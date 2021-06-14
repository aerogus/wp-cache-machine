# WP Cache Machine

Système de cache statique HTML pour WordPress

## Description

Le système est composé de 2 fichiers principaux

### index-cache-machine.php

Ce script remplace le index.php de WordPress.
Il fonctionne de pair avec le plugin "wp-cache-machine"
mais n'en dépend par car il est chargé avant.
Il n'est exécuté que si le fichier de cache n'a pas déjà été servi
directement par les règles de réécriture du .htaccess
Il écrit les fichiers de cache

Possibilité de bypass en envoyant le header HTTP suivant :
`HTTP_X_WP_CACHE_MACHINE_BYPASS = on`

### wp-cache-machine.php

Cette librairie ne fait qu'invalider les fichiers en cache
l'écriture proprement dite du cache se fait dans index-cache-machine.php
(qui doit remplacer le index.php de la racine WordPress)

## Prérequis

- Apache 2
- WordPress >= 4.0
- PHP >= 7
- module php_curl
- accès ssh au serveur d'hébergement

## Installation

- Aller dans le `ABSPATH` (chemin de base de WordPress), ex `cd /var/www/wordpress`
- Créer le répertoire de stockage `ABSPATH . '/cache'` avec les droits d'écriture pour le serveur web (ex `www-data`), `mkdir cache`
- Créer un fichier de log debug. `touch wp-content/debug.log`
- Faire une copie de sauvegarde du point d'entrée WordPress `ABSPATH . '/index.php'`, ex `cp index.php index-orig.php`
- Copier index-cache-machine.php vers `ABSPATH . '/index.php'`, ex `cp ./wp-content/plugins/wp-cache-machine/index-cache-machine.php ./index-cache-machine.php`
- Définir la constante `WP_CACHE_MACHINE_THEME` dans `index-cache-machine.php` avec le nom de votre thème actif, ex `twentytwentyone`
- Activer le plugin via l'admin web ou via `wp-cli` si installé
- Intégrer les règles personnalisées proposées dans l'admin par le plugin (onglet Tools / Cache Machine / Règles à mettre dans le .htaccess) dans votre `ABSPATH . '/.htaccess'` ou dans le fichier du virtual host (entre ### BEGIN WP Cache Machine ### et ### END WP Cache Machine ###)
- Activer le nouveau point d'entrée: `rm index.php && ln -s index-cache-machine.php index.php`

## Désinstallation

- Désactiver le point d'entrée cache machine et restaurer celui d'origine : `unlink index.php && mv index-orig.php index.php`
- Désactiver le plugin (vide le cache) via l'admin web ou via `wp-cli` si installé
- Supprimer le répertoire de stockage `ABSPATH . '/cache'`
- Retirer les règles personnalisées de `ABSPATH . '/.htaccess'` ou du fichier du virtual host (entre ### BEGIN WP Cache Machine ### et ### END WP Cache Machine ###)

