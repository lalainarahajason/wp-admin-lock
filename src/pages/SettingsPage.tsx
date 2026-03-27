import { useState, useEffect } from '@wordpress/element';
import { Panel, PanelBody, PanelRow, ToggleControl, Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchConfig, saveConfig, LbsConfig } from '../api';

const SettingsPage = () => {
    const [config, setConfig] = useState<LbsConfig | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState<{message: string, isError: boolean} | null>(null);

    useEffect(() => {
        fetchConfig().then(setConfig).catch(() => {
            setNotice({ message: __('Erreur lors du chargement de la configuration.', 'lebo-secu'), isError: true });
        });
    }, []);

    const handleFeatureToggle = (featureId: string, enabled: boolean) => {
        if (!config) return;
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
                                <div>
                                    <h3>{featureNames[featureId]}</h3>
                                    <ToggleControl
                                        label={featureConfig.enabled ? __('Activé', 'lebo-secu') : __('Désactivé', 'lebo-secu')}
                                        checked={featureConfig.enabled}
                                        onChange={(val) => handleFeatureToggle(featureId, val)}
                                    />
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
        </div>
    );
};

export default SettingsPage;
