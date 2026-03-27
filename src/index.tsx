import { createRoot } from '@wordpress/element';
import SettingsPage from './pages/SettingsPage';
import AuditLogPage from './pages/AuditLogPage';
import ImportExportPage from './pages/ImportExportPage';
import './style.scss';

document.addEventListener('DOMContentLoaded', () => {
    const settingsApp = document.getElementById('lbs-settings-app');
    if (settingsApp) {
        createRoot(settingsApp).render(<SettingsPage />);
    }

    const auditApp = document.getElementById('lbs-audit-log-app');
    if (auditApp) {
        createRoot(auditApp).render(<AuditLogPage />);
    }

    const importExportApp = document.getElementById('lbs-import-export-app');
    if (importExportApp) {
        createRoot(importExportApp).render(<ImportExportPage />);
    }
});
