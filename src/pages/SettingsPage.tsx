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
        rest_api: __('Protection API REST', 'lebo-secu'),
        login_protection: __('Protection page de login', 'lebo-secu'),
        user_enumeration: __('Blocage énumération users', 'lebo-secu'),
        security_headers: __('Headers de sécurité HTTP', 'lebo-secu'),
        disable_features: __('Désactivation features WP', 'lebo-secu'),
        audit_log: __('Audit Log', 'lebo-secu'),
    };

    const featureDescriptions: Record<string, string> = {
        admin_url: __('Change l\'URL d\'accès à l\'administration pour bloquer les bots qui ciblent /wp-admin.', 'lebo-secu'),
        hide_version: __('Supprime la version de WordPress des sources HTML pour limiter la reconnaissance des failles.', 'lebo-secu'),
        rest_api: __('Bloque l\'accès public à l\'API REST pour les utilisateurs non authentifiés.', 'lebo-secu'),
        login_protection: __('Limite le nombre de tentatives de connexion échouées pour bloquer les attaques par force brute.', 'lebo-secu'),
        user_enumeration: __('Empêche la découverte des identifiants utilisateurs via les archives d\'auteur.', 'lebo-secu'),
        security_headers: __('Ajoute des en-têtes HTTP de sécurité (X-Frame-Options, X-XSS-Protection, etc.).', 'lebo-secu'),
        disable_features: __('Désactive des fonctionnalités vulnérables comme XML-RPC, l\'éditeur de fichiers et les Pingbacks.', 'lebo-secu'),
        audit_log: __('Enregistre toutes les actions sensibles dans un journal d\'audit sécurisé.', 'lebo-secu'),
    };

    const calculateSecurityScore = () => {
        const criticalFeatures = [
            'hide_version',
            'admin_url',
            'login_protection',
            'user_enumeration',
            'rest_api',
            'security_headers',
            'disable_features',
            'htaccess',
            'audit_log',
        ];

        let score = 0;
        criticalFeatures.forEach(feature => {
            if (config.features[feature] && config.features[feature].enabled) {
                score++;
            }
        });

        return Math.round((score / criticalFeatures.length) * 10);
    };

    const securityScore = calculateSecurityScore();

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

            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', margin: '20px 0 40px 0' }}>
                <div className="lbs-score-card" style={{ marginBottom: 0 }}>
                    <div className="lbs-score-value">{securityScore}/10</div>
                    <div className="lbs-score-label">{__('Score de sécurité', 'lebo-secu')}</div>
                </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' }}>
                <h2 style={{ fontSize: '1.5em', margin: 0 }}>{__('Modules de sécurité', 'lebo-secu')}</h2>
                <Button isPrimary isBusy={isSaving} disabled={isSaving} onClick={onSave}>
                    {__('Sauvegarder les réglages', 'lebo-secu')}
                </Button>
            </div>

            <div className="lbs-settings-grid">
                {Object.keys(featureNames).map(featureId => {
                    const featureConfig = config.features[featureId];
                    if (!featureConfig) return null;

                    return (
                        <div key={featureId} className="lbs-settings-card">
                            <div className="lbs-settings-card-header">
                                <h3>{featureNames[featureId]}</h3>
                                <ToggleControl
                                    label={featureConfig.enabled ? __('Activé', 'lebo-secu') : __('Désactivé', 'lebo-secu')}
                                    checked={featureConfig.enabled}
                                    onChange={(val) => handleFeatureToggle(featureId, val)}
                                />
                            </div>
                            
                            <div className="lbs-settings-card-body">
                                <p>{featureDescriptions[featureId]}</p>
                                
                                {featureId === 'admin_url' && featureConfig.enabled && (
                                    <div style={{ marginTop: '10px', padding: '10px', background: '#f8f9fa', borderRadius: '4px', border: '1px solid #e2e4e7' }}>
                                        <p style={{ margin: '0 0 10px 0', fontSize: '13px', color: '#666' }}>
                                            {__('Nouvelle URL pour remplacer /wp-admin :', 'lebo-secu')}
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
                                                style={{ padding: '3px 8px', borderRadius: '4px', border: '1px solid #8c8f94', width: '100%' }}
                                            />
                                        </div>
                                    </div>
                                )}

                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default SettingsPage;
