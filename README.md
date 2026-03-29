# 🛡️ Lebo Secu

**Lebo Secu** est un plugin de sécurité WordPress moderne et performant conçu pour renforcer la sécurité de votre installation en bloquant les vecteurs d'attaque les plus courants. 

Doté d'une interface d'administration ultra-rapide (développée en React), Lebo Secu centralise tous les réglages critiques sur une seule page.

---

## ✨ Fonctionnalités

Lebo Secu embarque 9 modules de sécurité essentiels, activables ou désactivables à la volée :

1. 🔗 **Custom Admin URL** : Changez l'URL d'accès à l'administration (`/wp-admin` et `wp-login.php`) par une URL secrète de votre choix afin de bloquer les attaques par force brute (ex: `https://votre-site.com/mon-espace-admin`).
2. 🕵️ **Masquage de la version WP** : Supprime complètement la version de WordPress des sources HTML (balises meta, scripts, styles, flux RSS) pour limiter la reconnaissance des failles par les bots. (Optionnel pour l'espace d'administration).
3. 🚧 **Protection API REST** : Bloque l'accès public à l'API REST de WordPress pour empêcher la fuite de données (tout en maintenant un fonctionnement normal pour les utilisateurs connectés).
4. 🛑 **Protection de la page de connexion** : Limite le nombre de tentatives de connexion échouées et bannit temporairement les adresses IP suspectes.
5. 👤 **Blocage de l'énumération des utilisateurs** : Empêche la découverte des identifiants (usernames) via les archives d'auteur WordPress ou les requêtes `?author=N`.
6. 🛡️ **En-têtes de sécurité HTTP (Security Headers)** : Ajoute de puissants en-têtes HTTP de sécurité (X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy) pour protéger contre le Clickjacking et le XSS.
7. ✂️ **Désactivation de fonctionnalités vulnérables** : Désactive l'éditeur de fichiers interne, XML-RPC et les Pingbacks/Trackbacks, souvent utilisés comme vecteurs d'attaques.
8. 📝 **Protection .htaccess** : Générez et injectez des règles Apache strictes directement dans votre fichier `.htaccess` pour une sécurité optimale au niveau du serveur web (avec un éditeur code avancé et un système de backup automatique intégré).
9. 📋 **Journal d'Audit (Audit Log) Avancé** : Enregistre toutes les actions sensibles dans une table sécurisée.
   - **Standardisation** : Codes d'événements explicites (`AUTH_LOGIN_SUCCESS`, `CONFIG_UPDATED`, etc.) avec badges de sévérité colorés.
   - **Suivi des modifications** : Historique précis des changements de configuration avec affichage du différentiel (Ancien vs Nouveau réglage).
   - **Gestion des IPs** : Bloquez ou ré-autorisez des adresses IP suspectes en un clic directement depuis le journal, avec indicateurs d'état visuels (cercles rouge/vert).
   - **Détails techniques** : Capture automatique de l'URL de la requête et du User-Agent pour chaque événement.

💡 **Bonus** : Un module d'**Import/Export (JSON)** vous permet d'exporter la configuration de Lebo Secu pour la réintégrer facilement sur d'autres sites WordPress.

---

## 🚀 Installation

### 1. Installation classique (Archive ZIP)

1. Téléchargez le dépôt sous forme d'archive zip.
2. Décompressez l'archive dans le répertoire `/wp-content/plugins/` de votre installation WordPress.
3. Renommez le dossier en `lebo-secu` (si nécessaire).
4. Connectez-vous à votre tableau de bord WordPress.
5. Allez dans **Extensions > Extensions installées** et activez **Lebo Secu**.

### 2. Installation pour les développeurs (Clonage & Build)

Si vous souhaitez contribuer ou compiler les assets vous-même :

```bash
# 1. Naviguer vers le dossier des plugins
cd wp-content/plugins/

# 2. Cloner le repository
git clone https://github.com/lalainarahajason/wp-admin-lock.git lebo-secu
cd lebo-secu

# 3. Installer les dépendances (NPM)
npm install

# 4. Compiler les assets React & CSS
npm run build
```

*Une fois le build terminé, activez le plugin depuis l'interface WordPress.*

---

## ⚙️ Configuration

Une fois le plugin activé, un nouveau menu **Lebo Secu** fera son apparition dans la barre latérale gauche de l'administration WordPress.

- **Paramètres** : Espace principal pour activer/désactiver les modules.
- **Import / Export** : Migrez vos configs d'un site à l'autre.
- **Journal d'audit** : Consultez l'historique de sécurité du site (lecture seule).
- **Fichier .htaccess** : Gérez les sécurités Apache bas niveau.

---

## 🛠️ Stack technique

- **Backend** : PHP 8.1+ strictement orienté objet, respect complet des *WordPress Coding Standards* (WPCS).
- **Frontend** : React.js (via `@wordpress/element`), TypeScript, Sass, `@wordpress/components`.
- **Infrastructure** : Environnement local géré sous Docker via `@wordpress/env`.

> ⚠️ **Avertissement de non-responsabilité** : La modification des URL d'administration ou du fichier `.htaccess` peut entraîner la perte d'accès à l'interface de gestion si mal utilisée. Veillez à toujours conserver des accès FTP/SFTP d'urgence.
