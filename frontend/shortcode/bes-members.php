<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [bes_members]
 * ----------------------------------------------------------
 * Rendert Mitgliederkarten oder Karte im Frontend
 * 
 * Optionen:
 *   view="kachel"  → Nur Mitgliederkacheln (Standard)
 *   view="map"     → Nur Karte
 *   view="toggle"  → Toggle zwischen Kacheln & Karte
 */
// Neuer Shortcode
add_shortcode('bes_members', function ($atts) {

  // 🔧 Attribute
  $atts = shortcode_atts([
    'view' => 'kachel'  // kachel, map, oder toggle
  ], $atts, 'bes_members');

  $view = sanitize_text_field($atts['view']);

  // 🔧 Renderer-Funktion laden - MIT DIAGNOSTIK
  if (!function_exists('bes_render_members')) {
    // Kanonischer Pfad: Stub unter frontend/ lädt views/renderer.php
    if (defined('BES_DIR')) {
      $renderer_path = BES_DIR . 'frontend/renderer.php';
    } else {
      $renderer_path = dirname(__FILE__, 3) . '/frontend/renderer.php';
    }
    
    // 🔍 DEBUG: Überprüfe ob Datei existiert
    if (!file_exists($renderer_path)) {
      if (function_exists('bes_debug_log')) {
        bes_debug_log('renderer.php nicht gefunden: ' . $renderer_path, 'ERROR', 'shortcode');
      }
      return '<div style="color: red; padding: 20px; border: 2px solid red;">❌ Frontend-Fehler: Rendering-Datei nicht gefunden. Admin benachrichtigt.</div>';
    }
    
    // Versuche zu laden
    require_once $renderer_path;
    if (!function_exists('bes_render_members')) {
      if (function_exists('bes_debug_log')) {
        bes_debug_log('bes_render_members() existiert nicht nach require_once', 'ERROR', 'shortcode');
      }
      return '<div style="color: red; padding: 20px; border: 2px solid red;">❌ Frontend-Fehler: Rendering-Funktion konnte nicht geladen werden.</div>';
    }
  }

  // Sicherstellen, dass BES_URL definiert ist (Shortcode liegt unter frontend/shortcode/)
  if (!defined('BES_URL')) {
    $plugin_url = plugin_dir_url(dirname(__DIR__, 2) . '/bseasy-sync.php');
  } else {
    $plugin_url = BES_URL;
  }
  
  // Sicherstellen, dass BES_VERSION definiert ist
  if (!defined('BES_VERSION')) {
    $plugin_version = '3.0.0';
  } else {
    $plugin_version = BES_VERSION;
  }

  // 🎨 Frontend-Assets einbinden (Kacheln)
  wp_enqueue_style(
    'bes-frontend-style',
    $plugin_url . 'frontend/assets/frontend.css',
    [],
    $plugin_version
  );

  wp_enqueue_script(
    'bes-frontend-script',
    $plugin_url . 'frontend/assets/frontend.js',
    ['jquery'],
    $plugin_version,
    true
  );
  // defer: JS blockiert das Rendering nicht
  wp_script_add_data('bes-frontend-script', 'defer', true);

  // ✅ AJAX-Variablen für Frontend (inkl. Nonce)
  wp_localize_script('bes-frontend-script', 'bes_ajax', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('bes_filter_members_nonce')
  ]);

  // Map-Assets einbinden (wenn Karte benötigt)
  if (in_array($view, ['map', 'toggle'])) {
    // Leaflet.js CSS (Lokal)
    wp_enqueue_style(
      'leaflet-css',
      $plugin_url . 'frontend/assets/libs/leaflet/leaflet.min.css',
      [],
      '1.9.4'
    );

    // Leaflet.js (Lokal)
    wp_enqueue_script(
      'leaflet-js',
      $plugin_url . 'frontend/assets/libs/leaflet/leaflet.min.js',
      [],
      '1.9.4',
      true
    );

    // Leaflet Images Path für JavaScript setzen
    wp_add_inline_script('leaflet-js', 
      'L.Icon.Default.imagePath = "' . esc_js($plugin_url . 'frontend/assets/libs/leaflet/images/') . '";',
      'after'
    );

    // Leaflet Cluster CSS (Lokal)
    wp_enqueue_style(
      'leaflet-cluster-css',
      $plugin_url . 'frontend/assets/libs/leaflet-cluster/MarkerCluster.min.css',
      [],
      '1.5.1'
    );

    wp_enqueue_style(
      'leaflet-cluster-default-css',
      $plugin_url . 'frontend/assets/libs/leaflet-cluster/MarkerCluster.Default.min.css',
      [],
      '1.5.1'
    );

    // Leaflet Cluster JS (Lokal)
    wp_enqueue_script(
      'leaflet-cluster-js',
      $plugin_url . 'frontend/assets/libs/leaflet-cluster/leaflet.markercluster.min.js',
      ['leaflet-js'],
      '1.5.1',
      true
    );

    // Map-Styling ist jetzt in frontend.css enthalten (nicht mehr separat)

    // Map-JavaScript
    wp_enqueue_script(
      'bes-map-script',
      $plugin_url . 'frontend/assets/map.js',
      ['jquery', 'leaflet-js', 'leaflet-cluster-js'],
      $plugin_version,
      true
    );
  }

  // 🎨 Design-CSS einbinden (wenn Design-Einstellungen vorhanden)
  // WICHTIG: Vor dem Rendering, damit CSS verfügbar ist
  if (function_exists('bes_frontend_generate_design_css')) {
    // Verwende wp_add_inline_style für bessere Integration (ohne <style>-Tags)
    wp_add_inline_style('bes-frontend-style', bes_frontend_generate_design_css(false));
  }

  // 💡 Renderer aufrufen basierend auf View
  if ($view === 'map') {
    if (!function_exists('bes_render_members_map')) {
      $map_render_path = defined('BES_DIR') ? BES_DIR . 'frontend/map-render.php' : dirname(__FILE__, 3) . '/frontend/map-render.php';
      require_once $map_render_path;
    }
    $output = bes_render_members_map();
  } elseif ($view === 'toggle') {
    if (!function_exists('bes_render_members_map')) {
      $map_render_path = defined('BES_DIR') ? BES_DIR . 'frontend/map-render.php' : dirname(__FILE__, 3) . '/frontend/map-render.php';
      require_once $map_render_path;
    }

    $kacheln = bes_render_members();
    $karte = bes_render_members_map();

    // 📝 Toggle-Script direkt einbinden (verhindert mehrfache Registrierung)
    $toggle_script = <<<'JS'
      document.addEventListener('DOMContentLoaded', function() {
        // Nur die View-Toggle-Buttons (nicht die "Mehr anzeigen" Buttons in den Kacheln)
        const toggleContainer = document.querySelector('.bes-view-toggle');
        if (!toggleContainer) return;

        const toggleButtons = toggleContainer.querySelectorAll('.bes-toggle-btn[data-view]');
        const views = toggleContainer.querySelectorAll('.bes-view');

        toggleButtons.forEach(btn => {
          btn.addEventListener('click', function(e) {
            e.stopPropagation(); // Verhindere Bubble
            const view = this.getAttribute('data-view');

            // Update buttons
            toggleButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Update views
            views.forEach(v => {
              v.style.display = 'none';
              v.classList.remove('active');
            });

            const activeView = toggleContainer.querySelector('.bes-view-' + view);
            if (activeView) {
              activeView.style.display = 'block';
              activeView.classList.add('active');

              // Trigger Leaflet map resize wenn Karte angezeigt wird
              if (view === 'map' && window.besMapInstance) {
                setTimeout(() => {
                  window.besMapInstance.invalidateSize();
                }, 100);
              }
            }
          });
        });
      });
    JS;

    // Script nur einmal im Footer registrieren (verhindert mehrfache Ausführung)
    // Verwende eine globale Variable statt static, da static in Closures problematisch sein kann
    global $bes_toggle_script_registered;
    if (!isset($bes_toggle_script_registered) || !$bes_toggle_script_registered) {
      add_action('wp_footer', function() use ($toggle_script) {
        echo '<script>' . $toggle_script . '</script>';
      }, 99);
      $bes_toggle_script_registered = true;
    }

    // Toggle HTML direkt bauen (KEINE ob_start()!)
    $output = '<div class="bes-view-toggle">'
      . '<div class="bes-toggle-buttons">'
      . '<button class="bes-toggle-btn bes-toggle-kachel active" data-view="kachel">🎴 Kacheln</button>'
      . '<button class="bes-toggle-btn bes-toggle-map" data-view="map">🗺️ Karte</button>'
      . '</div>'
      . '<div class="bes-view-content">'
      . '<div class="bes-view bes-view-kachel active">' . $kacheln . '</div>'
      . '<div class="bes-view bes-view-map" style="display: none;">' . $karte . '</div>'
      . '</div>'
      . '</div>';
  } else {
    // Standard: Kacheln
    $output = bes_render_members();
  }

  // 🔄 Wrapper für Konsistenz
  $final_output = '<div class="bes-members-wrapper">' . $output . '</div>';
  
  // Definiere erlaubte HTML-Tags für wp_kses (ERWEITERT für Map und Kalender)
  $allowed_html = array(
    'article' => array('class' => true, 'data-member-id' => true),
    'address' => array('class' => true),
    'div'    => array('class' => true, 'id' => true, 'style' => true, 'data-field' => true, 'data-for-field' => true, 'data-id' => true, 'data-value' => true, 'data-view' => true, 'data-loaded' => true, 'data-limit' => true, 'data-map-markers' => true, 'data-map-filters' => true, 'data-map-settings' => true, 'data-uploads-url' => true),
    'label'  => array(),
    'input'  => array('type' => true, 'id' => true, 'placeholder' => true, 'class' => true, 'data-field' => true),
    'select' => array('data-field' => true, 'class' => true, 'data-for-field' => true),
    'option' => array('value' => true, 'selected' => true),
    'button' => array('id' => true, 'class' => true, 'data-view' => true, 'aria-expanded' => true, 'type' => true),
    'img'    => array('src' => true, 'alt' => true, 'class' => true, 'loading' => true, 'decoding' => true, 'width' => true, 'height' => true),
    'a'      => array('href' => true, 'target' => true, 'rel' => true, 'class' => true),
    'strong' => array('class' => true),
    'h2'     => array('class' => true),
    'h3'     => array('class' => true),
    'h4'     => array('class' => true),
    'p'      => array('class' => true, 'style' => true),
    'span'   => array('class' => true),
    'script' => array('type' => true, 'id' => true),  // KRITISCH: Für JSON-Daten (Map + Schema.org)
  );
  
  // Filter: Erlaube Entwicklern, erlaubte HTML-Tags anzupassen
  if (function_exists('bes_filter_allowed_html')) {
      $allowed_html = bes_filter_allowed_html($allowed_html);
  }
  
  // Verwende wp_kses um sicherzustellen dass HTML nicht escaped wird
  return wp_kses($final_output, $allowed_html);
});

