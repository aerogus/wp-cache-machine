<?php

/*
Plugin Name: WP Cache Machine
Plugin URI:  https://github.com/aerogus/wp-cache-machine
Description: Système de cache statique HTML pour WordPress
Version:     1.0.0
Author:      Guillaume SEZNEC <guillaume@seznec.fr>
Author URI:  https://aerogus.net
License:     AGPL 3.0
*/

namespace WPCacheMachine;

// hooks d'installation et désinstallation
register_activation_hook(__FILE__, __NAMESPACE__ . '\activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivate');
register_uninstall_hook(__FILE__, __NAMESPACE__ . '\uninstall');

// admin
add_action('admin_menu', __NAMESPACE__ . '\admin_menu');
add_action('wp_before_admin_bar_render', __NAMESPACE__ . '\admin_bar_custom', 999);

// regénération du cache
add_action('transition_post_status', __NAMESPACE__ . '\delete_post_cache', 10, 3); // $new_status, $old_status, $post

/**
 * Ajout menu dans l'admin + permissions
 */
function admin_menu()
{
    // Seuls ces rôles peuvent utiliser le plugin
    foreach (['administrator', 'editor'] as $user) {
        $role = get_role($user);
        if (!$role->has_cap('wp_cache_machine_capability')) {
            $role->add_cap('wp_cache_machine_capability');
        }
    }

    add_management_page('Cache Machine', 'Cache Machine', 'wp_cache_machine_capability', 'cache-machine', __NAMESPACE__ . '\admin_page');
}

/**
 * Ajoute un raccourci pour vider le cache rapidement dans l'admin
 */
function admin_bar_custom()
{
    global $wp_admin_bar;
    $wp_admin_bar->add_menu([
        'id' => 'wp-cache-machine',
        'title' => 'Vide le cache',
        'href' => admin_url('tools.php?page=cache-machine&clear-all=1'),
    ]);
}

/**
 *
 */
function admin_page()
{
    $cleared = false;
    if (!empty($_GET['clear-all'])) {
        delete_cache_domain();
        $cleared = true;
    }
?>
<h2>WP Cache Machine</h2>
<a href="?page=cache-machine&amp;clear-all=1">Vider tout le cache</a>
<?php if ($cleared) : echo '<p>cache vidé</p>'; endif; ?>
<h3>Liste des fichiers en cache</h3>
<h3>Règles à mettre dans le .htaccess</h3>
<pre style="background: #c0c0c0; padding: 10px;"><?php echo htmlentities(getHtaccessRules()); ?></pre>
<?php
}

/**
 * Activation du plugin
 */
function activate()
{
    write_log('debug', 'activate plugin');

    // création du répertoire de base
    if (!file_exists(getBasePath())) {
        if (mkdir(getBasePath())) {
            write_log('debug', getBasePath() . ' created');
        } else {
            write_log('error', ABSPATH . ' is readonly, unable to create ' . getBasePath());
        }
    }
}

/**
 * Désactivation du plugin
 */
function deactivate()
{
    write_log('debug', 'deactivate plugin');

    // vidage des répertoires de cache
    delete_cache_domain();
}

/**
 * Désinstallation du plugin
 */
function uninstall()
{
    write_log('debug', 'uninstall plugin');

    // vidage des répertoires de cache
    delete_cache_domain();

    rrmdir(getBasePath());
}

/**
 * Retourne le répertoire de base de stockage du cache
 * (à créer manuellement avec droits d'écriture pour le user du serveur http)
 *
 * @return string
 */
function getBasePath()
{
    return ABSPATH . '/../cache';
}

/**
 * Retourne le nom du thème actif
 */
function getCurrentTheme()
{
    return wp_get_theme()->template;
}

/**
 * Retourne un tableau des slugs des thèmes actifs
 *
 * @return array
 */
function getThemes()
{
    $themes = [];
    $wp_themes = wp_get_themes();
    foreach ($wp_themes as $wp_theme) {
        $themes[] = $wp_theme->template;
    }
    return $themes;
}

/**
 * Retourne les protocoles actifs
 *
 * @return array
 */
function getSchemes()
{
    return [
        'http',
        'https',
    ];
}

/**
 * Retourne les règles à ajouter au fichier .htaccess de la racine pour que le système de cache fonctionne
 *
 * Cache actif seulement si :
 * - Méthode HTTP = GET
 * - pas de paramètre dans l'uri
 * - pas de cookie d'identification
 * - protocole http ou https
 *
 * @TODO écrire ces mêmes règles pour NGinx ?
 *
 * @return string
 */
