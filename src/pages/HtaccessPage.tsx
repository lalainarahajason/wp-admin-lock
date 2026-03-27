import { useState, useEffect } from '@wordpress/element';
import { Panel, PanelBody, Button, Notice, Spinner, ToggleControl, TextareaControl } from '@wordpress/components';
import { fetchConfig, saveConfig, fetchHtaccess, saveHtaccess, LbsConfig } from '../api';

const HtaccessPage = () => {
    const [config, setConfig] = useState<LbsConfig | null>(null);
    const [fullHtaccess, setFullHtaccess] = useState<string>('');
    const [customRules, setCustomRules] = useState<string>('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    const loadData = () => {
        setIsLoading(true);
        Promise.all([fetchConfig(), fetchHtaccess()])
            .then(([configData, htaccessData]) => {
                setConfig(configData);
                setCustomRules(configData.features?.htaccess?.rules || '');
                if (htaccessData.error) {
                    setMessage({ type: 'error', text: htaccessData.error });
                } else {
                    setFullHtaccess(htaccessData.content || '');
                }
            })
            .catch((error) => {
                console.error(error);
                setMessage({ type: 'error', text: 'Erreur lors du chargement des données.' });
            })
            .finally(() => setIsLoading(false));
    };

    useEffect(() => {
        loadData();
    }, []);

    const handleSaveRules = () => {
        setIsSaving(true);
        setMessage(null);
        
        saveHtaccess(customRules)
            .then((res) => {
                if (res.error) {
                    setMessage({ type: 'error', text: res.error });
                } else {
                    setMessage({ type: 'success', text: 'Règles sauvegardées et injectées avec succès.' });
                    // Rechargement pour voir le fichier global mis à jour
                    loadData();
                }
            })
            .catch(() => {
                setMessage({ type: 'error', text: 'Une erreur est survenue lors de la sauvegarde.' });
            })
            .finally(() => setIsSaving(false));
    };

    const handleToggleFeature = (val: boolean) => {
        if (!config) return;
        const newConfig = {
            ...config,
            features: {
                ...config.features,
                htaccess: {
                    ...(config.features.htaccess || {}),
                    enabled: val,
                    rules: customRules
                }
            }
        };
        
        setConfig(newConfig);
        saveConfig(newConfig).then(() => {
            setMessage({ type: 'success', text: 'Statut de la fonctionnalité mis à jour.' });
        });
    };

    if (isLoading && !config) {
        return <Spinner />;
    }

    const isEnabled = config?.features?.htaccess?.enabled || false;

    return (
        <div className="lbs-settings-page">
            {message && (
                <Notice status={message.type} onRemove={() => setMessage(null)}>
                    {message.text}
                </Notice>
            )}

            <Panel>
                <PanelBody title="Activation du module" initialOpen={true}>
                    <ToggleControl
                        label="Activer la gestion du .htaccess"
                        help="Permet à Lebo Secu d'injecter des règles de sécurité directement dans le fichier .htaccess de votre serveur Apache."
                        checked={isEnabled}
                        onChange={handleToggleFeature}
                    />
                </PanelBody>

                {isEnabled && (
                    <PanelBody title="Règles personnalisées (Bloc Lebo Secu)" initialOpen={true}>
                        <p>
                            Ces règles seront injectées automatiquement entre les balises <code># BEGIN lebo-secu</code> et <code># END lebo-secu</code>.
                        </p>
                        <TextareaControl
                            label="Règles Apache"
                            value={customRules}
                            onChange={(val) => setCustomRules(val)}
                            rows={10}
                            help="Attention : une erreur de syntaxe empêchera Apache de démarrer (Erreur 500 sur tout le site)."
                        />
                        <Button isPrimary onClick={handleSaveRules} isBusy={isSaving} disabled={isSaving}>
                            Sauvegarder et injecter
                        </Button>
                    </PanelBody>
                )}

                <PanelBody title="Aperçu du fichier .htaccess global" initialOpen={false}>
                    <p>Contenu réel du fichier <code>.htaccess</code> à la racine de votre site WordPress :</p>
                    <pre style={{ background: '#f0f0f1', padding: '15px', overflowX: 'auto', border: '1px solid #ccd0d4' }}>
                        {fullHtaccess || 'Fichier vide ou introuvable.'}
                    </pre>
                </PanelBody>
            </Panel>
        </div>
    );
};

export default HtaccessPage;
