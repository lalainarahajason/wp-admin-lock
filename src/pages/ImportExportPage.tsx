import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const ImportExportPage = () => {
    const [isExporting, setIsExporting] = useState(false);
    const [isImporting, setIsImporting] = useState(false);
    const [notice, setNotice] = useState<{message: string, isError: boolean} | null>(null);
    const [fileContent, setFileContent] = useState<string>('');

    const handleExport = async () => {
        setIsExporting(true);
        try {
            const config = await apiFetch({ path: '/lebo-secu/v1/export' });
            const blob = new Blob([JSON.stringify(config, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `lebo-secu-export-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
            setNotice({ message: __('Configuration exportée avec succès.', 'lebo-secu'), isError: false });
        } catch (err) {
            setNotice({ message: __('Erreur lors de l\'export.', 'lebo-secu'), isError: true });
        } finally {
            setIsExporting(false);
        }
    };

    const handleFileChange = (e: any) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (ev) => {
            setFileContent(ev.target?.result as string);
        };
        reader.readAsText(file);
    };

    const handleImport = async () => {
        if (!fileContent) return;
        setIsImporting(true);
        setNotice(null);

        try {
            const configObj = JSON.parse(fileContent);
            await apiFetch({
                path: '/lebo-secu/v1/import',
                method: 'POST',
                data: configObj
            });
            setNotice({ message: __('Configuration importée et appliquée avec succès. Rechargez la page.', 'lebo-secu'), isError: false });
        } catch (err: any) {
            setNotice({ message: err.message || __('Fichier JSON invalide ou erreur serveur.', 'lebo-secu'), isError: true });
        } finally {
            setIsImporting(false);
        }
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

            <div className="lbs-feature-panel">
                <h3>{__('Exporter la configuration', 'lebo-secu')}</h3>
                <p>{__('Téléchargez un fichier JSON contenant tous vos réglages de sécurité pour les déployer sur un autre site.', 'lebo-secu')}</p>
                <Button isPrimary isBusy={isExporting} disabled={isExporting} onClick={handleExport}>
                    {__('Télécharger l\'export', 'lebo-secu')}
                </Button>
            </div>

            <div className="lbs-feature-panel" style={{ marginTop: '20px' }}>
                <h3>{__('Importer une configuration', 'lebo-secu')}</h3>
                <p>{__('Sélectionnez un fichier JSON généré par Lebo Secu. L\'importation écrasera votre configuration actuelle.', 'lebo-secu')}</p>
                <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                    <input type="file" accept=".json" onChange={handleFileChange} />
                    <Button isSecondary isBusy={isImporting} disabled={isImporting || !fileContent} onClick={handleImport}>
                        {__('Importer et appliquer', 'lebo-secu')}
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default ImportExportPage;
