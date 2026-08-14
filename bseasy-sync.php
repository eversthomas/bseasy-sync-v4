<?php

/**
 * Plugin Name: BSEasy Sync V4
 * Plugin URI: https://bezugssysteme.de
 * Description: Synchronisiert Mitglieder- und Kontaktdaten aus EasyVerein (API v2.0) mit WordPress. Zeigt Mitgliederlisten, interaktive Karten und Kalender im Frontend an.
 * Version: 4.0.1
 * Author: Tom Evers
 * Author URI: https://bezugssysteme.de
 * GitHub Plugin URI: https://github.com/eversthomas/bseasy-sync
 * Text Domain: besync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @license GPL-2.0+
 * @link https://github.com/eversthomas/bseasy-sync GitHub Repository
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Version für Caching
define('BES_VERSION', '4.0.1');
define('BES_TEXT_DOMAIN', 'besync');

/**
 * ------------------------------------------------------------
 *  🔧 BASIS-KONSTANTEN (einheitlich für alle Module)
 * ------------------------------------------------------------
 */
if (!defined('BES_DIR')) {
    define('BES_DIR', plugin_dir_path(__FILE__));
}
if (!defined('BES_URL')) {
    define('BES_URL', plugin_dir_url(__FILE__));
}

// 📁 Uploads-Verzeichnisse & URLs
// Prüfe ob wp_upload_dir() verfügbar ist (kann während Aktivierung fehlen)
if (function_exists('wp_upload_dir')) {
    $upload_dir = wp_upload_dir();
    if (isset($upload_dir['basedir']) && isset($upload_dir['baseurl'])) {
        if (!defined('BES_UPLOADS_DIR')) {
            define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        }
        if (!defined('BES_UPLOADS_URL')) {
            define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
        }
    } else {
        // Fallback wenn wp_upload_dir() fehlerhafte Daten zurückgibt
        if (!defined('BES_UPLOADS_DIR')) {
            define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
        }
        if (!defined('BES_UPLOADS_URL')) {
            define('BES_UPLOADS_URL', (function_exists('content_url') ? content_url('/uploads/bseasy-sync/') : ''));
        }
    }
} else {
    // Fallback für Aktivierungs-Hook oder wenn wp_upload_dir() nicht verfügbar ist
    if (!defined('BES_UPLOADS_DIR')) {
        if (defined('WP_CONTENT_DIR')) {
            define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
        } else {
            define('BES_UPLOADS_DIR', dirname(dirname(dirname(dirname(__FILE__)))) . '/uploads/bseasy-sync/');
        }
    }
    if (!defined('BES_UPLOADS_URL')) {
        if (function_exists('content_url')) {
            define('BES_UPLOADS_URL', content_url('/uploads/bseasy-sync/'));
        } else {
            define('BES_UPLOADS_URL', '');
        }
    }
}

// 📄 Daten- und Bildpfade
if (!defined('BES_DATA')) {
    define('BES_DATA', BES_UPLOADS_DIR);
}
if (!defined('BES_IMG')) {
    define('BES_IMG', trailingslashit(BES_UPLOADS_DIR) . 'img/');
}

// 🔒 Sicherstellen, dass Ordner existieren (mit sicheren Berechtigungen)
// Verwende hosting-kompatible Funktionen (wird nach includes geladen)
// WICHTIG: Nur ausführen wenn WordPress-Funktionen verfügbar sind UND nicht während Aktivierung
// (Verzeichnisse werden in bes_plugin_activate() erstellt)
if (!defined('WP_INSTALLING') && function_exists('wp_mkdir_p') && defined('BES_DATA') && BES_DATA) {
    if (!file_exists(BES_DATA)) {
        if (function_exists('bes_ensure_writable_directory')) {
            bes_ensure_writable_directory(BES_DATA, 0755);
        } else {
            wp_mkdir_p(BES_DATA);
            if (!chmod(BES_DATA, 0755)) {
                // Hosting erlaubt chmod ggf. nicht – kein fataler Fehler
                error_log('BSEasy Sync: chmod fehlgeschlagen für ' . BES_DATA);
            }
        }
    }
    if (defined('BES_IMG') && BES_IMG && !file_exists(BES_IMG)) {
        if (function_exists('bes_ensure_writable_directory')) {
            bes_ensure_writable_directory(BES_IMG, 0755);
        } else {
            wp_mkdir_p(BES_IMG);
            if (!chmod(BES_IMG, 0755)) {
                error_log('BSEasy Sync: chmod fehlgeschlagen für ' . BES_IMG);
            }
        }
    }
}

require_once BES_DIR . 'bootstrap/load.php';

register_activation_hook(__FILE__, 'bes_plugin_activate');
register_deactivation_hook(__FILE__, 'bes_plugin_deactivate');
register_uninstall_hook(__FILE__, 'bes_plugin_uninstall');
