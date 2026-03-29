import { useState } from '@wordpress/element';
import { Modal, Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface CustomHeader {
    key: string;
    value: string;
}

interface SecurityHeadersModalProps {
    isOpen: boolean;
    onClose: () => void;
    customHeaders: CustomHeader[];
    onSave: (headers: CustomHeader[]) => void;
}

const SecurityHeadersModal = ({ isOpen, onClose, customHeaders, onSave }: SecurityHeadersModalProps) => {
    const [headers, setHeaders] = useState<CustomHeader[]>(customHeaders || []);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');

    if (!isOpen) return null;

    const handleAdd = () => {
        if (!newKey.trim() || !newValue.trim()) return;
        
        const newHeaders = [...headers, { key: newKey.trim(), value: newValue.trim() }];
        setHeaders(newHeaders);
        onSave(newHeaders);
        setNewKey('');
        setNewValue('');
    };

    const handleRemove = (index: number) => {
        const newHeaders = headers.filter((_, i) => i !== index);
        setHeaders(newHeaders);
        onSave(newHeaders);
    };

    return (
        <Modal title={__('Gérer les en-têtes HTTP de sécurité', 'lebo-secu')} onRequestClose={onClose} style={{ minWidth: '600px' }}>
            
            <div style={{ marginBottom: '20px' }}>
                <h3 style={{ fontSize: '14px', marginTop: 0 }}>{__('En-têtes natifs (Gérés par Lebo Secu)', 'lebo-secu')}</h3>
                <p style={{ fontSize: '13px', color: '#666', margin: '0 0 10px 0' }}>
                    {__('Ces en-têtes sont injectés automatiquement lorsque le module est activé.', 'lebo-secu')}
                </p>
                <div style={{ background: '#f0f0f1', padding: '10px', borderRadius: '4px', fontSize: '13px', fontFamily: 'monospace' }}>
                    <div><strong>X-Frame-Options:</strong> SAMEORIGIN</div>
                    <div><strong>X-Content-Type-Options:</strong> nosniff</div>
                    <div><strong>Referrer-Policy:</strong> strict-origin-when-cross-origin</div>
                    <div><strong>Permissions-Policy:</strong> camera=(), microphone=(), geolocation=()</div>
                </div>
            </div>

            <div>
                <h3 style={{ fontSize: '14px', margin: '20px 0 10px 0' }}>{__('En-têtes personnalisés', 'lebo-secu')}</h3>
                <p style={{ fontSize: '13px', color: '#666', margin: '0 0 15px 0' }}>
                    {__('Ajoutez ici d\'autres en-têtes HTTP de sécurité (ex: Strict-Transport-Security).', 'lebo-secu')}
                </p>

                {headers.length > 0 ? (
                    <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '15px', fontSize: '13px' }}>
                        <thead>
                            <tr style={{ background: '#f8f9fa', borderBottom: '1px solid #ddd' }}>
                                <th style={{ padding: '8px', textAlign: 'left', width: '35%' }}>{__('Nom de l\'en-tête', 'lebo-secu')}</th>
                                <th style={{ padding: '8px', textAlign: 'left' }}>{__('Valeur', 'lebo-secu')}</th>
                                <th style={{ padding: '8px', textAlign: 'center', width: '60px' }}>{__('Action', 'lebo-secu')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {headers.map((header, index) => (
                                <tr key={index} style={{ borderBottom: '1px solid #eee' }}>
                                    <td style={{ padding: '8px', fontFamily: 'monospace' }}>{header.key}</td>
                                    <td style={{ padding: '8px', fontFamily: 'monospace' }}>{header.value}</td>
                                    <td style={{ padding: '8px', textAlign: 'center' }}>
                                        <Button 
                                            isDestructive 
                                            isSmall 
                                            variant="link"
                                            onClick={() => handleRemove(index)}
                                        >
                                            {__('Supprimer', 'lebo-secu')}
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <div style={{ padding: '15px', background: '#fff', border: '1px dashed #ccd0d4', textAlign: 'center', color: '#666', marginBottom: '15px', borderRadius: '4px' }}>
                        {__('Aucun en-tête personnalisé défini.', 'lebo-secu')}
                    </div>
                )}

                <div style={{ display: 'flex', gap: '10px', alignItems: 'flex-end', background: '#f8f9fa', padding: '15px', borderRadius: '4px', border: '1px solid #e2e4e7' }}>
                    <div style={{ flex: 1 }}>
                        <TextControl
                            label={__('Clé (ex: Strict-Transport-Security)', 'lebo-secu')}
                            value={newKey}
                            onChange={setNewKey}
                            placeholder="Strict-Transport-Security"
                        />
                    </div>
                    <div style={{ flex: 2 }}>
                        <TextControl
                            label={__('Valeur (ex: max-age=31536000)', 'lebo-secu')}
                            value={newValue}
                            onChange={setNewValue}
                            placeholder="max-age=31536000"
                        />
                    </div>
                    <div style={{ paddingBottom: '8px' }}>
                        <Button isSecondary onClick={handleAdd} disabled={!newKey.trim() || !newValue.trim()}>
                            {__('Ajouter', 'lebo-secu')}
                        </Button>
                    </div>
                </div>

            </div>

            <div style={{ marginTop: '20px', textAlign: 'right', display: 'flex', justifyContent: 'flex-end' }}>
                <Button isPrimary onClick={onClose}>
                    {__('Fermer', 'lebo-secu')}
                </Button>
            </div>
            
        </Modal>
    );
};

export default SecurityHeadersModal;
