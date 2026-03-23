<?php

/**
 * Plugin Name: BSEasy Sync
 * Plugin URI: https://bezugssysteme.de
 * Description: Synchronisiert Mitglieder- und Kontaktdaten aus EasyVerein (API v2.0) mit WordPress. Zeigt Mitgliederlisten, interaktive Karten und Kalender im Frontend an.
 * Version: 3.0.0
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

if (!defined('ABSPATH')) exit;

// Plugin-Version für Caching
define('BES_VERSION', '3.0.0');
define('BES_TEXT_DOMAIN', 'besync');

/**
 * ------------------------------------------------------------
 *  🔧 BASIS-KONSTANTEN (einheitlich für alle Module)
 * ------------------------------------------------------------
 */
if (!defined('BES_DIR'))  define('BES_DIR', plugin_dir_path(__FILE__));
if (!defined('BES_PATH')) define('BES_PATH', plugin_dir_path(__FILE__));
if (!defined('BES_URL'))  define('BES_URL', plugin_dir_url(__FILE__));

// 📁 Uploads-Verzeichnisse & URLs
// Prüfe ob wp_upload_dir() verfügbar ist (kann während Aktivierung fehlen)
if (function_exists('wp_upload_dir')) {
    $upload_dir = wp_upload_dir();
    if (isset($upload_dir['basedir']) && isset($upload_dir['baseurl'])) {
        if (!defined('BES_UPLOADS_DIR')) define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
        if (!defined('BES_UPLOADS_URL')) define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
    } else {
        // Fallback wenn wp_upload_dir() fehlerhafte Daten zurückgibt
        if (!defined('BES_UPLOADS_DIR')) define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
        if (!defined('BES_UPLOADS_URL')) define('BES_UPLOADS_URL', (function_exists('content_url') ? content_url('/uploads/bseasy-sync/') : ''));
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
if (!defined('BES_DATA')) define('BES_DATA', BES_UPLOADS_DIR);
if (!defined('BES_IMG'))  define('BES_IMG', trailingslashit(BES_UPLOADS_DIR) . 'img/');

// V2-Datenverzeichnis wurde entfernt - nur noch V3 wird verwendet

// 🔒 Sicherstellen, dass Ordner existieren (mit sicheren Berechtigungen)
// Verwende hosting-kompatible Funktionen (wird nach includes geladen)
// WICHTIG: Nur ausführen wenn WordPress-Funktionen verfügbar sind UND nicht während Aktivierung
// (Verzeichnisse werden in bes_plugin_activate() erstellt)
if (!defined('WP_INSTALLING') && function_exists('wp_mkdir_p') && defined('BES_DATA') && BES_DATA) {
    if (!file_exists(BES_DATA)) {
        if (function_exists('bes_ensure_writable_directory')) {
            @bes_ensure_writable_directory(BES_DATA, 0755);
        } else {
            @wp_mkdir_p(BES_DATA);
            @chmod(BES_DATA, 0755);
        }
    }
    if (defined('BES_IMG') && BES_IMG && !file_exists(BES_IMG)) {
        if (function_exists('bes_ensure_writable_directory')) {
            @bes_ensure_writable_directory(BES_IMG, 0755);
        } else {
            @wp_mkdir_p(BES_IMG);
            @chmod(BES_IMG, 0755);
        }
    }
    // V2-Verzeichnis wird nicht mehr erstellt
}

/**
 * ------------------------------------------------------------
 *  🌍 INTERNATIONALIZATION
 * ------------------------------------------------------------
 */
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        BES_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

/**
 * ------------------------------------------------------------
 *  🔧 UTILITIES & CONSTANTS
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'includes/constants.php')) {
    require_once BES_DIR . 'includes/constants.php';
}
if (file_exists(BES_DIR . 'includes/error-handler.php')) {
    require_once BES_DIR . 'includes/error-handler.php';
}
if (file_exists(BES_DIR . 'includes/cache-utils.php')) {
    require_once BES_DIR . 'includes/cache-utils.php';
}

/**
 * ------------------------------------------------------------
 *  📁 VERZEICHNIS-HILFSFUNKTIONEN
 * ------------------------------------------------------------
 */

/**
 * Gibt das V2-Datenverzeichnis zurück (nur für Kompatibilität/Fallback)
 * 
 * @deprecated V2 wird nicht mehr verwendet, nur noch für Fallback
 * @param string $version Optional, ignoriert
 * @return string Pfad zum Verzeichnis
 */
function bes_get_data_dir($version = 'v2') {
    return trailingslashit(BES_UPLOADS_DIR) . 'v2/';
}

/**
 * Lädt eine JSON-Datei aus dem V2-Datenverzeichnis (nur für Kompatibilität/Fallback)
 * 
 * @deprecated V2 wird nicht mehr verwendet, nur noch für Fallback
 * @param string $file Dateiname (z.B. 'members_consent_v2.json')
 * @param string $version Optional, ignoriert
 * @return array|null Array-Daten oder null bei Fehler
 */
function bes_load_json_versioned($file, $version = 'v2') {
    $dir = bes_get_data_dir();
    $path = $dir . $file;
    
    if (!file_exists($path)) {
        return null;
    }
    
    if (function_exists('bes_load_json_from_path')) {
        return bes_load_json_from_path($path);
    }
    
    $content = @file_get_contents($path);
    if ($content === false) {
        return null;
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }
    
    return $data;
}

/**
 * WordPress Error Handler registrieren (nur im Admin-Bereich, nur einmal)
 */
if (!defined('BES_ERROR_HANDLER_REGISTERED')) {
    // Prüfe ob wir im Admin-Bereich sind UND es kein AJAX-Request ist
    if (is_admin() && !wp_doing_ajax() && function_exists('bes_write_debug_log')) {
        // Setze Error Handler für PHP-Fehler
        set_error_handler('bes_wp_error_handler', E_ALL);
        
        // Setze Shutdown Handler für Fatal Errors
        register_shutdown_function('bes_wp_shutdown_handler');
        
        // Markiere als registriert, damit es nicht nochmal passiert
        define('BES_ERROR_HANDLER_REGISTERED', true);
        
        // Logge dass Error Handler registriert wurde
        bes_write_debug_log("WordPress Error Handler registriert", "INFO", "bseasy-sync.php");
    }
}
if (file_exists(BES_DIR . 'includes/filters.php')) {
    require_once BES_DIR . 'includes/filters.php';
}
if (file_exists(BES_DIR . 'includes/hosting-compatibility.php')) {
    require_once BES_DIR . 'includes/hosting-compatibility.php';
}

/**
 * ------------------------------------------------------------
 *  🔩 CORE-FUNKTIONEN & CRON
 * ------------------------------------------------------------
 */
// Lade V3-Konstanten
// Lade V3-Konstanten
if (file_exists(BES_DIR . 'includes/constants-v3.php')) {
    require_once BES_DIR . 'includes/constants-v3.php';
}

// Lade V3-Module (nur im Admin oder bei Cron)
if (is_admin() || wp_doing_cron()) {
    if (file_exists(BES_DIR . 'sync/v3-helpers.php')) {
        require_once BES_DIR . 'sync/v3-helpers.php';
    }
    if (file_exists(BES_DIR . 'sync/api-explorer-v3.php')) {
        require_once BES_DIR . 'sync/api-explorer-v3.php';
    }
    if (file_exists(BES_DIR . 'sync/api-core-consent-v3.php')) {
        require_once BES_DIR . 'sync/api-core-consent-v3.php';
    }
    if (file_exists(BES_DIR . 'sync/cron-v3.php')) {
        require_once BES_DIR . 'sync/cron-v3.php';
    }
    if (file_exists(BES_DIR . 'admin/ajax/ajax-v3.php')) {
        require_once BES_DIR . 'admin/ajax/ajax-v3.php';
    }
    // Lade V3 Consent Audit (nur im Admin)
    if (file_exists(BES_DIR . 'sync/v3-consent-audit.php')) {
        require_once BES_DIR . 'sync/v3-consent-audit.php';
    }
}

if (file_exists(BES_DIR . 'admin/calendar-handler.php')) {
    require_once BES_DIR . 'admin/calendar-handler.php';
}

/**
 * ------------------------------------------------------------
 *  🌐 FRONTEND-RENDERING
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'frontend/renderer.php')) {
    require_once BES_DIR . 'frontend/renderer.php';
}
if (file_exists(BES_DIR . 'frontend/map-render.php')) {
    require_once BES_DIR . 'frontend/map-render.php';
}
if (file_exists(BES_DIR . 'frontend/shortcode.php')) {
    require_once BES_DIR . 'frontend/shortcode.php';
}
if (file_exists(BES_DIR . 'frontend/ajax-endpoints.php')) {
    require_once BES_DIR . 'frontend/ajax-endpoints.php';
}

// Kalender-Modul (calendar-handler.php wurde bereits oben geladen)
if (file_exists(BES_DIR . 'frontend/calendar-render.php')) {
    require_once BES_DIR . 'frontend/calendar-render.php';
}

/**
 * ------------------------------------------------------------
 *  🧩 ADMIN-INTERFACE (Tabs, Sync, Felder, Kalender)
 * ------------------------------------------------------------
 *  UI wird über Callback geladen, um Header-Warnungen zu vermeiden.
 */
add_action('admin_menu', function () {
    add_menu_page(
        __('BSEasy Sync', BES_TEXT_DOMAIN),
        __('BSEasy Sync', BES_TEXT_DOMAIN),
        'manage_options',
        'bseasy-sync',
        'bes_admin_page',
        'dashicons-update-alt',
        80
    );
});

/**
 * ------------------------------------------------------------
 *  🧠 FELDERVERWALTUNG (CustomField-Konfiguration)
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'admin/fields-handler.php')) {
    require_once BES_DIR . 'admin/fields-handler.php';
}
if (file_exists(BES_DIR . 'admin/includes/field-label-generator.php')) {
    require_once BES_DIR . 'admin/includes/field-label-generator.php';
}
if (file_exists(BES_DIR . 'admin/includes/fields-template.php')) {
    require_once BES_DIR . 'admin/includes/fields-template.php';
}
if (file_exists(BES_DIR . 'admin/includes/design-settings.php')) {
    require_once BES_DIR . 'admin/includes/design-settings.php';
}

/**
 * ------------------------------------------------------------
 *  🔁 AJAX-HANDLER: Cache & Debug
 * ------------------------------------------------------------
 */
if (file_exists(BES_DIR . 'admin/ajax/ajax-cache.php')) {
    require_once BES_DIR . 'admin/ajax/ajax-cache.php';
}
if (file_exists(BES_DIR . 'admin/ajax/ajax-debug.php')) {
    require_once BES_DIR . 'admin/ajax/ajax-debug.php';
}

/**
 * ------------------------------------------------------------
 *  🎨 ADMIN-ASSETS: Styles & Scripts
 * ------------------------------------------------------------
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // Debug: Logge Hook-Namen
    if (function_exists('bes_write_debug_log')) {
        bes_write_debug_log("admin_enqueue_scripts Hook aufgerufen - hook: " . $hook, "INFO", "admin_enqueue_scripts");
    }

    // Nur auf der Plugin-Seite laden
    // Hook-Format: {menu_slug} oder {menu_slug}_{submenu_slug}
    if (strpos($hook, 'bseasy-sync') === false) {
        if (function_exists('bes_write_debug_log')) {
            bes_write_debug_log("Hook '" . $hook . "' entspricht nicht 'bseasy-sync' - Scripts werden nicht geladen", "INFO", "admin_enqueue_scripts");
        }
        return;
    }
    
    if (function_exists('bes_write_debug_log')) {
        bes_write_debug_log("Hook '" . $hook . "' entspricht 'bseasy-sync' - Scripts werden geladen", "INFO", "admin_enqueue_scripts");
    }

    /**
     * 🧩 Basis-CSS für das Admin-UI
     */
    wp_enqueue_style(
        'bes-admin-style',
        BES_URL . 'admin/assets/admin.css',
        [],
        BES_VERSION
    );

    /**
     * 🧩 Neue Sidebar-CSS
     */
    wp_enqueue_style(
        'bes-sidebar-style',
        BES_URL . 'admin/assets/bes-sidebar.css',
        [],
        BES_VERSION
    );

    /**
     * 🧩 Felderverwaltung CSS
     */
    wp_enqueue_style(
        'bes-fields-style',
        BES_URL . 'admin/assets/ui-felder.css',
        ['bes-sidebar-style'],
        BES_VERSION
    );

    /**
     * 🧩 Haupt-JS (Admin Tabs, UI etc.)
     * WICHTIG: false = im Header laden (für frühe Verfügbarkeit)
     */
    wp_enqueue_script(
        'bes-admin-script',
        BES_URL . 'admin/assets/ui.js',
        ['jquery'],
        BES_VERSION,
        false  // Im Header laden statt Footer
    );

    /**
     * 🧩 Sortable + Felderverwaltung
     */
    wp_enqueue_script(
        'bes-sortable',
        BES_URL . 'admin/vendor/Sortable.min.js',
        [],
        '1.15',
        true
    );

    /**
     * 🧩 Neue Sidebar-Felderverwaltung (Sidebar, Filter, Suche)
     */
    wp_enqueue_script(
        'bes-fields-sidebar',
        BES_URL . 'admin/assets/ui-felder-sidebar.js',
        ['jquery', 'bes-sortable'],
        BES_VERSION,
        true
    );

    wp_localize_script('bes-fields-sidebar', 'bes_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_felder_nonce')
    ]);

    /**
     * 🔑 AJAX-Variablen für Cache-Verwaltung
     */
    wp_localize_script('bes-admin-script', 'bes_cache_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce')
    ]);

    /**
     * 🔑 AJAX-Variablen für Debug-Logging
     */
    wp_localize_script('bes-admin-script', 'bes_debug_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_debug_nonce')
    ]);

    /**
     * 🔑 AJAX-Variablen für Sync-Operationen (Consent-Sync, Merge, Status)
     */
    wp_localize_script('bes-admin-script', 'bes_sync_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce')
    ]);
    
    /**
     * 🔑 AJAX-Variablen für V3-Operationen (Explorer, V3 Sync)
     */
    wp_localize_script('bes-admin-script', 'bes_v3_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bes_admin_nonce')
    ]);

    // ui-felder.js wurde entfernt - ui-felder-sidebar.js ist die aktuelle Version
    // Die alte Datei wurde gelöscht, da sie doppelte IDs verursachte und nicht mehr benötigt wird
});

