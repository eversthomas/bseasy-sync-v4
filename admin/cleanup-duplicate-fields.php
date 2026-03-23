<?php
/**
 * Bereinigt Doppelungen in fields-config.json
 * 
 * Entfernt cfraw.* Einträge, wenn cf.* existiert
 * Entfernt contactcfraw.* Einträge, wenn contactcf.* existiert
 * 
 * Führt Konfigurationen von cfraw auf cf über, falls cf noch keine Konfiguration hat
 */

// Definiere Konstanten falls nicht vorhanden (für direkten Aufruf)
if (!defined('WP_CONTENT_DIR')) {
    // Versuche WP_CONTENT_DIR zu finden
    $plugin_dir = dirname(__FILE__);
    // Plugin liegt in: wp-content/plugins/bseasy-sync/admin/
    // Also: 3 Ebenen nach oben = wp-content/
    $wp_content_dir = dirname(dirname(dirname($plugin_dir)));
    define('WP_CONTENT_DIR', $wp_content_dir);
}

if (!defined('BES_DATA')) {
    define(
        'BES_DATA',
        rtrim(WP_CONTENT_DIR, '/') . '/uploads/bseasy-sync/'
    );
}

// Lade fields-config.json
$config_file = BES_DATA . 'fields-config.json';

if (!file_exists($config_file)) {
    echo "❌ fields-config.json nicht gefunden.\n";
    exit(1);
}

$config = json_decode(file_get_contents($config_file), true);

if (!is_array($config)) {
    echo "❌ fields-config.json ist ungültig.\n";
    exit(1);
}

echo "📋 Bereinige fields-config.json...\n";
echo "   Gefundene Felder: " . count($config) . "\n\n";

$removed = [];
$migrated = [];
$kept = [];

// Sammle alle cf.* IDs
$cf_ids = [];
foreach ($config as $id => $field) {
    if (strpos($id, 'cf.') === 0) {
        $field_id = substr($id, 3);
        $cf_ids[$field_id] = $id;
    }
}

// Sammle alle contactcf.* IDs
$contactcf_ids = [];
foreach ($config as $id => $field) {
    if (strpos($id, 'contactcf.') === 0) {
        $field_id = substr($id, 10);
        $contactcf_ids[$field_id] = $id;
    }
}

// Entferne cfraw.* wenn cf.* existiert
foreach ($config as $id => $field) {
    if (strpos($id, 'cfraw.') === 0) {
        $field_id = substr($id, 6);
        
        if (isset($cf_ids[$field_id])) {
            $cf_id = $cf_ids[$field_id];
            
            // Prüfe ob cf.* noch keine Konfiguration hat (nur Defaults)
            $cf_field = $config[$cf_id];
            $has_config = false;
            
            // Prüfe ob cf.* mehr als nur id, type, example hat
            $cf_keys = array_keys($cf_field);
            $default_keys = ['id', 'type', 'example'];
            $config_keys = array_diff($cf_keys, $default_keys);
            
            if (empty($config_keys)) {
                // cf.* hat keine Konfiguration - migriere von cfraw
                $cfraw_field = $field;
                unset($cfraw_field['id']); // id wird überschrieben
                unset($cfraw_field['type']); // type bleibt 'cf'
                unset($cfraw_field['example']); // example kommt aus Scan
                
                // Übernehme alle Konfigurationswerte
                foreach ($cfraw_field as $key => $value) {
                    if ($key !== 'id' && $key !== 'type' && $key !== 'example') {
                        $config[$cf_id][$key] = $value;
                    }
                }
                
                $migrated[] = [
                    'from' => $id,
                    'to' => $cf_id,
                    'keys' => array_keys($cfraw_field)
                ];
            } else {
                // cf.* hat bereits Konfiguration - nur entfernen
                $kept[] = [
                    'cfraw' => $id,
                    'cf' => $cf_id,
                    'reason' => 'cf.* hat bereits Konfiguration'
                ];
            }
            
            // Entferne cfraw.*
            unset($config[$id]);
            $removed[] = $id;
        }
    }
}

// Entferne contactcfraw.* wenn contactcf.* existiert
foreach ($config as $id => $field) {
    if (strpos($id, 'contactcfraw.') === 0) {
        $field_id = substr($id, 13);
        
        if (isset($contactcf_ids[$field_id])) {
            $contactcf_id = $contactcf_ids[$field_id];
            
            // Prüfe ob contactcf.* noch keine Konfiguration hat (nur Defaults)
            $contactcf_field = $config[$contactcf_id];
            $has_config = false;
            
            // Prüfe ob contactcf.* mehr als nur id, type, example hat
            $contactcf_keys = array_keys($contactcf_field);
            $default_keys = ['id', 'type', 'example'];
            $config_keys = array_diff($contactcf_keys, $default_keys);
            
            if (empty($config_keys)) {
                // contactcf.* hat keine Konfiguration - migriere von contactcfraw
                $contactcfraw_field = $field;
                unset($contactcfraw_field['id']); // id wird überschrieben
                unset($contactcfraw_field['type']); // type bleibt 'contactcf'
                unset($contactcfraw_field['example']); // example kommt aus Scan
                
                // Übernehme alle Konfigurationswerte
                foreach ($contactcfraw_field as $key => $value) {
                    if ($key !== 'id' && $key !== 'type' && $key !== 'example') {
                        $config[$contactcf_id][$key] = $value;
                    }
                }
                
                $migrated[] = [
                    'from' => $id,
                    'to' => $contactcf_id,
                    'keys' => array_keys($contactcfraw_field)
                ];
            } else {
                // contactcf.* hat bereits Konfiguration - nur entfernen
                $kept[] = [
                    'contactcfraw' => $id,
                    'contactcf' => $contactcf_id,
                    'reason' => 'contactcf.* hat bereits Konfiguration'
                ];
            }
            
            // Entferne contactcfraw.*
            unset($config[$id]);
            $removed[] = $id;
        }
    }
}

// Speichere bereinigte Config
$backup_file = $config_file . '.backup.' . date('Y-m-d_His');
copy($config_file, $backup_file);
echo "💾 Backup erstellt: " . basename($backup_file) . "\n\n";

file_put_contents(
    $config_file,
    json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// Ausgabe
echo "✅ Bereinigung abgeschlossen!\n\n";
echo "📊 Statistiken:\n";
echo "   Entfernte Felder: " . count($removed) . "\n";
echo "   Migrierte Konfigurationen: " . count($migrated) . "\n";
echo "   Übersprungene (bereits konfiguriert): " . count($kept) . "\n";
echo "   Verbleibende Felder: " . count($config) . "\n\n";

if (!empty($removed)) {
    echo "🗑️  Entfernte Felder:\n";
    foreach ($removed as $id) {
        echo "   - $id\n";
    }
    echo "\n";
}

if (!empty($migrated)) {
    echo "🔄 Migrierte Konfigurationen:\n";
    foreach ($migrated as $m) {
        echo "   - {$m['from']} → {$m['to']}\n";
        echo "     Keys: " . implode(', ', $m['keys']) . "\n";
    }
    echo "\n";
}

if (!empty($kept)) {
    echo "⏭️  Übersprungene (cf.*/contactcf.* hatte bereits Konfiguration):\n";
    foreach ($kept as $k) {
        $key = isset($k['cfraw']) ? 'cfraw' : 'contactcfraw';
        $target = isset($k['cf']) ? $k['cf'] : $k['contactcf'];
        echo "   - {$k[$key]} (→ $target)\n";
    }
    echo "\n";
}

echo "✅ fields-config.json wurde bereinigt!\n";

