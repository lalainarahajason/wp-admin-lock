import { useState, useEffect } from '@wordpress/element';
import { Panel, PanelBody, PanelRow, ToggleControl, Button, Notice, Spinner, Modal, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchConfig, saveConfig, LbsConfig } from '../api';

const SettingsPage = () => {
    const [config, setConfig] = useState<LbsConfig | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState<{message: string, isError: boolean} | null>(null);
    const [isHtaccessModalOpen, setIsHtaccessModalOpen] = useState(false);

    useEffect(() => {
        fetchConfig().then(setConfig).catch(() => {
            setNotice({ message: __('Erreur lors du chargement de la configuration.', 'lebo-secu'), isError: true });
        });
    }, []);

    const handleFeatureToggle = (featureId: string, enabled: boolean) => {
        if (!config) return;

        if (featureId === 'htaccess' && enabled) {
            setIsHtaccessModalOpen(true);
        }

        setConfig({
            ...config,
            features: {
                ...config.features,
                [featureId]: {
                    ...config.features[featureId],
                    enabled
                }
            }
        });
    };

    const onSave = async () => {
        if (!config) return;
        setIsSaving(true);
        setNotice(null);
        try {
            const updated = await saveConfig(config);
            setConfig(updated);
            setNotice({ message: __('Configuration sauvegardée avec succès.', 'lebo-secu'), isError: false });
        } catch (error: any) {
            setNotice({ message: error.message || __('Erreur de sauvegarde.', 'lebo-secu'), isError: true });
        } finally {
            setIsSaving(false);
        }
    };

    if (!config) {
        return <Spinner />;
    }

    const featureNames: Record<string, string> = {
        admin_url: __('Custom Admin URL', 'lebo-secu'),
        hide_version: __('Masquer la version WP', 'lebo-secu'),
        htaccess: __('Gestionnaire .htaccess', 'lebo-secu'),
        rest_api: __('Protection API REST', 'lebo-secu'),
        login_protection: __('Protection page de login', 'lebo-secu'),
        user_enumeration: __('Blocage énumération users', 'lebo-secu'),
        security_headers: __('Headers de sécurité HTTP', 'lebo-secu'),
        disable_features: __('Désactivation features WP', 'lebo-secu'),
        audit_log: __('Audit Log', 'lebo-secu'),
    };

    return (
        <div className="lbs-settings-container">
            {notice && (
                <Notice 
                    status={notice.isError ? 'error' : 'success'} 
                    isDismissible={true} 
                    onRemove={() => setNotice(null)}
                >
                    {notice.message}
                </Notice>
            )}

            <Panel>
                <PanelBody title={__('Modules de sécurité', 'lebo-secu')} initialOpen={true}>
                    {Object.keys(featureNames).map(featureId => {
                        const featureConfig = config.features[featureId];
                        if (!featureConfig) return null;

                        return (
                            <PanelRow key={featureId} className="lbs-feature-panel">
                                <div style={{ borderBottom: '1px solid #eee', paddingBottom: '15px' }}>
                                    <h3>{featureNames[featureId]}</h3>
                                    <ToggleControl
                                        label={featureConfig.enabled ? __('Activé', 'lebo-secu') : __('Désactivé', 'lebo-secu')}
                                        checked={featureConfig.enabled}
                                        onChange={(val) => handleFeatureToggle(featureId, val)}
                                    />
                                    
                                    {featureId === 'admin_url' && featureConfig.enabled && (
                                        <div style={{ marginTop: '10px', padding: '10px', background: '#f8f9fa', borderRadius: '4px' }}>
                                            <p style={{ margin: '0 0 10px 0', fontSize: '13px', color: '#666' }}>
                                                {__('Choisissez la nouvelle URL pour remplacer /wp-admin et /wp-login.php :', 'lebo-secu')}
                                            </p>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                                                <code>{window.location.origin}/</code>
                                                <input 
                                                    type="text" 
                                                    value={featureConfig.slug || 'mon-espace-admin'} 
                                                    onChange={(e) => {
                                                        const newConfig = { ...config };
                                                        newConfig.features[featureId].slug = e.target.value;
                                                        setConfig(newConfig);
                                                    }}
                                                    placeholder="mon-espace-admin"
                                                    style={{ padding: '3px 8px', borderRadius: '4px', border: '1px solid #8c8f94' }}
                                                />
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </PanelRow>
                        );
                    })}
                </PanelBody>
            </Panel>

            <div style={{ marginTop: '20px' }}>
                <Button isPrimary isBusy={isSaving} disabled={isSaving} onClick={onSave}>
                    {__('Sauvegarder les réglages', 'lebo-secu')}
                </Button>
            </div>

            {isHtaccessModalOpen && (
                <Modal
                    title={__('Règles .htaccess personnalisées', 'lebo-secu')}
                    onRequestClose={() => setIsHtaccessModalOpen(false)}
                >
                    <p>{__('Ces règles seront injectées dans votre fichier .htaccess global lors de la sauvegarde.', 'lebo-secu')}</p>
                    <TextareaControl
                        label={__('Règles Apache', 'lebo-secu')}
                        value={config.features?.htaccess?.rules || ''}
                        onChange={(val) => {
                            const newConfig = { ...config };
                            newConfig.features.htaccess.rules = val;
                            setConfig(newConfig);
                        }}
                        rows={10}
                    />
                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '20px' }}>
                        <Button isPrimary onClick={() => setIsHtaccessModalOpen(false)}>
                            {__('Ok', 'lebo-secu')}
                        </Button>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default SettingsPage;
