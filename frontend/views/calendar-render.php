<?php
if (!defined('ABSPATH')) exit;

/**
 * ------------------------------------------------------------
 * 📅 FRONTEND-KALENDER (Mehrere Kalender)
 * ------------------------------------------------------------
 */

/**
 * Dekodiert ICS-Escape-Sequenzen in Textfeldern.
 *
 * @param string $value Roher ICS-Wert
 * @return string Dekodierter Text
 */
function bes_decode_ics_text($value)
{
    return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
}

/**
 * Schreibt Kalender-Cache-Daten mit Plugin-Helfer oder Fallback.
 *
 * @param string $cache_file Zielpfad
 * @param string $json       JSON-Inhalt
 * @return void
 */
function bes_calendar_write_cache($cache_file, $json)
{
    if (function_exists('bes_safe_file_put_contents')) {
        bes_safe_file_put_contents($cache_file, $json);
        return;
    }
    file_put_contents($cache_file, $json);
}

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
        'debug' => '0', // DIAGNOSE – entfernen nach Fix
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

    if (!wp_script_is('jquery', 'registered')) {
        wp_register_script(
            'jquery',
            includes_url('js/jquery/jquery.min.js'),
            [],
            '3.7.1',
            true
        );
    }

    wp_enqueue_script('bes-calendar-js', BES_URL . 'frontend/assets/calendar.js', ['jquery'], BES_VERSION, true);

    // DIAGNOSE – entfernen nach Fix
    wp_add_inline_script(
        'bes-calendar-js',
        'console.log("[BES Calendar] wp_add_inline_script vor calendar.js");',
        'before'
    );

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

    $cache_file = (defined('BES_DATA') && BES_DATA) ? BES_DATA . "calendar-cache-{$id}.json" : '';
    $cache_lifetime = 6 * HOUR_IN_SECONDS;
    $events = [];
    $cache_used = false;

    if ($cache_file && file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_lifetime) {
        $decoded = json_decode(file_get_contents($cache_file), true);
        if (is_array($decoded)) {
            $events = $decoded;
            $cache_used = true;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("BES Calendar: Cache geladen, " . count($events) . " Events");
            }
        }
    }

    if (!$cache_used) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BES Calendar: Lade ICS von: " . $calendar['url']);
        }
        $events = bes_parse_ics($calendar['url'], $calendar['max']);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BES Calendar: " . count($events) . " Events geparst");
        }
        if (!empty($events) && $cache_file) {
            bes_calendar_write_cache(
                $cache_file,
                json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    if (empty($events)) {
        return '<p>Keine kommenden Termine gefunden.</p>';
    }

    // Filter: nur kommende Events
    $today = strtotime('today');
    $events_before = count($events);
    $events = array_filter($events, function ($e) use ($today) {
        return !empty($e['start']) && strtotime($e['start']) >= $today;
    });
    $events = array_values($events);
    usort($events, function ($a, $b) {
        return strcmp($a['start'], $b['start']);
    });
    $events_after = count($events);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("BES Calendar: Nach Filterung: {$events_before} → {$events_after} Events");
    }

    if (empty($events)) {
        return '<p>Keine kommenden Termine gefunden.</p>';
    }

    ob_start(); ?>
    <div class="bes-calendar" 
         data-limit="<?php echo esc_attr($limit); ?>"
         data-calendar-id="<?php echo esc_attr($id); ?>"
         data-events-count="<?php echo esc_attr(count($events)); ?>"
         data-debug="<?php echo $atts['debug'] === '1' ? 'true' : 'false'; ?>"><?php // DIAGNOSE – entfernen nach Fix ?>
        <h2><?php echo esc_html($calendar['name']); ?></h2>
        <div class="bes-calendar-grid">
            <?php foreach ($events as $i => $e): ?>
            <?php
                // DIAGNOSE – entfernen nach Fix
                $desc_source = 'none';
                if (!empty($e['html_description'])) {
                    $desc_source = 'html';
                } elseif (!empty($e['description'])) {
                    $desc_source = 'text';
                }
                $has_description = !empty($e['html_description']) || !empty($e['description']);
                $description_content = '';
                if ($has_description) {
                    $description_content = wp_kses_post($e['html_description'] ?: nl2br(esc_html($e['description'])));
                    // Prüfe ob nach dem Strippen von HTML-Tags noch Inhalt vorhanden ist
                    $has_description = trim(strip_tags($description_content)) !== '';
                }
                $desc_length = strlen($description_content);
            ?>
            <div class="bes-card bes-event-card<?php echo $i >= $limit ? ' hidden' : ''; ?>"
                 data-has-description="<?php echo $has_description ? 'true' : 'false'; ?>"
                 data-desc-source="<?php echo esc_attr($desc_source); ?>"
                 data-desc-length="<?php echo esc_attr($desc_length); ?>"><?php // DIAGNOSE – entfernen nach Fix ?>
                <div class="bes-event-date"><?php echo date_i18n('d.m.Y', strtotime($e['start'])); ?></div>
                <h3 class="bes-event-title"><?php echo esc_html($e['title']); ?></h3>
                <?php if (!empty($e['location'])): ?>
                    <div class="bes-event-location"><?php echo esc_html($e['location']); ?></div>
                <?php endif; ?>
                <?php if ($has_description): ?>
                    <button type="button" class="bes-readmore" aria-expanded="false">Weiterlesen</button><?php // DIAGNOSE – entfernen nach Fix (type="button") ?>
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
    <?php if ($atts['debug'] === '1'): ?>
    <script>
    // DIAGNOSE – entfernen nach Fix
    (function () {
      console.log('[BES Calendar] Inline-Diagnose: Shortcode-HTML ausgegeben (id=<?php echo esc_js($id); ?>)');
      document.addEventListener('DOMContentLoaded', function () {
        var cal = document.querySelector('.bes-calendar[data-calendar-id="<?php echo esc_js($id); ?>"]');
        var scripts = Array.prototype.slice.call(document.querySelectorAll('script[src*="calendar.js"]'));
        console.log('[BES Calendar] Inline-Diagnose DOMContentLoaded:', {
          containerFound: !!cal,
          dataDebug: cal ? cal.getAttribute('data-debug') : null,
          readmoreButtons: cal ? cal.querySelectorAll('.bes-readmore').length : 0,
          calendarJsScriptTags: scripts.length,
          calendarJsUrls: scripts.map(function (s) { return s.src; })
        });
      });
    })();
    </script>
    <?php endif; ?>
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
    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        return [];
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return [];
    }

    $body = str_replace("\r\n", "\n", wp_remote_retrieve_body($response));
    $body = str_replace("\r", "\n", $body);
    $raw_lines = explode("\n", $body);
    $lines = [];

    foreach ($raw_lines as $raw_line) {
        if ($raw_line === '') {
            continue;
        }
        if (!empty($lines) && isset($raw_line[0]) && ($raw_line[0] === ' ' || $raw_line[0] === "\t")) {
            $lines[count($lines) - 1] .= substr($raw_line, 1);
        } else {
            $lines[] = $raw_line;
        }
    }

    $events = [];
    $event = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $event = [];
        } elseif ($line === 'END:VEVENT') {
            if (!empty($event)) {
                $events[] = $event;
            }
            if (count($events) >= $max) {
                break;
            }
        } elseif (strpos($line, 'SUMMARY:') === 0) {
            $event['title'] = bes_decode_ics_text(substr($line, 8));
        } elseif (strpos($line, 'DTSTART') === 0) {
            $event['start'] = bes_parse_datetime($line);
        } elseif (strpos($line, 'DTEND') === 0) {
            $event['end'] = bes_parse_datetime($line);
        } elseif (strpos($line, 'LOCATION:') === 0) {
            $event['location'] = bes_decode_ics_text(substr($line, 9));
        } elseif (strpos($line, 'DESCRIPTION:') === 0) {
            $event['description'] = bes_decode_ics_text(substr($line, 12));
        } elseif (strpos($line, 'X-ALT-DESC') === 0) {
            $event['html_description'] = substr($line, strpos($line, ':') + 1);
        } elseif (strpos($line, 'URL:') === 0) {
            $event['url'] = bes_decode_ics_text(substr($line, 4));
        } elseif (strpos($line, 'CLASSIFICATION:') === 0) {
            $event['category'] = substr($line, 15);
        }
    }

    return $events;
}

function bes_parse_datetime($line)
{
    $tzid = null;
    if (preg_match('/TZID=([^:;]+)/', $line, $tz_match)) {
        $tzid = $tz_match[1];
    }

    if (preg_match('/:(\d{8}T\d{6}Z?)/', $line, $m)) {
        $raw = $m[1];

        if ($tzid && in_array($tzid, timezone_identifiers_list(), true)) {
            $tz = timezone_open($tzid);
            if ($tz) {
                $format = (substr($raw, -1) === 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis';
                $dt = date_create_from_format($format, $raw, $tz);
                if ($dt) {
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        if (substr($raw, -1) === 'Z') {
            $dt = date_create_from_format('Ymd\THis\Z', $raw, new DateTimeZone('UTC'));
            if ($dt) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return date('Y-m-d H:i:s', strtotime($raw));
    }

    if (preg_match('/:(\d{8})$/', $line, $m)) {
        return date('Y-m-d', strtotime($m[1]));
    }

    return '';
}

// CSS/JS werden jetzt nur geladen, wenn der Shortcode verwendet wird (siehe bes_render_calendar_shortcode)
