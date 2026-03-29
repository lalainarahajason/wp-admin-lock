import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { fetchConfig, saveConfig, fetchHtaccess, LbsConfig } from '../api';
import apiFetch from '@wordpress/api-fetch';

const HtaccessPage = () => {
    const [config, setConfig] = useState<LbsConfig | null>(null);
    const [htaccessContent, setHtaccessContent] = useState<string>('');
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const lineNumbersRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const syncScroll = () => {
        if (lineNumbersRef.current && textareaRef.current) {
            lineNumbersRef.current.scrollTop = textareaRef.current.scrollTop;
        }
    };

    const loadData = useCallback(() => {
        setIsLoading(true);
        Promise.all([fetchConfig(), fetchHtaccess()])
            .then(([configData, htaccessData]) => {
                setConfig(configData);
                if (htaccessData.error) {
                    setMessage({ type: 'error', text: htaccessData.error });
                } else {
                    setHtaccessContent(htaccessData.content || '');
                }
            })
            .catch(() => setMessage({ type: 'error', text: 'Erreur lors du chargement des données.' }))
            .finally(() => setIsLoading(false));
    }, []);

    useEffect(() => { loadData(); }, []);

    const handleToggleFeature = (val: boolean) => {
        if (!config) return;
        const newConfig = {
            ...config,
            features: {
                ...config.features,
                htaccess: { ...(config.features.htaccess || {}), enabled: val }
            }
        };
        setConfig(newConfig);
        saveConfig(newConfig).then(() => {
            setMessage({ type: 'success', text: `Module .htaccess ${val ? 'activé' : 'désactivé'}.` });
        });
    };

    const handleSaveFullFile = () => {
        setIsSaving(true);
        setMessage(null);
        apiFetch({
            path: '/lebo-secu/v1/htaccess',
            method: 'PUT',
            data: { content: htaccessContent },
        } as any)
            .then(() => {
                // Sync la config DB : enabled=true, reload la config
                if (config) {
                    const newConfig = {
                        ...config,
                        features: {
                            ...config.features,
                            htaccess: { ...(config.features.htaccess || {}), enabled: true }
                        }
                    };
                    return saveConfig(newConfig).then((updated) => {
                        setConfig(updated);
                        setMessage({ type: 'success', text: '.htaccess sauvegardé avec succès. Un backup automatique a été créé.' });
                    });
                }
            })
            .catch((err: any) => {
                setMessage({ type: 'error', text: err?.message || 'Erreur lors de la sauvegarde.' });
            })
            .finally(() => setIsSaving(false));
    };

    if (isLoading && !config) {
        return <Spinner />;
    }

    const isEnabled = config?.features?.htaccess?.enabled || false;
    const lineCount = htaccessContent.split('\n').length;

    return (
        <div className="lbs-settings-page" style={{ maxWidth: '900px' }}>
            {message && (
                <Notice status={message.type} onRemove={() => setMessage(null)}>
                    {message.text}
                </Notice>
            )}

            {/* En-tête */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '20px' }}>
                <div>
                    <p style={{ margin: 0, color: '#666', fontSize: '13px' }}>
                        Éditez directement le fichier <code>.htaccess</code> à la racine de votre site. Un backup est créé automatiquement à chaque sauvegarde.
                    </p>
                </div>
                <div>
                    <ToggleControl
                        label="Module actif"
                        checked={isEnabled}
                        onChange={handleToggleFeature}
                    />
                </div>
            </div>

            {/* Éditeur principal */}
            <div style={{
                border: '1px solid #ccd0d4',
                borderRadius: '4px',
                overflow: 'hidden',
                boxShadow: '0 1px 3px rgba(0,0,0,0.08)'
            }}>
                {/* Barre d'état de l'éditeur */}
                <div style={{
                    background: '#f0f0f1',
                    color: '#3c434a',
                    padding: '8px 15px',
                    fontFamily: 'monospace',
                    fontSize: '12px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    borderBottom: '1px solid #ccd0d4'
                }}>
                    <span>📄 .htaccess</span>
                    <span style={{ opacity: 0.8 }}>{lineCount} ligne{lineCount > 1 ? 's' : ''} · Apache config</span>
                </div>

                {/* Zone de texte éditable */}
                <div style={{ display: 'flex', background: '#fff', height: '420px', overflow: 'hidden' }}>
                    {/* Numéros de ligne — défilent avec le textarea */}
                    <div
                        ref={lineNumbersRef}
                        style={{
                            padding: '12px 10px',
                            background: '#f6f7f7',
                            color: '#646970',
                            fontFamily: '"Fira Code", "Courier New", monospace',
                            fontSize: '13px',
                            lineHeight: '1.6',
                            textAlign: 'right',
                            userSelect: 'none',
                            minWidth: '45px',
                            borderRight: '1px solid #ccd0d4',
                            whiteSpace: 'pre',
                            overflowY: 'hidden'
                        }}
                    >
                        {htaccessContent.split('\n').map((_, i) => `${i + 1}`).join('\n')}
                    </div>

                    {/* Contenu éditable */}
                    <textarea
                        ref={textareaRef}
                        value={htaccessContent}
                        onChange={(e) => setHtaccessContent(e.target.value)}
                        onScroll={syncScroll}
                        spellCheck={false}
                        style={{
                            flex: 1,
                            background: '#ffffff',
                            color: '#3c434a',
                            fontFamily: '"Fira Code", "Courier New", monospace',
                            fontSize: '13px',
                            lineHeight: '1.6',
                            padding: '12px 15px',
                            border: 'none',
                            outline: 'none',
                            resize: 'none',
                            height: '100%',
                            overflowY: 'scroll',
                            overflowX: 'auto',
                            whiteSpace: 'pre'
                        }}
                    />
                </div>

                {/* Barre d'actions */}
                <div style={{
                    background: '#f0f0f1',
                    borderTop: '1px solid #ccd0d4',
                    padding: '10px 15px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '10px'
                }}>
                    <Button
                        isPrimary
                        onClick={handleSaveFullFile}
                        isBusy={isSaving}
                        disabled={isSaving}
                    >
                        💾 Sauvegarder le fichier
                    </Button>
                    <Button
                        isSecondary
                        onClick={loadData}
                        disabled={isSaving}
                    >
                        Annuler les modifications
                    </Button>
                    <span style={{ marginLeft: 'auto', color: '#50575e', fontSize: '12px' }}>
                        ⚠️ Un backup automatique est créé avant chaque sauvegarde.
                    </span>
                </div>
            </div>
        </div>
    );
};

export default HtaccessPage;