/**
 * ------------------------------------------------------------
 *  🧾 ADMIN CALLBACK
 * ------------------------------------------------------------
 */
/**
 * Debug-Log-Funktion für JavaScript-Fehler
 * Schreibt direkt in Datei (ohne AJAX)
 * 
 * @param string $message Die Log-Nachricht
 * @param string $level Log-Level: INFO, ERROR, WARN, DEBUG
 * @param string $context Kontext (z.B. 'ui.js', 'admin')
 * @return bool Erfolg
 */
function bes_write_debug_log($message, $level = 'INFO', $context = 'admin') {
    if (!defined('BES_DIR')) {
        return false;
    }
    
    // Validiere BES_DIR Pfad
    $plugin_dir = realpath(BES_DIR);
    if ($plugin_dir === false) {
        error_log("BES Debug: Ungültiger BES_DIR Pfad");
        return false;
    }
    
    // Prüfe ob Pfad innerhalb WP_PLUGIN_DIR liegt
    $wp_plugin_dir = realpath(WP_PLUGIN_DIR);
    if ($wp_plugin_dir === false || strpos($plugin_dir, $wp_plugin_dir) !== 0) {
        error_log("BES Debug: BES_DIR liegt außerhalb von WP_PLUGIN_DIR");
        return false;
    }
    
    $debug_dir = trailingslashit($plugin_dir) . 'debug/';
    
    // Versuche Verzeichnis zu erstellen mit sicheren Berechtigungen
    if (!file_exists($debug_dir)) {
        if (function_exists('wp_mkdir_p')) {
            @wp_mkdir_p($debug_dir);
        } else {
            @mkdir($debug_dir, 0755, true);
        }
        @chmod($debug_dir, 0755);
    }
    
    // Prüfe ob Verzeichnis jetzt existiert und beschreibbar ist
    if (!file_exists($debug_dir) || !is_writable($debug_dir)) {
        error_log("BES Debug: Verzeichnis nicht beschreibbar: " . $debug_dir);
        return false;
    }
    
    // Validiere Dateiname (kein Path-Traversal)
    $log_file = $debug_dir . 'debug.log';
    if (basename($log_file) !== 'debug.log') {
        error_log("BES Debug: Ungültiger Log-Dateiname");
        return false;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$context}] {$message}\n";
    
    $result = @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($result === false) {
        error_log("BES Debug: Fehler beim Schreiben in: " . $log_file);
        return false;
    }
    
    return true;
}

