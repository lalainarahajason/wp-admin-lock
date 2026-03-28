import { useState, useEffect } from '@wordpress/element';
import { Spinner, Button, Dashicon, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchAuditLogs, quickBanIp, getAuditLogExportUrl } from '../api';

const AuditLogPage = () => {
    const [logs, setLogs] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [isBanning, setIsBanning] = useState<string | null>(null);

    const loadLogs = (p: number) => {
        setIsLoading(true);
        fetchAuditLogs(p).then((res) => {
            setLogs(res.logs || []);
        }).catch(() => {
            setLogs([]);
        }).finally(() => {
            setIsLoading(false);
        });
    };

    useEffect(() => {
        loadLogs(page);
    }, [page]);

    const handleQuickBan = (ip: string) => {
        if (!confirm(__('Voulez-vous vraiment bannir cette IP pour 7 jours ?', 'lebo-secu'))) return;
        
        setIsBanning(ip);
        quickBanIp(ip).then(() => {
            alert(__('IP bannie avec succès.', 'lebo-secu'));
            loadLogs(page);
        }).catch((err) => {
            alert(err.message || __('Erreur lors du bannissement.', 'lebo-secu'));
        }).finally(() => {
            setIsBanning(null);
        });
    };

    const getSeverityColor = (severity: string) => {
        switch (severity) {
            case 'CRITICAL': return '#d63638'; // Red
            case 'WARNING':  return '#ffb900'; // Yellow/Orange
            case 'NOTICE':   return '#72aee6'; // Blue
            default:         return '#646970'; // Grey
        }
    };

    if (isLoading && page === 1 && logs.length === 0) {
        return <div style={{ padding: '20px' }}><Spinner /></div>;
    }

    return (
        <div className="lbs-audit-log-page">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h2>{__('Audit Log — Événements de sécurité', 'lebo-secu')}</h2>
                <Button 
                    variant="secondary" 
                    href={getAuditLogExportUrl()} 
                    icon={<Dashicon icon="download" />}
                >
                    {__('Exporter en CSV', 'lebo-secu')}
                </Button>
            </div>

            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style={{ width: '150px' }}>{__('Date', 'lebo-secu')}</th>
                        <th style={{ width: '180px' }}>{__('Action', 'lebo-secu')}</th>
                        <th>{__('Utilisateur', 'lebo-secu')}</th>
                        <th>{__('IP', 'lebo-secu')}</th>
                        <th>{__('Détails', 'lebo-secu')}</th>
                        <th style={{ width: '80px' }}>{__('Actions', 'lebo-secu')}</th>
                    </tr>
                </thead>
                <tbody>
                    {logs.length === 0 ? (
                        <tr>
                            <td colSpan={6}>{__('Aucun log enregistré pour le moment.', 'lebo-secu')}</td>
                        </tr>
                    ) : (
                        logs.map((log: any) => (
                            <tr key={log.id}>
                                <td>{log.created_at}</td>
                                <td>
                                    <span style={{ 
                                        display: 'inline-block',
                                        width: '8px',
                                        height: '8px',
                                        borderRadius: '50%',
                                        backgroundColor: getSeverityColor(log.severity),
                                        marginRight: '8px'
                                    }}></span>
                                    <strong>{log.event_code}</strong>
                                </td>
                                <td>{log.user_login ? log.user_login : (log.actor_id && log.actor_id !== "0" ? `ID: ${log.actor_id}` : __('Anonyme', 'lebo-secu'))}</td>
                                <td><code>{log.actor_ip}</code></td>
                                <td>
                                    <div style={{ fontSize: '11px', color: '#666' }}>
                                        {(() => {
                                            if (!log.metadata) return '';
                                            try {
                                                const meta = typeof log.metadata === 'string' ? JSON.parse(log.metadata) : log.metadata;
                                                return Object.entries(meta).map(([k, v]) => (
                                                    <div key={k}><strong>{k}:</strong> {String(v)}</div>
                                                ));
                                            } catch (e) {
                                                return String(log.metadata);
                                            }
                                        })()}
                                        {log.request_url && (
                                            <div style={{ marginTop: '4px', borderTop: '1px solid #eee', paddingTop: '2px' }}>
                                                <strong>URL:</strong> {log.request_url}
                                            </div>
                                        )}
                                    </div>
                                </td>
                                <td>
                                    <Tooltip text={__('Bannir cette IP (7 jours)', 'lebo-secu')}>
                                        <Button 
                                            isDestructive 
                                            variant="link"
                                            onClick={() => handleQuickBan(log.actor_ip)}
                                            disabled={isBanning === log.actor_ip}
                                        >
                                            {isBanning === log.actor_ip ? <Spinner /> : <Dashicon icon="shield-alt" />}
                                        </Button>
                                    </Tooltip>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                <Button 
                    variant="secondary" 
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page === 1}
                >
                    {__('Précédent', 'lebo-secu')}
                </Button>
                <span style={{ alignSelf: 'center' }}>{__('Page', 'lebo-secu')} {page}</span>
                <Button 
                    variant="secondary" 
                    onClick={() => setPage(p => p + 1)}
                    disabled={logs.length < 20}
                >
                    {__('Suivant', 'lebo-secu')}
                </Button>
            </div>
        </div>
    );
};

export default AuditLogPage;
