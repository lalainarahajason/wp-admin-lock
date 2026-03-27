# Lebo Secu — Contexte projet

## C'est quoi
Plugin WordPress de sécurité multi-sites nommé **Lebo Secu**.
Stack : PHP 8.1+, WordPress 6.4+, développement local sous Docker.
Objectif : durcissement WordPress déployable facilement sur plusieurs sites via import/export de config.

### Sécurité — non négociable
> Règles issues des [WordPress Plugin Developer Guidelines](https://developer.wordpress.com/docs/wordpress-com-marketplace/plugin-developer-guidelines/) et vérifiées par le Plugin Check (`plugin_review_phpcs`, `late_escaping`, `no_unfiltered_uploads`).

**Chaque fichier PHP** commence par :
```php
defined('ABSPATH') || exit;
```

**Chaque dossier** du plugin contient un `index.php` vide avec `<?php // Silence is golden.`

**Toute lecture de `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE`** doit être sanitizée immédiatement, sans exception :
```php
// Jamais : $value = $_POST['key']
// Toujours :
$value     = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
$int_value = absint( $_GET['id'] ?? 0 );
$html      = wp_kses_post( $_POST['content'] ?? '' );
```

**Toute sortie** est échappée au plus près de l'affichage (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`).
Ne jamais stocker une valeur échappée — échapper uniquement au moment du rendu.

**Capability + Nonce — matrice complète :**

| Contexte | `current_user_can` | Nonce |
|---|---|---|
| Page admin (rendu HTML) | `manage_options` | `wp_nonce_field()` dans le form |
| Action admin (`admin_post_*`, formulaire) | `manage_options` | `check_admin_referer('action_name')` |
| Endpoint REST GET (lecture seule) | `manage_options` via `permission_callback` | Non requis (protocole REST "safe method") |
| Endpoint REST POST / DELETE (mutation) | `manage_options` via `permission_callback` | `verify_nonce` via header `X-WP-Nonce` |
| AJAX admin (`wp_ajax_*`) | `manage_options` | `check_ajax_referer('action_name')` |

**Implémentation REST — pattern obligatoire :**
```php
register_rest_route('agency-sentinel/v1', '/scan/start', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_start'],
    'permission_callback' => fn() => current_user_can('manage_options'),
]);

// Dans handle_start() — vérification nonce sur les mutations :
public function handle_start( WP_REST_Request $request ): WP_REST_Response {
    if ( ! wp_verify_nonce( $request->get_header('X-WP-Nonce'), 'wp_rest' ) ) {
        return new WP_REST_Response(['error' => 'Invalid nonce'], 403);
    }
    // ...
}
```

**Côté JS — toujours passer le nonce REST :**
```javascript
// wp_localize_script passe : { nonce: wp_create_nonce('wp_rest') }
fetch(ajaxurl, {
    method: 'POST',
    headers: { 'X-WP-Nonce': agencySentinel.nonce }
});
```


## Structure des dossiers
```
lebo-secu/
├── GEMINI.md
├── docker/
│   ├── docker-compose.yml
│   ├── .env.example
│   └── wp-config-extra.php
├── plugin/
│   ├── lebo-secu.php                  # Entry point
│   ├── includes/
│   │   ├── Admin/                     # Pages admin WP
│   │   ├── Features/                  # Une classe PHP par feature
│   │   │   ├── AdminUrl.php
│   │   │   ├── HideVersion.php
│   │   │   ├── HtaccessManager.php
│   │   │   ├── RestApiProtection.php
│   │   │   ├── LoginProtection.php
│   │   │   ├── UserEnumeration.php
│   │   │   ├── SecurityHeaders.php
│   │   │   ├── DisableFeatures.php
│   │   │   └── AuditLog.php
│   │   ├── ImportExport.php
│   │   └── Helpers.php
│   ├── assets/
│   │   ├── css/admin.css
│   │   └── js/admin.js
│   └── languages/
├── tests/                             # PHPUnit
├── docs/
│   ├── PRD.md
│   └── features/                     # Un fichier par feature
└── Makefile
```

## Conventions de code

- **Préfixe classes** : `LBS_` (ex: `LBS_AdminUrl`, `LBS_Helpers`)
- **Préfixe fonctions** : `lbs_` (ex: `lbs_get_config()`)
- **Préfixe constantes** : `LBS_` (ex: `LBS_VERSION`, `LBS_PLUGIN_DIR`)
- **Option WordPress** : `lebosecu_config` (JSON dans wp_options)
- **Table BDD** : `wp_lebosecu_logs`
- **Slug plugin** : `lebo-secu`
- **Text domain** : `lebo-secu`
- Une classe par fichier, un fichier par feature
- Chaque feature implémente une interface commune `LBS_Feature_Interface`

## Règles de sécurité (non négociables)

- Toujours vérifier `check_admin_referer()` ou `wp_verify_nonce()` sur les actions POST
- Toujours vérifier `current_user_can('manage_options')` avant toute écriture de config
- Toutes les entrées : `sanitize_text_field()`, `wp_kses()`, `absint()` selon le type
- Toutes les sorties : `esc_html()`, `esc_attr()`, `esc_url()`
- Requêtes SQL directes : toujours `$wpdb->prepare()`
- Jamais de `eval()`, jamais d'include dynamique non contrôlé

## Configuration (schéma JSON)

Stockée dans `wp_options` sous la clé `lebosecu_config` :
```json
{
  "version": "1.0.0",
  "features": {
    "admin_url":         { "enabled": true, "slug": "mon-espace-admin" },
    "hide_version":      { "enabled": true },
    "htaccess":          { "enabled": true, "rules": [] },
    "rest_api":          { "enabled": true, "whitelist_endpoints": [], "whitelist_ips": [] },
    "login_protection":  { "enabled": true, "max_attempts": 5, "lockout_duration": 900, "basic_auth": false, "email_notify": false, "whitelist_ips": [] },
    "user_enumeration":  { "enabled": true },
    "security_headers":  { "enabled": true, "headers": {} },
    "disable_features":  { "enabled": true, "file_editor": true, "xmlrpc": true, "oembed": false, "pingbacks": true },
    "audit_log":         { "enabled": true, "retention_days": 30 }
  }
}
```

## Features (v1.0)

| ID  | Nom                        | Priorité |
|-----|----------------------------|----------|
| F1  | Custom Admin URL           | HAUTE    |
| F2  | Masquage version WordPress | HAUTE    |
| F3  | Gestionnaire .htaccess     | HAUTE    |
| F4  | Protection API REST        | HAUTE    |
| F5  | Import / Export config     | HAUTE    |
| F6  | Protection page de login   | HAUTE    |
| F7  | Blocage énumération users  | HAUTE    |
| F8  | Headers de sécurité HTTP   | MOYENNE  |
| F9  | Désactivation features WP  | MOYENNE  |
| F10 | Audit Log                  | MOYENNE  |

## Ordre de développement recommandé

1. **Scaffold + Docker** — structure, entry point, interface, Helpers, docker-compose
2. **F2, F7, F9** — hooks simples, aucun effet de bord risqué
3. **F1, F6** — réécriture URL et rate limiting login
4. **F4, F8** — sécurité active sur requêtes entrantes
5. **F3** — écriture fichier .htaccess (toujours avec backup)
6. **F10** — table BDD + interface audit log
7. **F5** — import/export JSON (transversal, en dernier)

## Environnement Docker

- WordPress : `http://localhost:8000`
- phpMyAdmin : `http://localhost:8080`
- Le dossier `plugin/` est monté dans `/var/www/html/wp-content/plugins/lebo-secu`
- Commandes via `make` (voir Makefile)

## Compatibilité cible

- WordPress 6.4+
- PHP 8.1+
- Apache avec mod_rewrite (requis pour F1 et F3)
- Single site uniquement en v1.0 (pas de multisite)
- Compatible Gutenberg et WooCommerce

## Ce qu'il ne faut jamais faire

> Ces règles sont vérifiées automatiquement par `wordpress/plugin-check-action`. Une violation = build cassé en CI.

- **Ne jamais lire `$_GET`/`$_POST` brut** — toujours `sanitize_text_field( wp_unslash( ... ) )`
- **Ne jamais `echo` une variable non échappée** — `echo esc_html( $var )` au minimum
- **Ne jamais utiliser `file_get_contents()` avec une URL** — utiliser `wp_remote_get()`
- **Ne jamais `echo` en dehors d'une méthode de rendu dédiée**
- **Ne jamais appeler `die()` ou `exit()` dans une action AS** — laisser l'exception remonter
- **Ne jamais créer de table sans passer par `Database::create_tables()`**
- **Ne jamais stocker de données sensibles** (chemins quarantinés) dans les options — utiliser les tables custom
- **Ne jamais faire de requête SQL avec concaténation de variables** — `$wpdb->prepare()` obligatoire
- **Ne jamais autoriser l'upload de fichiers PHP** dans un répertoire web-accessible
- **Ne jamais stocker des chemins absolus** dans `agency_sentinel_integrity_hashes` — chemins relatifs uniquement