/**
 * WordPress Error Handler - fängt PHP-Fehler ab und schreibt sie in die Debug-Log-Datei
 * 
 * @param int $errno Fehler-Level
 * @param string $errstr Fehler-Nachricht
 * @param string $errfile Datei, in der der Fehler auftrat
 * @param int $errline Zeile, in der der Fehler auftrat
 * @return bool true wenn Fehler behandelt wurde
 */
function bes_wp_error_handler($errno, $errstr, $errfile, $errline) {
    // Nur Fehler loggen, die nicht unterdrückt werden sollen
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    // Bestimme Fehler-Level
    $level = 'INFO';
    switch ($errno) {
        case E_ERROR:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
        case E_PARSE:
        case E_USER_ERROR:
            $level = 'ERROR';
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $level = 'WARN';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
        case E_STRICT:
            $level = 'INFO';
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $level = 'INFO';
            break;
    }
    
    // Extrahiere Dateinamen aus vollständigem Pfad
    $file_path = defined('ABSPATH') ? str_replace(ABSPATH, '', $errfile) : $errfile;
    
    // Formatiere Fehlermeldung
    $error_message = sprintf(
        "PHP %s: %s in %s:%d",
        $level,
        $errstr,
        $file_path,
        $errline
    );
    
    // Schreibe in Debug-Log
    bes_write_debug_log($error_message, $level, 'PHP_ERROR');
    
    // Lass WordPress den Fehler auch normal behandeln
    return false;
}

