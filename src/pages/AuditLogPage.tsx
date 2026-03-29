import { useState, useEffect } from '@wordpress/element';
import { Spinner, Button, Dashicon, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchAuditLogs, quickBanIp, unbanIp, fetchBannedIps, getAuditLogExportUrl } from '../api';

const AuditLogPage = () => {
    const [logs, setLogs] = useState<any[]>([]);
    const [bannedIps, setBannedIps] = useState<string[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [isProcessing, setIsProcessing] = useState<string | null>(null);

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

    const loadBannedIps = () => {
        fetchBannedIps().then((res) => {
            setBannedIps(res.banned || []);
        });
    };

    useEffect(() => {
        loadLogs(page);
        loadBannedIps();
    }, [page]);

    const handleQuickBan = (ip: string) => {
        if (!confirm(__('Voulez-vous vraiment bannir cette IP pour 7 jours ?', 'lebo-secu'))) return;
        
        setIsProcessing(ip);
        quickBanIp(ip).then(() => {
            loadLogs(page);
            loadBannedIps();
        }).catch((err) => {
            alert(err.message || __('Erreur lors du bannissement.', 'lebo-secu'));
        }).finally(() => {
            setIsProcessing(null);
        });
    };

    const handleUnban = (ip: string) => {
        if (!confirm(__('Voulez-vous vraiment ré-autoriser cette IP ?', 'lebo-secu'))) return;
        
        setIsProcessing(ip);
        unbanIp(ip).then(() => {
            loadLogs(page);
            loadBannedIps();
        }).catch((err) => {
            alert(err.message || __('Erreur lors du déblocage.', 'lebo-secu'));
        }).finally(() => {
            setIsProcessing(null);
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
                        <th style={{ width: '110px' }}>{__('Actions', 'lebo-secu')}</th>
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
                            const isBanned = bannedIps.includes(log.actor_ip);
                            
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
                                        </div>
                                    </td>
                                    <td style={{ verticalAlign: 'middle' }}>
                                        {isProcessing === log.actor_ip ? (
                                            <Spinner />
                                        ) : isBanned ? (
                                            <Button 
                                                variant="secondary"
                                                onClick={() => handleUnban(log.actor_ip)}
                                                style={{ 
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '6px',
                                                    backgroundColor: '#e7f6ed', 
                                                    color: '#008a20', 
                                                    borderColor: '#00a32a',
                                                    fontSize: '11px',
                                                    height: '24px',
                                                    lineHeight: '22px'
                                                }}
                                            >
                                                <span style={{ 
                                                    display: 'inline-block', 
                                                    width: '8px', 
                                                    height: '8px', 
                                                    borderRadius: '50%', 
                                                    backgroundColor: '#00a32a' 
                                                }}></span>
                                                {__('Ré-autoriser', 'lebo-secu')}
                                            </Button>
                                        ) : (
                                            <Button 
                                                variant="secondary"
                                                onClick={() => handleQuickBan(log.actor_ip)}
                                                style={{ 
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '6px',
                                                    backgroundColor: '#f6f7f7',
                                                    fontSize: '11px',
                                                    height: '24px',
                                                    lineHeight: '22px'
                                                }}
                                            >
                                                <span style={{ 
                                                    display: 'inline-block', 
                                                    width: '8px', 
                                                    height: '8px', 
                                                    borderRadius: '50%', 
                                                    backgroundColor: '#d63638' 
                                                }}></span>
                                                {__('Bloquer l\'IP', 'lebo-secu')}
                                            </Button>
                                        )}
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
