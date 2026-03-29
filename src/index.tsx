import { createRoot } from '@wordpress/element';
import SettingsPage from './pages/SettingsPage';
import AuditLogPage from './pages/AuditLogPage';
import ImportExportPage from './pages/ImportExportPage';
import HtaccessPage from './pages/HtaccessPage';
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

    const htaccessApp = document.getElementById('lbs-htaccess-app');
    if (htaccessApp) {
        createRoot(htaccessApp).render(<HtaccessPage />);
    }
});