/**
 * WordPress Shutdown Handler - fängt Fatal Errors ab
 */
function bes_wp_shutdown_handler() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $file_path = defined('ABSPATH') ? str_replace(ABSPATH, '', $error['file']) : $error['file'];
        bes_write_debug_log(
            sprintf(
                "FATAL ERROR: %s in %s:%d",
                $error['message'],
                $file_path,
                $error['line']
            ),
            'ERROR',
            'PHP_FATAL'
        );
    }
}

function bes_admin_page()
{
    if (!current_user_can('manage_options')) return;
    
    // Debug: Initial-Log beim Seitenaufruf
    bes_write_debug_log("Admin-Seite geladen - bes_admin_page() aufgerufen", "INFO", "bes_admin_page");
    bes_write_debug_log("BES_DIR: " . (defined('BES_DIR') ? BES_DIR : 'NICHT DEFINIERT'), "INFO", "bes_admin_page");
    bes_write_debug_log("PHP Version: " . PHP_VERSION, "INFO", "bes_admin_page");
    bes_write_debug_log("WordPress Version: " . get_bloginfo('version'), "INFO", "bes_admin_page");

    // Nonce-Verifizierung für alle POST-Requests
    if (isset($_POST['bes_token']) || isset($_POST['bes_consent_field_id']) || 
        isset($_POST['bes_batch_size']) || isset($_POST['bes_auto_continue']) || isset($_POST['bes_sync_all_members'])) {
        if (!isset($_POST['bes_admin_nonce']) || !wp_verify_nonce($_POST['bes_admin_nonce'], 'bes_admin_save')) {
            wp_die(esc_html__('Sicherheitsprüfung fehlgeschlagen.', BES_TEXT_DOMAIN));
        }
    }

    // Token speichern (verschlüsselt)
    if (isset($_POST['bes_token'])) {
        $token = sanitize_text_field($_POST['bes_token']);
        // Nur speichern wenn Token nicht leer ist (leer = beibehalten)
        if (!empty($token)) {
            // Verschlüssele Token vor dem Speichern
            if (function_exists('bes_encrypt_token')) {
                $encrypted_token = bes_encrypt_token($token);
                update_option('bes_api_token', $encrypted_token);
                echo '<div class="updated"><p>' . esc_html__('Token gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
            } else {
                // Fallback: Unverschlüsselt speichern (für Migration)
                update_option('bes_api_token', $token);
                echo '<div class="updated"><p>' . esc_html__('Token gespeichert (unverschlüsselt - Migration).', BES_TEXT_DOMAIN) . '</p></div>';
            }
        }
    }

    // Consent-Feld-ID speichern mit Validierung
    if (isset($_POST['bes_consent_field_id'])) {
        $consent_id = intval($_POST['bes_consent_field_id']);
        // Validierung: zwischen 1 und 999999999 (sinnvolle Obergrenze)
        if ($consent_id > 0 && $consent_id <= BES_CONSENT_FIELD_ID_MAX) {
            update_option('bes_consent_field_id', $consent_id);
            echo '<div class="updated"><p>' . esc_html__('Consent-Feld-ID gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . sprintf(
                esc_html__('Ungültige Consent-Feld-ID. Bitte einen Wert zwischen %d und %d eingeben.', BES_TEXT_DOMAIN),
                BES_CONSENT_FIELD_ID_MIN,
                BES_CONSENT_FIELD_ID_MAX
            ) . '</p></div>';
        }
    }

    // Batch-Größe speichern
    if (isset($_POST['bes_batch_size'])) {
        $batch_size = intval($_POST['bes_batch_size']);
        if ($batch_size >= BES_BATCH_SIZE_MIN && $batch_size <= BES_BATCH_SIZE_MAX) {
            update_option('bes_batch_size', $batch_size);
            echo '<div class="updated"><p>' . esc_html__('Batch-Größe gespeichert.', BES_TEXT_DOMAIN) . '</p></div>';
        } else {
            echo '<div class="error"><p>' . sprintf(
                esc_html__('Batch-Größe muss zwischen %d und %d liegen.', BES_TEXT_DOMAIN),
                BES_BATCH_SIZE_MIN,
                BES_BATCH_SIZE_MAX
            ) . '</p></div>';
        }
    }

    // V2-Sync-Methode wurde entfernt - nur noch V3 wird verwendet

    // Automatische Fortsetzung speichern
    if (isset($_POST['bes_auto_continue'])) {
        update_option('bes_auto_continue', true);
    } else {
        update_option('bes_auto_continue', false);
    }

    // Sync-alle-Mitglieder-Option speichern
    if (isset($_POST['bes_sync_all_members'])) {
        update_option('bes_sync_all_members', true);
        echo '<div class="updated"><p>' . esc_html__('Sync-Modus gespeichert: Alle Mitglieder werden synchronisiert (ohne Consent-Filter).', BES_TEXT_DOMAIN) . '</p></div>';
    } else {
        update_option('bes_sync_all_members', false);
        echo '<div class="updated"><p>' . esc_html__('Sync-Modus gespeichert: Nur Mitglieder mit Consent werden synchronisiert.', BES_TEXT_DOMAIN) . '</p></div>';
    }
    
    // Prüfe und bereinige abgebrochene Syncs beim Speichern (verhindert falsche "running"-Anzeige)
    if (isset($_POST['bes_sync_all_members']) || isset($_POST['bes_consent_field_id'])) {
        if (defined('BES_DATA_V3') && defined('BES_V3_STATUS_FILE')) {
            $status_file = BES_DATA_V3 . BES_V3_STATUS_FILE;
            if (file_exists($status_file)) {
                $status_data = json_decode(file_get_contents($status_file), true);
                if (is_array($status_data) && isset($status_data['state']) && $status_data['state'] === 'cancelled') {
                    // Setze Status auf idle und explorer_running auf false
                    $status_data['state'] = 'idle';
                    $status_data['progress'] = 0;
                    $status_data['message'] = '';
                    file_put_contents($status_file, json_encode($status_data, JSON_PRETTY_PRINT));
                    
                    if (defined('BES_V3_OPTION_PREFIX')) {
                        update_option(BES_V3_OPTION_PREFIX . 'explorer_running', false);
                    }
                }
            }
        }
    }

    // Admin-UI laden
    require_once BES_DIR . 'admin/ui-main.php';
}

/**
 * ------------------------------------------------------------
 *  🧹 PLUGIN-DEAKTIVIERUNG: CLEANUP
 * ------------------------------------------------------------
 */
/**
 * ------------------------------------------------------------
 *  🔌 PLUGIN HOOKS
 * ------------------------------------------------------------
 */

/**
 * Plugin-Aktivierung
 */
register_activation_hook(__FILE__, 'bes_plugin_activate');
function bes_plugin_activate() {
    // Speichere aktuellen Error-Reporting-Level
    $error_level = error_reporting();
    
    // Unterdrücke nur Warnungen und Notices während der Aktivierung
    // Aber behalte Error-Reporting für kritische Fehler
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
    
    // KEIN Output-Buffering-Manipulation mehr!
    // WordPress verwaltet das selbst - wir sollten es nicht stören
    
    // Stelle sicher, dass Konstanten definiert sind
    if (!defined('BES_DATA')) {
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            if (isset($upload_dir['basedir']) && isset($upload_dir['baseurl'])) {
                define('BES_UPLOADS_DIR', trailingslashit($upload_dir['basedir']) . 'bseasy-sync/');
                define('BES_UPLOADS_URL', trailingslashit($upload_dir['baseurl']) . 'bseasy-sync/');
            } else {
                define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
                define('BES_UPLOADS_URL', (function_exists('content_url') ? content_url('/uploads/bseasy-sync/') : ''));
            }
        } else {
            define('BES_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads/bseasy-sync/');
            define('BES_UPLOADS_URL', '');
        }
        define('BES_DATA', BES_UPLOADS_DIR);
        define('BES_IMG', trailingslashit(BES_UPLOADS_DIR) . 'img/');
    }
    
    // Stelle sicher, dass Standard-Konstanten definiert sind
    if (!defined('BES_BATCH_SIZE_DEFAULT')) {
        define('BES_BATCH_SIZE_DEFAULT', 200);
    }
    if (!defined('BES_CONSENT_FIELD_ID_DEFAULT')) {
        define('BES_CONSENT_FIELD_ID_DEFAULT', 282018660);
    }
    
    // Erstelle notwendige Verzeichnisse
    if (function_exists('wp_mkdir_p') && defined('BES_DATA') && BES_DATA) {
        if (!file_exists(BES_DATA)) {
            wp_mkdir_p(BES_DATA);
            @chmod(BES_DATA, 0755);
        }
        if (defined('BES_IMG') && BES_IMG && !file_exists(BES_IMG)) {
            wp_mkdir_p(BES_IMG);
            @chmod(BES_IMG, 0755);
        }
        // V2-Verzeichnisse werden nicht mehr erstellt (nur noch V3)
    }
    
    // Setze Standard-Optionen (autoload=false für Performance)
    if (!get_option('bes_batch_size')) {
        update_option('bes_batch_size', BES_BATCH_SIZE_DEFAULT, false);
    }
    if (!get_option('bes_consent_field_id')) {
        update_option('bes_consent_field_id', BES_CONSENT_FIELD_ID_DEFAULT, false);
    }
    
    // Flush Rewrite Rules falls nötig
    flush_rewrite_rules(false);
    
    // Migration zu getrennten Verzeichnissen (einmalig)
    bes_migrate_to_versioned_dirs();
    
    // Stelle Error-Reporting wieder her
    error_reporting($error_level);
}

