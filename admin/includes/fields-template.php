<?php
/**
 * BSEasy Sync - Fields Config Template System
 * 
 * Verwaltet Template-Konfigurationen für neue Installationen
 * 
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 * @since 3.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * Lädt die Template-Konfiguration aus dem Plugin-Verzeichnis
 * 
 * @return array Template-Konfiguration oder leeres Array
 */
function bes_load_fields_template(): array
{
    $template_file = BES_DIR . 'admin/templates/fields-config-default.json';
    
    if (!file_exists($template_file)) {
        return [];
    }
    
    $raw = file_get_contents($template_file);
    if ($raw === false || $raw === '') {
        return [];
    }
    
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    
    // Alte Struktur? (Array von Objekten mit id)
    if (isset($data[0]) && is_array($data[0]) && isset($data[0]['id'])) {
        $migrated = [];
        foreach ($data as $item) {
            if (!isset($item['id'])) continue;
            $id = (string)$item['id'];
            $migrated[$id] = $item;
        }
        return $migrated;
    }
    
    // Neue Struktur: Objekt (assoziatives Array)
    return $data;
}

/**
 * Prüft ob eine Config-Datei existiert
 * 
 * @return bool True wenn Config existiert
 */
function bes_fields_config_exists(): bool
{
    $config_file = BES_DATA . 'fields-config.json';
    return file_exists($config_file) && filesize($config_file) > 0;
}

/**
 * Prüft ob ein Template existiert
 * 
 * @return bool True wenn Template existiert
 */
function bes_fields_template_exists(): bool
{
    $template_file = BES_DIR . 'admin/templates/fields-config-default.json';
    return file_exists($template_file) && filesize($template_file) > 0;
}

/**
 * Initialisiert die Config mit Template bei neuer Installation
 * 
 * @return bool Erfolg
 */
function bes_init_fields_config_from_template(): bool
{
    // Nur wenn noch keine Config existiert
    if (bes_fields_config_exists()) {
        return false;
    }
    
    $template = bes_load_fields_template();
    
    if (empty($template)) {
        // Kein Template vorhanden, leere Config erstellen
        bes_save_json('fields-config.json', []);
        return false;
    }
    
    // Template als Config speichern
    return bes_save_json('fields-config.json', $template);
}

/**
 * Exportiert die aktuelle Config als Template
 * 
 * @param bool $force Überschreibt vorhandenes Template ohne Warnung
 * @return bool|array Erfolg (true) oder Array mit Warnung ['warning' => true, 'message' => '...']
 */
function bes_export_config_as_template(bool $force = false)
{
    $config = bes_load_fields_config();
    
    if (empty($config)) {
        return false;
    }
    
    $template_dir = BES_DIR . 'admin/templates/';
    
    // Template-Verzeichnis erstellen falls nicht vorhanden
    if (!is_dir($template_dir)) {
        wp_mkdir_p($template_dir);
    }
    
    $template_file = $template_dir . 'fields-config-default.json';
    
    // Prüfe ob Template bereits existiert
    if (!$force && bes_fields_template_exists()) {
        return [
            'warning' => true,
            'message' => __('Ein Template existiert bereits. Möchten Sie es wirklich überschreiben?', 'besync')
        ];
    }
    
    $result = file_put_contents(
        $template_file,
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    
    return $result !== false;
}

/**
 * Merged Template-Config mit aktuellen Feldern
 * 
 * Nutzt Template nur für Felder, die in beiden vorhanden sind
 * 
 * @param array $auto_fields Automatisch extrahierte Felder
 * @param array $user_config Benutzer-Config (kann leer sein)
 * @return array Gemergte Config
 */
function bes_merge_template_with_fields(array $auto_fields, array $user_config): array
{
    // Wenn bereits eine User-Config existiert, diese verwenden
    if (!empty($user_config)) {
        return $user_config;
    }
    
    // Template laden
    $template = bes_load_fields_template();
    
    if (empty($template)) {
        return [];
    }
    
    // Nur Template-Einträge übernehmen, die auch in den aktuellen Feldern existieren
    $merged = [];
    foreach ($template as $id => $template_field) {
        if (isset($auto_fields[$id])) {
            $merged[$id] = $template_field;
        }
    }
    
    return $merged;
}