function getHtaccessRules()
{
    return "### BEGIN WP Cache Machine ###
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
# https
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} !.*=.*
RewriteCond %{HTTP:Cookie} !^.*(wordpress_logged_in_).*$
RewriteCond %{HTTP:X-Wp-Cache-Machine-Bypass} !on
RewriteCond %{HTTPS} on [OR]
RewriteCond %{HTTP:X-Forwarded-Proto} https
RewriteCond %{DOCUMENT_ROOT}/cache/" . getCurrentTheme() . "s/%{REQUEST_URI}index.html -f
RewriteRule ^(.*) \"/cache/" . getCurrentTheme() . "s/%{REQUEST_URI}index.html\" [L]
# http
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} !.*=.*
RewriteCond %{HTTP:Cookie} !^.*(wordpress_logged_in_).*$
RewriteCond %{HTTP:X-Wp-Cache-Machine-Bypass} !on
RewriteCond %{HTTPS} !on
RewriteCond %{HTTP:X-Forwarded-Proto} !https
RewriteCond %{DOCUMENT_ROOT}/cache/" . getCurrentTheme() . "/%{REQUEST_URI}index.html -f
RewriteRule ^(.*) \"/cache/" . getCurrentTheme() . "/%{REQUEST_URI}index.html\" [L]
</IfModule>
### END WP Cache Machine ###";
}

/**
 * Routine d'effacement d'un post
 * marche aussi sur les cpt
 *
 * TODO: bug si post inexistant: utilisation de wp_insert_post par exemple
 */
function delete_post_cache($new_status, $old_status, $post)
{
    if ($new_status === 'publish' || $old_status === 'publish')
    {
        write_log('debug', 'delete_post_cache (' . $old_status . '=>' . $new_status . ') ' . get_permalink($post->ID));

        if (!function_exists('get_sample_permalink')) {
            return false;
        }

        // Bug get_permalink($post->ID) si post_status = draft
        list ($permalink, $postname) = get_sample_permalink($post->ID);
        $url = str_replace(
            ['%postname%', '%pagename%'],
            [$postname, $postname],
            $permalink
        );

        write_log('debug', 'delete_post_cache ' . $url);

        // Suppression de la page
        delete_cache_file($url);

        // Suppression des caches des taxonomies reliées à l'article
        delete_post_terms_archive_cache($post);

        // Suppression cache de la home
        delete_cache_home();
    }
}

/**
 * Efface le cache d'un fichier à partir de l'url
 *
 * @param string $url
 */
function delete_cache_file($url)
{
    write_log('debug', 'delete_cache_file ' . $url);

    foreach (getThemes() as $theme) {
        foreach (getSchemes() as $scheme) {
            $path = getPath($theme, $scheme);
            $uri = str_replace([home_url('/', 'http'), home_url('/', 'https')], '', $url);
            rrmdir($path . '/' . $uri);
        }
    }
}

/**
 * Suppression de plusieurs dossiers
 *
 * @param array $urls
 */
function delete_cache_files($urls)
{
    foreach ($urls as $url) {
        delete_cache_file($url);
    }
}

/**
 * On efface les pages d'archives liées à l'article
 *
 * @param object $post
 */
function delete_post_terms_archive_cache($post)
{
    $urls = [];

    $taxonomies = get_object_taxonomies($post);

    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($post->ID, $taxonomy);
        if (!empty($terms)) {
            foreach ($terms as $term) {
                $urls[] = get_term_link($term->slug, $taxonomy);
            }
        }
    }

    delete_cache_files($urls);
}

/**
 * Suppression du cache de la home
 */
function delete_cache_home()
{
    foreach (getThemes() as $theme) {
        foreach (getSchemes() as $scheme) {
            write_log('debug', 'delete_cache_home ' . $theme . ' ' . $scheme);
            $path = getPath($theme, $scheme) . '/index.html';
            if (file_exists($path)) {
                if (unlink($path)) {
                    write_log('debug', 'del index.html OK');
                } else {
                    write_log('error', 'del index.html KO');
                }
            }
        }
    }
}

/**
 * Efface l'ensemble des fichiers de cache
 */
function delete_cache_domain()
{
    foreach (getThemes() as $theme) {
        foreach (getSchemes() as $scheme) {
            write_log('debug', 'delete_cache_domain ' . $theme . ' ' . $scheme);
            rrmdir(getPath($theme, $scheme));
        }
    }
}

/**
 * Retourne le répertoire de stockage pour un thème et un protocole donné
 *
 * @param string $theme (twentyeleven|...)
 * @param string $scheme (http|https)
 * @return string
 */
function getPath($theme, $scheme)
{
    if (!in_array($theme, getThemes())) {
        throw new Exception("unknown theme", 404);
    }

    if (!in_array($scheme, getSchemes())) {
        throw new Exception("unknown scheme", 404);
    }

    $path = getBasePath() . '/' . $theme;
    if ($scheme === 'https') {
        $path .= 's';
    }

    return $path;
}

/**
 * Efface récursivement un répertoire
 *
 * @param string $dir
 */
function rrmdir($dir)
{
    write_log('debug', 'rrmdir ' . $dir);

    if (!is_dir($dir)) {
        @unlink($dir);
        return;
    }

    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            rrmdir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

/**
 * Écriture dans la log
 *
 * @param string $type
 * @param string $message
 */
function write_log($type, $message)
{
    switch ($type) {
        case 'debug':
            if (WP_DEBUG === true) {
                error_log('cache-machine debug : ' . $message);
            }
            break;
        case 'error':
            error_log('cache-machine error : ' . $message);
            break;
    }
}
