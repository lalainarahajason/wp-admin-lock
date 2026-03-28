import apiFetch from '@wordpress/api-fetch';

export interface FeatureConfig {
    enabled: boolean;
    [key: string]: any;
}

export interface LbsConfig {
    version: string;
    features: Record<string, FeatureConfig>;
}

/**
 * Récupère la configuration actuelle.
 */
export const fetchConfig = async (): Promise<LbsConfig> => {
    return apiFetch({ path: '/lebo-secu/v1/config' });
};

/**
 * Sauvegarde la configuration entière.
 */
export const saveConfig = async (config: LbsConfig): Promise<LbsConfig> => {
    return apiFetch({
        path: '/lebo-secu/v1/config',
        method: 'POST',
        data: config,
    });
};

/**
 * Récupère les logs d'audit.
 */
export const fetchAuditLogs = async (page = 1, perPage = 20): Promise<any> => {
    return apiFetch({ path: `/lebo-secu/v1/logs?page=${page}&per_page=${perPage}` });
};

/**
 * Bannit une IP via Quick Ban.
 */
export const quickBanIp = async (ip: string): Promise<any> => {
    return apiFetch({
        path: '/lebo-secu/v1/logs/ban',
        method: 'POST',
        data: { ip },
    });
};

/**
 * Retourne l'URL pour l'export CSV.
 */
export const getAuditLogExportUrl = (): string => {
    // @ts-ignore
    const root = window.wpApiSettings?.root || (window.lbsAdmin?.restUrl ? window.lbsAdmin.restUrl.replace('lebo-secu/v1', '') : '/wp-json/');
    // @ts-ignore
    const nonce = window.wpApiSettings?.nonce || window.lbsAdmin?.nonce || '';
    
    // Ensure root ends with slash
    const normalizedRoot = root.endsWith('/') ? root : `${root}/`;
    
    return `${normalizedRoot}lebo-secu/v1/logs/export?_wpnonce=${nonce}`;
};



/**
 * Récupère le contenu actuel du fichier .htaccess.
 */
export const fetchHtaccess = async (): Promise<{ content: string; error?: string }> => {
    return apiFetch({ path: '/lebo-secu/v1/htaccess' });
};

/**
 * Sauvegarde les règles personnalisées dans le .htaccess.
 */
export const saveHtaccess = async (rules: string): Promise<{ success: boolean; error?: string }> => {
    return apiFetch({
        path: '/lebo-secu/v1/htaccess',
        method: 'POST',
        data: { rules },
    });
};
