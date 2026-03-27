# CONFIG-MIGRATION — Stratégie de migration de configuration
**Transversal — affecte : Helpers.php, entry point, F5 (Import/Export)**

## Problème

La configuration du plugin est stockée dans `wp_options` sous la clé `lebosecu_config` (JSON). Dès qu'une feature ajoute un champ, modifie une valeur par défaut ou restructure un sous-objet, les sites déjà déployés ont une config périmée. Sans stratégie de migration, deux cas de figure se produisent :

1. **Champ absent** → `undefined` / `null` → comportement imprévisible (PHP Warning, feature qui ne s'active pas)
2. **Schéma restructuré** → lecture de l'ancienne structure → bug silencieux

## Principe général

**La config en base n'est jamais lue brute.** Elle passe toujours par `LBS_Helpers::get_config()` qui fusionne la config sauvegardée avec la config par défaut du code actuel, puis exécute les migrations nécessaires dans l'ordre.

```
Config en base (ancienne version)
        ↓
LBS_Config_Migrator::migrate($saved_config)   ← migrations versionnées
        ↓
LBS_Helpers::merge_with_defaults($migrated)   ← fusion avec les defaults courants
        ↓
Config utilisable dans le code
```

## Schéma versionné

La config inclut un champ `version` qui est la version du **schéma**, pas du plugin. Le schéma peut changer sans que la version du plugin change (et vice versa).

```json
{
  "version": "1.0.0",
  "features": { ... }
}
```

La version du schéma suit semver et est définie dans le code :

```php
// Helpers.php
const CONFIG_SCHEMA_VERSION = '1.0.0';
```

## Implémentation

### LBS_Helpers::get_config()

```php
public static function get_config(): array {
    $saved = get_option('lebosecu_config', null);

    if ( $saved === null ) {
        // Premier démarrage — retourner les defaults sans écrire en base
        // (l'écriture se fait au save explicite depuis l'UI)
        return self::get_default_config();
    }

    if ( is_string($saved) ) {
        $saved = json_decode($saved, true) ?? [];
    }

    // 1. Migrer le schéma si nécessaire
    $migrated = LBS_Config_Migrator::migrate($saved);

    // 2. Fusionner avec les defaults (ajoute les clés manquantes)
    $merged = self::merge_with_defaults($migrated, self::get_default_config());

    // 3. Si la migration a produit un changement, persister silencieusement
    if ( $migrated !== $saved ) {
        update_option('lebosecu_config', wp_json_encode($merged));
    }

    return $merged;
}
```

### LBS_Helpers::merge_with_defaults()

Une fusion récursive qui **ne jamais écrase** une valeur existante, mais **ajoute** les clés absentes.

```php
public static function merge_with_defaults( array $saved, array $defaults ): array {
    foreach ( $defaults as $key => $default_value ) {
        if ( ! array_key_exists($key, $saved) ) {
            // Clé absente → prendre le default
            $saved[$key] = $default_value;
        } elseif ( is_array($default_value) && is_array($saved[$key]) ) {
            // Sous-objet → fusionner récursivement
            $saved[$key] = self::merge_with_defaults($saved[$key], $default_value);
        }
        // Valeur scalaire existante → conserver sans toucher
    }
    return $saved;
}
```

> **Différence avec `array_merge` ou `wp_parse_args`** : ces deux fonctions sont superficielles (non-récursives). `wp_parse_args` sur un tableau imbriqué efface le sous-tableau entier si la clé parent existe. `merge_with_defaults` descend dans l'arbre.

### LBS_Config_Migrator

Une classe dédiée avec une méthode statique par migration, exécutées dans l'ordre croissant des versions.

```php
// plugin/includes/Config/Migrator.php
defined('ABSPATH') || exit;

class LBS_Config_Migrator {

    /**
     * Point d'entrée — exécute toutes les migrations nécessaires dans l'ordre.
     */
    public static function migrate( array $config ): array {
        $version = $config['version'] ?? '0.0.0';

        // Chaque migration vérifie si elle s'applique avant d'agir
        $config = self::migrate_to_1_0_0($config, $version);
        // $config = self::migrate_to_1_1_0($config, $version);  ← à ajouter en v1.1

        return $config;
    }

    /**
     * Migration 0.x.x → 1.0.0
     * Cas : config créée avant l'introduction du champ `version`.
     */
    private static function migrate_to_1_0_0( array $config, string $current_version ): array {
        if ( version_compare($current_version, '1.0.0', '>=') ) {
            return $config; // Déjà à jour
        }

        // Exemple de transformation : renommer une clé
        // Avant : $config['features']['rest']['enabled']
        // Après : $config['features']['rest_api']['enabled']
        if ( isset($config['features']['rest']) && ! isset($config['features']['rest_api']) ) {
            $config['features']['rest_api'] = $config['features']['rest'];
            unset($config['features']['rest']);
        }

        $config['version'] = '1.0.0';
        return $config;
    }
}
```

**Règles pour ajouter une migration :**

1. Créer une méthode privée `migrate_to_X_Y_Z(array $config, string $current_version): array`
2. La méthode **commence toujours** par `if (version_compare($current_version, 'X.Y.Z', '>=')) return $config;`
3. Décrire la transformation dans le docblock (avant / après)
4. Mettre à jour `$config['version']` en fin de méthode
5. Appeler la méthode depuis `migrate()` dans l'ordre chronologique
6. Mettre à jour `LBS_Helpers::CONFIG_SCHEMA_VERSION`

## Cas couverts par cette stratégie

| Cas | Mécanisme |
|---|---|
| Nouveau champ dans une feature existante | `merge_with_defaults` ajoute la clé avec la valeur par défaut |
| Renommage d'une clé | Migration versionnée dans `LBS_Config_Migrator` |
| Restructuration d'un sous-objet | Migration versionnée |
| Suppression d'une clé obsolète | Migration versionnée (unset + log optionnel) |
| Changement de valeur par défaut | Migration versionnée (ne touche que si valeur === ancien défaut) |
| Premier démarrage (aucune config en base) | `get_config()` retourne les defaults sans écriture |
| Import F5 avec config d'une version antérieure | `LBS_Config_Migrator::migrate()` appelé également dans `ImportExport::import()` |

## Intégration avec F5 (Import/Export)

L'import doit passer par le même pipeline de migration avant d'écrire en base :

```php
// ImportExport.php — dans la méthode import()
$imported_config = json_decode($json_content, true);

// 1. Valider le schéma JSON (structure minimale)
if ( ! $this->validate_schema($imported_config) ) {
    return new WP_Error('invalid_schema', __('Schéma JSON invalide.', 'lebo-secu'));
}

// 2. Migrer si la config importée est d'une version antérieure
$migrated = LBS_Config_Migrator::migrate($imported_config);

// 3. Fusionner avec les defaults du code actuel
$final = LBS_Helpers::merge_with_defaults($migrated, LBS_Helpers::get_default_config());

// 4. (Dry-run) Retourner $final sans écrire, pour affichage du diff
// (Application) update_option('lebosecu_config', wp_json_encode($final));
```

## Tests à prévoir

```php
// tests/Unit/ConfigMigratorTest.php

// Cas : config sans champ version → doit être migrée vers 1.0.0
public function test_config_without_version_is_migrated(): void {
    $old_config = ['features' => ['rest' => ['enabled' => true]]];
    $migrated   = LBS_Config_Migrator::migrate($old_config);
    $this->assertEquals('1.0.0', $migrated['version']);
    $this->assertArrayHasKey('rest_api', $migrated['features']);
    $this->assertArrayNotHasKey('rest', $migrated['features']);
}

// Cas : config déjà à jour → ne doit pas être modifiée
public function test_current_version_config_is_unchanged(): void {
    $config   = ['version' => '1.0.0', 'features' => []];
    $migrated = LBS_Config_Migrator::migrate($config);
    $this->assertSame($config, $migrated);
}

// Cas : merge_with_defaults ne doit pas écraser les valeurs existantes
public function test_merge_preserves_existing_values(): void {
    $saved    = ['features' => ['hide_version' => ['enabled' => false]]];
    $defaults = ['features' => ['hide_version' => ['enabled' => true]]];
    $merged   = LBS_Helpers::merge_with_defaults($saved, $defaults);
    $this->assertFalse($merged['features']['hide_version']['enabled']);
}

// Cas : merge_with_defaults doit ajouter les clés absentes
public function test_merge_adds_missing_keys(): void {
    $saved    = ['features' => ['hide_version' => ['enabled' => true]]];
    $defaults = ['features' => ['hide_version' => ['enabled' => true, 'new_option' => 'default_value']]];
    $merged   = LBS_Helpers::merge_with_defaults($saved, $defaults);
    $this->assertEquals('default_value', $merged['features']['hide_version']['new_option']);
}
```

## Fichiers impactés

| Fichier | Modification |
|---|---|
| `plugin/includes/Helpers.php` | Ajouter `CONFIG_SCHEMA_VERSION`, `get_default_config()`, `merge_with_defaults()`, modifier `get_config()` |
| `plugin/includes/Config/Migrator.php` | Nouveau fichier |
| `plugin/includes/ImportExport.php` | Appeler `LBS_Config_Migrator::migrate()` dans `import()` |
| `plugin/lebo-secu.php` | Ajouter `require_once` pour `Config/Migrator.php` |
| `plugin/includes/Config/index.php` | Nouveau dossier → fichier vide `<?php // Silence is golden.` |
| `tests/Unit/ConfigMigratorTest.php` | Nouveau fichier de tests |