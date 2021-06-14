<?php

/**
 * WP Cache Machine
 * version: 1.0.0
 */

ini_set('date.timezone', 'UTC');
setlocale(LC_ALL, 'fr_FR.UTF-8');
ini_set('default_charset', 'UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('error_log', dirname(__FILE__) . '/../log/debug.log');

define('WP_CACHE_MACHINE_DEBUG', false);
define('WP_USE_THEMES', true);

/**
 * Écriture dans la log
 *
 * @param string $type debug|error
 * @param string $message
 */
function write_log($type, $message)
{
    switch ($type) {
        case 'debug':
            if (WP_CACHE_MACHINE_DEBUG === true) {
                error_log('wp-cache-machine debug : ' . $message);
            }
            break;
        case 'error':
            error_log('wp-cache-machine error : ' . $message);
            break;
    }
}

// = thème actif, à adapter
define('WP_CACHE_MACHINE_THEME', 'rock');

// = Aerogus\CacheMachine\getBasePath()
define('WP_CACHE_MACHINE_BASEPATH', dirname(__FILE__) . '/cache');

// on ne met en cache que sous certaines conditions
if (($_SERVER['REQUEST_METHOD'] === 'GET') // uniquement requête GET
    && empty($_GET) // pas de paramètres
    && isset($_SERVER['HTTP_USER_AGENT']) // UA obligatoire
    && empty($_SERVER['HTTP_X_WP_CACHE_MACHINE_BYPASS']) // bypass par header HTTP
    && (strpos($_SERVER['REQUEST_URI'], '?') === false) // vraiment pas de paramètres
    && (strpos($_SERVER['REQUEST_URI'], '/feed/') === false) // pas de cache des flux rss
    && (strpos($_SERVER['REQUEST_URI'], '/api/') === false) // pas de cache de l'api
    && (strpos($_SERVER['REQUEST_URI'], '/inscription/') === false) // pas de cache du formulaire d'inscription
    && (strpos($_SERVER['REQUEST_URI'], '/login/') === false) // pas de cache du formulaire de login
    && (strpos($_SERVER['REQUEST_URI'], '/facebook-login-callback/') === false) // pas de cache du callback FB
    && (strpos($_SERVER['REQUEST_URI'], '/contact/') === false) // pas de cache du formulaire de contact
    && (strpos($_SERVER['REQUEST_URI'], 'sitemap') === false) // pas de cache des sitemaps
    && (strpos($_SERVER['REQUEST_URI'], 'robots.txt') === false) // pas de cache du robots.txt
    && !preg_match('/(wordpress_logged_in)/', var_export($_COOKIE, true)) // pas de cache si identifié
) {

    // protocole http ou https ?
    $is_ssl = (bool) (array_key_exists('HTTPS', $_SERVER) && ($_SERVER['HTTPS'] === 'on'))
                  || (array_key_exists('HTTP_X_FORWARDED_PROTO', $_SERVER) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));

    $scheme = 'http' . ($is_ssl ? 's' : '');
    $cache_path = WP_CACHE_MACHINE_BASEPATH . '/' . WP_CACHE_MACHINE_THEME . ($is_ssl ? 's' : '');
    $filename = 'index.html';

    // le fichier de cache n'existe pas, créons le !
    if (!file_exists($cache_path . '/' . $_SERVER['REQUEST_URI'] . $filename)) {

        if (false && !is_writable($cache_path . '/' . $_SERVER['REQUEST_URI'])) {
            // la page ne doit pas être cachée
            write_log('debug', $_SERVER['REQUEST_URI'] . ' non cachable');
        } else {

            // fetch de la page
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $html = curl_exec($ch);

            if ($html !== false) {

                $info = curl_getinfo($ch);
                curl_close($ch);

                // mise en cache que si le code réponse HTTP est 200
                if ($info['http_code'] === 200) {

                    // créer répertoire de cache (même de 1er niveau) avant avec les bons droits
                    $dirname = $cache_path . '/' . $_SERVER['REQUEST_URI'];
                    if (!file_exists($dirname)) {
                        if (mkdir($dirname, 0755, true)) {
                            write_log('debug', 'mkdir ' . $dirname);
                        } else {
                            write_log('error', 'mkdir ' . $dirname . ' KO');
                        }
                    }

                    // marqueur informatif
                    $html .= "\n" . '<!-- WP Cache Machine|' . $scheme . '|' . $_SERVER['REQUEST_URI'] . '|' . date('Y-m-d H:i:s') . ' -->';

                    // écriture du fichier de cache
                    if (is_writable($dirname) && file_put_contents($dirname . '/' . $filename, $html)) {
                        write_log('debug', 'write ' . $_SERVER['REQUEST_URI']);
                        readfile($cache_path . '/' . $_SERVER['REQUEST_URI'] . '/' . $filename);
                        exit;
                    } else {
                        write_log('error', 'write ' . $_SERVER['REQUEST_URI'] . ' KO');
                    }

                } else {
                    // pas très grave si 404 ou 301
                    write_log('debug', 'fetch ' . $_SERVER['REQUEST_URI'] . ' KO (not HTTP 200: ' . $info['http_code'] . ')');
                }

            } else {
                write_log('error', 'fetch ' . $_SERVER['REQUEST_URI'] . ' KO');
            }

        }

    }
}

require dirname(__FILE__) . '/wp/wp-blog-header.php';
exit;
