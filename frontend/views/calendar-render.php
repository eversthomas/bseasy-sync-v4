<?php
if (!defined('ABSPATH')) exit;

/**
 * ------------------------------------------------------------
 * 📅 FRONTEND-KALENDER (Mehrere Kalender)
 * ------------------------------------------------------------
 */

/**
 * Rendert einen Kalender-Shortcode
 * 
 * @param array $atts Shortcode-Attribute
 * @return string HTML-Output
 */
function bes_render_calendar_shortcode($atts) {
    $atts = shortcode_atts([
        'id'    => '',
        'limit' => 10,
    ], $atts);

    $id = sanitize_title($atts['id']);
    $limit = intval($atts['limit']);

    // Debug-Logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("BES Calendar: Shortcode aufgerufen mit id='{$id}', limit={$limit}");
    }

    // CSS/JS nur laden wenn Shortcode verwendet wird
    // Calendar-Styles sind jetzt in frontend.css enthalten
    wp_enqueue_style('bes-frontend-style', BES_URL . 'frontend/assets/frontend.css', [], BES_VERSION);
    wp_enqueue_script('bes-calendar-js', BES_URL . 'frontend/assets/calendar.js', ['jquery'], BES_VERSION, true);

    $calendars = get_option('bes_calendars', []);
    
    // Debug: Logge gefundene Kalender
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("BES Calendar: Gefundene Kalender: " . count($calendars));
        error_log("BES Calendar: Gesuchte ID: '{$id}'");
    }
    
    $calendar = null;

    foreach ($calendars as $c) {
        if ($c['id'] === $id) {
            $calendar = $c;
            break;
        }
    }

    if (!$calendar || empty($calendar['url'])) {
        $debug_info = defined('WP_DEBUG') && WP_DEBUG 
            ? " (Debug: " . count($calendars) . " Kalender gefunden, gesuchte ID: '{$id}')" 
            : "";
        return '<p>⚠️ Kein gültiger Kalender gefunden.' . esc_html($debug_info) . '</p>';
    }

    $cache_file = BES_DATA . "calendar-cache-{$id}.json";
    $cache_lifetime = 6 * HOUR_IN_SECONDS;

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_lifetime) {
        $events = json_decode(file_get_contents($cache_file), true);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BES Calendar: Cache geladen, " . count($events) . " Events");
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BES Calendar: Lade ICS von: " . $calendar['url']);
        }
        $events = bes_parse_ics($calendar['url'], $calendar['max']);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BES Calendar: " . count($events) . " Events geparst");
        }
        if (!empty($events)) {
            file_put_contents($cache_file, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    if (empty($events)) {
        return '<p>Keine kommenden Termine gefunden.</p>';
    }

    // Filter: nur kommende Events
    $today = strtotime('today');
    $events_before = count($events);
    $events = array_filter($events, fn($e) => strtotime($e['start']) >= $today);
    $events_after = count($events);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("BES Calendar: Nach Filterung: {$events_before} → {$events_after} Events");
    }

    ob_start(); ?>
    <div class="bes-calendar" 
         data-limit="<?php echo esc_attr($limit); ?>"
         data-calendar-id="<?php echo esc_attr($id); ?>"
         data-events-count="<?php echo esc_attr(count($events)); ?>"
         data-debug="<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>">
        <h2><?php echo esc_html($calendar['name']); ?></h2>
        <div class="bes-calendar-grid">
            <?php foreach ($events as $i => $e): ?>
            <div class="bes-card bes-event-card<?php echo $i >= $limit ? ' hidden' : ''; ?>">
                <div class="bes-event-date"><?php echo date_i18n('d.m.Y', strtotime($e['start'])); ?></div>
                <h3 class="bes-event-title"><?php echo esc_html($e['title']); ?></h3>
                <?php if (!empty($e['location'])): ?>
                    <div class="bes-event-location"><?php echo esc_html($e['location']); ?></div>
                <?php endif; ?>
                <?php 
                $has_description = !empty($e['html_description']) || !empty($e['description']);
                $description_content = '';
                if ($has_description) {
                    $description_content = wp_kses_post($e['html_description'] ?: nl2br(esc_html($e['description'])));
                    // Prüfe ob nach dem Strippen von HTML-Tags noch Inhalt vorhanden ist
                    $has_description = trim(strip_tags($description_content)) !== '';
                }
                ?>
                <?php if ($has_description): ?>
                    <button class="bes-readmore" aria-expanded="false">Weiterlesen</button>
                    <div class="bes-event-full"><?php echo $description_content; ?></div>
                <?php endif; ?>
                <?php if (!empty($e['url'])): ?>
                    <a href="<?php echo esc_url($e['url']); ?>" class="bes-event-link" target="_blank">Zur Veranstaltung</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($events) > $limit): ?>
        <button class="bes-load-more">Mehr anzeigen</button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Shortcode
add_shortcode('bes_kalender', 'bes_render_calendar_shortcode');

/**
 * ------------------------------------------------------------
 * 🧩 ICS PARSER
 * ------------------------------------------------------------
 */
function bes_parse_ics($url, $max = 200)
{
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return [];

    $lines = explode("\n", wp_remote_retrieve_body($response));
    $events = [];
    $event = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') $event = [];
        elseif ($line === 'END:VEVENT') {
            if (!empty($event)) $events[] = $event;
            if (count($events) >= $max) break;
        } elseif (strpos($line, 'SUMMARY:') === 0) $event['title'] = substr($line, 8);
        elseif (strpos($line, 'DTSTART') === 0) $event['start'] = bes_parse_datetime($line);
        elseif (strpos($line, 'DTEND') === 0) $event['end'] = bes_parse_datetime($line);
        elseif (strpos($line, 'LOCATION:') === 0) $event['location'] = substr($line, 9);
        elseif (strpos($line, 'DESCRIPTION:') === 0) $event['description'] = substr($line, 12);
        elseif (strpos($line, 'X-ALT-DESC') === 0) $event['html_description'] = substr($line, strpos($line, ':') + 1);
        elseif (strpos($line, 'URL:') === 0) $event['url'] = substr($line, 4);
        elseif (strpos($line, 'CLASSIFICATION:') === 0) $event['category'] = substr($line, 15);
    }

    return $events;
}

function bes_parse_datetime($line)
{
    if (preg_match('/:(\d{8}T\d{6})/', $line, $m)) {
        return date('Y-m-d H:i:s', strtotime($m[1]));
    }
    return '';
}

// CSS/JS werden jetzt nur geladen, wenn der Shortcode verwendet wird (siehe bes_render_calendar_shortcode)