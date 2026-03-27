# Lebo Secu — Product Requirements Document v1.0

> **Statut** : Brouillon — En revue
> **Version** : 1.0.0
> **Environnement cible** : Docker local → Production

---

## Table des matières

1. [Introduction](#1-introduction)
2. [Environnement de développement Docker](#2-environnement-de-développement-docker)
3. [Architecture du plugin](#3-architecture-du-plugin)
4. [Fonctionnalités](#4-fonctionnalités)
5. [Interface d'administration](#5-interface-dadministration)
6. [Modèle de données](#6-modèle-de-données)
7. [Sécurité et contraintes techniques](#7-sécurité-et-contraintes-techniques)
8. [Stratégie de tests](#8-stratégie-de-tests)
9. [Déploiement](#9-déploiement)
10. [Roadmap](#10-roadmap)
11. [Glossaire](#11-glossaire)
12. [Migration](#12-migration)

---

## 1. Introduction

### 1.1 Objectif du document

Ce PRD définit les exigences fonctionnelles, techniques et d'architecture du plugin WordPress **Lebo Secu**. Il sert de référence unique pour le développement, les tests et le déploiement du plugin sur l'ensemble du parc de sites WordPress.

### 1.2 Contexte

Le parc de sites WordPress gérés est exposé à des vecteurs d'attaque courants : énumération de versions, brute force sur le login, exploitation de l'API REST publique, injections via `.htaccess` mal configuré. L'objectif est de centraliser les mesures de durcissement dans un plugin unique, versionné, facilement déployable et configurable site par site.

### 1.3 Périmètre

- Plugin WordPress autonome, sans dépendance à un service tiers payant
- Compatible WordPress 6.4+ et PHP 8.1+
- Développement en environnement Docker local
- Déployable via ZIP manuel ou WP-CLI
- Interface d'administration propre dans le dashboard WordPress

### 1.4 Hors périmètre

- Pare-feu applicatif WAF complet (Cloudflare, Wordfence niveau avancé)
- Gestion des certificats SSL
- Sauvegarde / restauration des bases de données
- CDN et performance

---

## 2. Environnement de développement Docker

Tout le développement s'effectue en local via Docker. Aucune installation PHP/MySQL native n'est requise sur le poste de développement.

### 2.1 Stack Docker

| Service      | Description                              |
|--------------|------------------------------------------|
| wordpress    | Image officielle `wordpress:6.4-apache`  |
| db           | Image `mysql:8.0`                        |
| phpmyadmin   | Interface BDD — port 8080                |
| wpcli        | Image `wordpress:cli` pour automatisation|

### 2.2 Structure du projet

```
lebo-secu/
├── docker/
│   ├── docker-compose.yml
│   ├── .env.example
│   └── wp-config-extra.php
├── plugin/
│   ├── lebo-secu.php              # Entry point
│   ├── includes/
│   │   ├── Admin/                 # Pages admin
│   │   ├── Features/              # Une classe par feature
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
├── tests/
├── docs/
│   ├── PRD.md
│   └── features/
├── Makefile
└── GEMINI.md
```

### 2.3 docker-compose.yml

```yaml
version: "3.9"
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql

  wordpress:
    image: wordpress:6.4-apache
    depends_on: [db]
    ports:
      - "8000:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: ${DB_NAME}
      WORDPRESS_DB_USER: ${DB_USER}
      WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./plugin:/var/www/html/wp-content/plugins/lebo-secu
      - wp_data:/var/www/html

  wpcli:
    image: wordpress:cli
    depends_on: [wordpress]
    volumes:
      - wp_data:/var/www/html

  phpmyadmin:
    image: phpmyadmin:latest
    ports: ["8080:80"]
    environment:
      PMA_HOST: db

volumes:
  db_data:
  wp_data:
```

### 2.4 Commandes Makefile

| Commande             | Action                              |
|----------------------|-------------------------------------|
| `make up`            | Démarrer les conteneurs             |
| `make down`          | Arrêter les conteneurs              |
| `make logs`          | Voir les logs WordPress             |
| `make shell`         | Shell dans le conteneur WP          |
| `make cli cmd="..."` | Exécuter une commande WP-CLI        |
| `make install`       | Activer le plugin via WP-CLI        |
| `make test`          | Lancer la suite PHPUnit             |

---

## 3. Architecture du plugin

### 3.1 Principes de conception

- Une classe PHP par fonctionnalité (Single Responsibility)
- Chaque feature est activable / désactivable indépendamment
- Toute la configuration est stockée dans un seul `wp_options` JSON (clé `lebosecu_config`)
- Aucune modification permanente sans consentement explicite (backup avant écriture `.htaccess`)
- Hooks WordPress natifs uniquement — pas de remplacement de fichiers core

### 3.2 Interface commune des features

```php
interface LBS_Feature_Interface {
    public static function is_enabled(array $config): bool;
    public function __construct(array $config);
    public function init(): void;
}
```

### 3.3 Entry point (lebo-secu.php)

```php
<?php
/**
 * Plugin Name: Lebo Secu
 * Description: Sécurisation multi-sites WordPress
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) exit;

define('LBS_VERSION', '1.0.0');
define('LBS_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once LBS_PLUGIN_DIR . 'includes/Helpers.php';

add_action('plugins_loaded', function() {
    $config = LBS_Helpers::get_config();
    foreach (LBS_Helpers::get_feature_classes() as $class) {
        if ($class::is_enabled($config)) {
            (new $class($config))->init();
        }
    }
    if (is_admin()) {
        (new LBS_Admin_Page($config))->init();
    }
});
```

---

## 4. Fonctionnalités

| ID | Nom | Spec | Priorité |
|:---|:---|:---|:---|
| **F1** | Custom Admin URL | [F1.md](./features/F1.md) | HAUTE |
| **F2** | Masquage version WP | [F2.md](./features/F2.md) | HAUTE |
| **F3** | Gestionnaire .htaccess | [F3.md](./features/F3.md) | HAUTE |
| **F4** | Protection API REST | [F4.md](./features/F4.md) | HAUTE |
| **F5** | Import/Export Config | *Core Feature* | HAUTE |
| **F6** | Protection Login | [F6.md](./features/F6.md) | HAUTE |
| **F7** | Blocage énumération | [F7.md](./features/F7.md) | HAUTE |
| **F8** | Headers de sécurité | [F8.md](./features/F8.md) | MOYENNE |
| **F9** | Désactivation WP Features| [F9.md](./features/F9.md) | MOYENNE |
| **F10**| Audit Log | [F10.md](./features/F10.md) | MOYENNE |

## 5. Interface d'administration

### 5.1 Structure des pages

| Page              | Contenu                                                                 |
|-------------------|-------------------------------------------------------------------------|
| Tableau de bord   | Résumé features actives, dernières alertes, score de sécurité /10      |
| Admin URL         | Configuration F1                                                        |
| Masquage version  | Configuration F2                                                        |
| .htaccess         | Éditeur F3 avec prévisualisation diff                                   |
| API REST          | Configuration F4 + whitelist endpoints/IPs                              |
| Login             | Configuration F6 + liste des IPs bloquées                              |
| Utilisateurs      | Configuration F7                                                        |
| Headers HTTP      | Toggles et valeurs F8                                                   |
| WordPress         | Toggles F9                                                              |
| Audit Log         | Tableau F10 avec filtres et export CSV                                  |
| Import / Export   | Interface F5                                                            |

### 5.2 Score de sécurité

Score calculé sur 10 points basé sur le nombre de features critiques activées, affiché dans le tableau de bord pour encourager l'activation progressive des protections.

---

## 6. Modèle de données

### 6.1 Configuration principale (wp_options)

Clé : `lebosecu_config`

```json
{
  "version": "1.0.0",
  "features": {
    "admin_url": {
      "enabled": true,
      "slug": "mon-espace-admin"
    },
    "hide_version": { "enabled": true },
    "htaccess": {
      "enabled": true,
      "rules": [
        { "id": "uuid", "label": "Block xmlrpc", "content": "...", "active": true }
      ]
    },
    "rest_api": {
      "enabled": true,
      "whitelist_endpoints": ["/wp-json/wp/v2/posts"],
      "whitelist_ips": []
    },
    "login_protection": {
      "enabled": true,
      "max_attempts": 5,
      "lockout_duration": 900,
      "basic_auth": false,
      "email_notify": false,
      "whitelist_ips": []
    },
    "user_enumeration": { "enabled": true },
    "security_headers": {
      "enabled": true,
      "headers": {
        "x_frame_options":  { "enabled": true, "value": "SAMEORIGIN" },
        "x_content_type":   { "enabled": true },
        "referrer_policy":  { "enabled": true, "value": "strict-origin-when-cross-origin" },
        "permissions_policy": { "enabled": true, "value": "camera=(), microphone=()" },
        "csp": { "enabled": false, "report_only": true, "value": "" }
      }
    },
    "disable_features": {
      "enabled": true,
      "file_editor": true,
      "xmlrpc": true,
      "oembed": false,
      "pingbacks": true
    },
    "audit_log": {
      "enabled": true,
      "retention_days": 30
    }
  }
}
```

### 6.2 Table Audit Log (wp_lebosecu_logs)

| Colonne      | Type / Description                                                  |
|--------------|---------------------------------------------------------------------|
| id           | BIGINT AUTO_INCREMENT PRIMARY KEY                                   |
| event_type   | VARCHAR(50) — `login_success`, `login_fail`, `api_blocked`, `config_change`… |
| user_id      | BIGINT NULL — ID WordPress si connecté                              |
| ip_address   | VARCHAR(45) — IPv4 / IPv6                                           |
| user_agent   | TEXT NULL                                                           |
| details      | JSON NULL — Données contextuelles                                   |
| created_at   | DATETIME DEFAULT CURRENT_TIMESTAMP                                  |

---

## 7. Sécurité et contraintes techniques

### 7.1 Règles de développement

- Toutes les entrées utilisateur sont sanitisées (`sanitize_text_field`, `wp_kses`, `absint`, etc.)
- Toutes les sorties sont échappées (`esc_html`, `esc_attr`, `esc_url`)
- Les opérations sensibles vérifient le nonce WP et la capability `manage_options`
- Les requêtes SQL directes utilisent `$wpdb->prepare()`
- Jamais de `eval()`, jamais d'include dynamique non contrôlé
- Compatibility check PHP 8.1+ au chargement du plugin

### 7.2 Compatibilité

| Critère       | Valeur cible                                          |
|---------------|-------------------------------------------------------|
| WordPress     | 6.4 et supérieur                                      |
| PHP           | 8.1 et supérieur                                      |
| Serveur web   | Apache (mod_rewrite requis pour F1 et F3)             |
| Multisite     | Non supporté en v1 (single site uniquement)           |
| WooCommerce   | Compatibilité testée pour F1 et F4                    |
| Gutenberg     | Pleinement compatible                                 |

### 7.3 Points de vigilance par feature

- **F1** : stocker le slug en base, pas en constante, pour permettre la modification sans accès FTP
- **F3** : vérifier les permissions fichier avant toute écriture, gérer les hébergements sans accès `.htaccess`
- **F4** : ne jamais bloquer sur critère IP seul (proxies, CDN) — toujours combiner avec l'authentification
- **F6** : la whitelist IP doit inclure l'IP du développeur dès le premier déploiement
- **F8** : activer CSP en `report-only` avant de passer en enforce pour éviter de casser le front

---

## 8. Stratégie de tests

### 8.1 Tests unitaires (PHPUnit)

- Une suite de tests par classe Feature
- Mock de `$wpdb` pour les tests impliquant la base de données
- Couverture minimale cible : 70% des méthodes publiques

### 8.2 Matrice de tests d'intégration (Docker)

| Scénario                               | Résultat attendu              |
|----------------------------------------|-------------------------------|
| Accès `/wp-admin` avec F1 actif        | HTTP 404                      |
| Accès slug custom                      | Dashboard WordPress           |
| GET `/wp-json` sans auth, F4 actif     | HTTP 401                      |
| GET `/wp-json` avec nonce valide       | HTTP 200                      |
| Brute force login (6 tentatives)       | Blocage IP dès la 6e          |
| `/?author=1` avec F7 actif             | Redirect vers accueil         |
| Export + Import config                 | Config identique après import |
| Response headers front-end            | `X-Frame-Options` présent     |

---

## 9. Déploiement

### 9.1 Local (Docker)

```bash
# 1. Cloner le dépôt
git clone https://github.com/your-org/lebo-secu.git
cd lebo-secu

# 2. Configurer les variables d'environnement
cp docker/.env.example docker/.env
# Éditer docker/.env

# 3. Démarrer l'environnement
make up

# 4. Installer WordPress et activer le plugin
make install

# 5. Accéder au site
# WordPress  : http://localhost:8000
# phpMyAdmin : http://localhost:8080
```

### 9.2 Production (ZIP)

1. Zipper le dossier `plugin/` → `lebo-secu.zip`
2. WordPress Admin > Extensions > Ajouter > Téléverser
3. Activer le plugin
4. Importer la configuration de référence via Import/Export (F5)
5. Vérifier le score de sécurité dans le tableau de bord Lebo Secu

### 9.3 Via WP-CLI

```bash
wp plugin install lebo-secu.zip --activate
wp option update lebosecu_config < config-reference.json
```

---

## 10. Roadmap

| Version | Contenu                                                                    |
|---------|----------------------------------------------------------------------------|
| v1.0    | F1 à F10 — fonctionnalités core décrites dans ce document                 |
| v1.1    | Support multisite WordPress, synchronisation de config entre sous-sites    |
| v1.2    | Intégration Webhook : alertes Slack / email sur événements critiques       |
| v2.0    | Dashboard centralisé pour gérer tous les sites depuis une interface unique |

---

## 11. Glossaire

| Terme        | Définition                                                                           |
|--------------|--------------------------------------------------------------------------------------|
| Nonce WP     | Token temporaire généré par WordPress pour sécuriser les actions                     |
| WP-CLI       | Interface en ligne de commande officielle de WordPress                               |
| REST API     | API WordPress accessible via `/wp-json`, utilisée par Gutenberg et les plugins modernes |
| .htaccess    | Fichier de configuration Apache contrôlant les règles de réécriture et d'accès      |
| Brute force  | Attaque consistant à tester massivement des combinaisons login/mot de passe          |
| Énumération  | Technique de découverte de logins via `/?author=N` ou l'API REST                    |
| CSP          | Content Security Policy — header HTTP limitant les sources de ressources autorisées  |
| WAF          | Web Application Firewall — pare-feu applicatif analysant le trafic HTTP              |

## 12. Migration
Nom | Spec | Priorité |
Stratégie de migration de configuration| [Migration.md](./docs/migration.md) | HAUTE |