/**
 * Plugin-Deaktivierung
 */
register_deactivation_hook(__FILE__, 'bes_plugin_deactivate');
function bes_plugin_deactivate() {
    // Legacy-Cleanup: Lösche alte V2-Status-Dateien (falls noch vorhanden)
    // V2 wurde entfernt, aber alte Dateien könnten noch existieren
    $status_file = BES_DATA . 'status.json';
    if (file_exists($status_file)) {
        @unlink($status_file);
    }
    
    // Lösche Render-Cache
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }
    
    // Lösche temporäre Part-Dateien (optional - nur wenn gewünscht)
    // $part_files = glob(BES_DATA . 'members_consent_part*.json');
}

/**
 * Migration zu V2-Verzeichnis
 * Verschiebt bestehende V2-Dateien ins v2/ Verzeichnis (falls noch nicht geschehen)
 */
function bes_migrate_to_versioned_dirs() {
    // Nur einmalig ausführen
    if (get_option('bes_versioned_dirs_migration_completed', false)) {
        return;
    }
    
    // Stelle sicher, dass V2-Verzeichnis existiert
    if (defined('BES_DATA_V2') && !file_exists(BES_DATA_V2)) {
        wp_mkdir_p(BES_DATA_V2);
        @chmod(BES_DATA_V2, 0755);
    }
    
    $migration_performed = false;
    
    // Verschiebe V2-Dateien (members_consent_v2*.json) ins v2/ Verzeichnis
    $v2_files = glob(BES_DATA . 'members_consent_v2*.json');
    if ($v2_files !== false) {
        foreach ($v2_files as $file) {
            $filename = basename($file);
            $new_path = BES_DATA_V2 . $filename;
            if (file_exists($file) && !file_exists($new_path)) {
                if (@rename($file, $new_path)) {
                    $migration_performed = true;
                }
            }
        }
    }
    
    if ($migration_performed) {
        update_option('bes_versioned_dirs_migration_completed', true, false);
    }
}

/**
 * Plugin-Deinstallation (nur wenn explizit gelöscht)
 */
register_uninstall_hook(__FILE__, 'bes_plugin_uninstall');
function bes_plugin_uninstall() {
    // Lösche alle Plugin-Optionen
    delete_option('bes_api_token');
    delete_option('bes_consent_field_id');
    delete_option('bes_batch_size');
    delete_option('bes_auto_continue');
    delete_option('bes_last_sync_time');
    delete_option('bes_last_sync_members_with_consent');
    delete_option('bes_last_sync_members_total');
    delete_option('bes_total_members');
    delete_option('bes_current_part');
    delete_option('bes_last_error');
    delete_option('bes_calendars');
    delete_option('bes_map_enabled');
    delete_option('bes_map_style');
    delete_option('bes_map_zoom');
    delete_option('bes_map_center_lat');
    delete_option('bes_map_center_lng');
    delete_option('bes_migration_completed');
    
    // Lösche alle Transients
    if (function_exists('bes_clear_render_cache')) {
        bes_clear_render_cache();
    }
    
    // WICHTIG: Lösche KEINE Daten-Dateien (members_consent.json, etc.)
    // Diese sollen erhalten bleiben, falls Plugin wieder installiert wird
}