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

    const getSeverityStyles = (severity: string) => {
        switch (severity) {
            case 'CRITICAL': return { bg: '#fbe9e9', text: '#d63638', border: '#f1aeb5' }; // Red
            case 'WARNING':  return { bg: '#fff8e5', text: '#d97e00', border: '#fcdb83' }; // Yellow/Orange
            case 'NOTICE':   return { bg: '#edf7ff', text: '#2271b1', border: '#72aee6' }; // Blue
            default:         return { bg: '#f6f7f7', text: '#646970', border: '#c3c4c7' }; // Grey
        }
    };

    const renderMetadata = (metadata: any) => {
        if (!metadata) return null;
        try {
            const meta = typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
            
            // Cas spécial pour CONFIG_UPDATED avec diff
            if (meta.changes && typeof meta.changes === 'object') {
                return (
                    <div className="lbs-log-details" style={{ marginTop: '4px' }}>
                        {meta.source && <div style={{ marginBottom: '6px' }}><strong>Source:</strong> <code>{meta.source}</code></div>}
                        <div style={{ fontWeight: 'bold', marginBottom: '6px', fontSize: '10px', color: '#333' }}>{__('CHANGEMENTS :', 'lebo-secu')}</div>
                        {Object.entries(meta.changes).map(([feature, diff]: [string, any]) => (
                            <div key={feature} style={{ paddingLeft: '8px', marginBottom: '8px', borderLeft: '3px solid #72aee6' }}>
                                <div style={{ fontWeight: '700', textTransform: 'uppercase', fontSize: '9px', color: '#555' }}>{feature.replace(/_/g, ' ')}</div>
                                <div style={{ fontSize: '11px', color: '#666', marginTop: '4px', wordBreak: 'break-all', fontFamily: 'monospace' }}>
                                    <div style={{ textDecoration: 'line-through', color: '#d63638', opacity: 0.7 }}>{JSON.stringify(diff.old)}</div>
                                    <div style={{ margin: '2px 0' }}><Dashicon icon="arrow-down-alt2" size={14} style={{ color: '#aaa' }} /></div>
                                    <div style={{ color: '#00a32a', fontWeight: 'bold' }}>{JSON.stringify(diff.new)}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                );
            }

            return Object.entries(meta).map(([k, v]) => (
                <div key={k}><strong>{k}:</strong> {typeof v === 'object' ? JSON.stringify(v) : String(v)}</div>
            ));
        } catch (e) {
            return String(metadata);
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
                        <th style={{ width: '120px' }}>{__('Utilisateur', 'lebo-secu')}</th>
                        <th style={{ width: '130px' }}>{__('IP', 'lebo-secu')}</th>
                        <th>{__('Détails', 'lebo-secu')}</th>
                        <th style={{ width: '70px' }}>{__('Actions', 'lebo-secu')}</th>
                    </tr>
                </thead>
                <tbody>
                    {logs.length === 0 ? (
                        <tr>
                            <td colSpan={6}>{__('Aucun log enregistré pour le moment.', 'lebo-secu')}</td>
                        </tr>
                    ) : (
                        logs.map((log: any) => {
                            const styles = getSeverityStyles(log.severity);
                            return (
                                <tr key={log.id}>
                                    <td>{log.created_at}</td>
                                    <td>
                                        <span style={{ 
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            padding: '2px 8px',
                                            borderRadius: '4px',
                                            fontSize: '10px',
                                            fontWeight: 'bold',
                                            backgroundColor: styles.bg,
                                            color: styles.text,
                                            border: `1px solid ${styles.border}`,
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.5px'
                                        }}>
                                            {log.event_code}
                                        </span>
                                    </td>
                                    <td>{log.user_login ? log.user_login : (log.actor_id && log.actor_id !== "0" ? `ID: ${log.actor_id}` : __('Anonyme', 'lebo-secu'))}</td>
                                    <td><code>{log.actor_ip}</code></td>
                                    <td>
                                        <div style={{ fontSize: '11px', color: '#666' }}>
                                            {renderMetadata(log.metadata)}
                                            {log.request_url && (
                                                <div style={{ marginTop: '6px', borderTop: '1px solid #eee', paddingTop: '4px', opacity: 0.8 }}>
                                                    <strong>URL:</strong> <code style={{ fontSize: '10px' }}>{log.request_url}</code>
                                                </div>
                                            )}
                                            {log.user_agent && (
                                                <div style={{ marginTop: '2px', fontSize: '9px', fontStyle: 'italic', opacity: 0.6 }}>
                                                    UA: {log.user_agent}
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
                            );
                        })
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
