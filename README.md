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

## Installation

- Créer le répertoire de stockage `ABSPATH . '/cache'` avec droits d'écriture pour le serveur web
- Activer le plugin via l'admin web ou via `wp-cli` si installé
- Faire une sauvegarde de `ABSPATH . '/index.php'`, ex `cp index.php index.orig.php`
- Définir la constante `WP_CACHE_MACHINE_THEME` du `index-cache-machine.php` avec le nom du thème actif, ex `twentyeleven`
- Copier index-cache-machine.php vers `ABSPATH . '/index.php'`, ex `cp ./wp-content/plugins/wp-cache-machine/index-cache-machine.php ./index.php`
- Intégrer les règles personnalisées proposées par le plugin dans `ABSPATH . '/.htaccess'` ou dans le fichier du virtual host

## Désinstallation

- Restaurer votre sauvegarde de `ABSPATH . '/index.php'`, ex `mv index.orig.php index.php`
- Désactiver le plugin (vide le cache) via l'admin web ou via `wp-cli` si installé
- Supprimer le répertoire de stockage `ABSPATH . '/cache'`
- Retirer les règles personnalisées de `ABSPATH . '/.htaccess'` ou du fichier du virtual host
