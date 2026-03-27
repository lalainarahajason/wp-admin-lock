import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchAuditLogs } from '../api';

const AuditLogPage = () => {
    const [logs, setLogs] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        fetchAuditLogs().then((res) => {
            setLogs(res.logs || []);
        }).catch(() => {
            // Error handling
            setLogs([]);
        }).finally(() => {
            setIsLoading(false);
        });
    }, []);

    if (isLoading) {
        return <Spinner />;
    }

    return (
        <div className="lbs-settings-container">
            <h2>{__('Derniers événements de sécurité', 'lebo-secu')}</h2>
            <table className="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>{__('Date', 'lebo-secu')}</th>
                        <th>{__('Action', 'lebo-secu')}</th>
                        <th>{__('Utilisateur', 'lebo-secu')}</th>
                        <th>{__('IP', 'lebo-secu')}</th>
                        <th>{__('Détails', 'lebo-secu')}</th>
                    </tr>
                </thead>
                <tbody>
                    {logs.length === 0 ? (
                        <tr>
                            <td colSpan={5}>{__('Aucun log enregistré pour le moment.', 'lebo-secu')}</td>
                        </tr>
                    ) : (
                        logs.map((log: any) => (
                            <tr key={log.id}>
                                <td>{log.created_at}</td>
                                <td><strong>{log.action}</strong></td>
                                <td>{log.user_id || '-'}</td>
                                <td>{log.ip_address}</td>
                                <td>{log.details ? JSON.stringify(log.details) : ''}</td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
};

export default AuditLogPage;
