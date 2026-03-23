<?php if (!defined('ABSPATH')) exit; ?>
<?php
// Debug-Log nur im Entwicklungsmodus
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('bes_write_debug_log')) {
    bes_write_debug_log("ui-main.php wird gerendert", "INFO", "ui-main.php");
    bes_write_debug_log("BES_DIR: " . (defined('BES_DIR') ? BES_DIR : 'NICHT DEFINIERT'), "INFO", "ui-main.php");
    
    // Prüfe ob Scripts geladen werden
    global $wp_scripts;
    if (isset($wp_scripts)) {
        $bes_admin_script = $wp_scripts->query('bes-admin-script', 'registered');
        if ($bes_admin_script) {
            bes_write_debug_log("bes-admin-script ist registriert: " . $bes_admin_script->src, "INFO", "ui-main.php");
        } else {
            bes_write_debug_log("bes-admin-script ist NICHT registriert", "ERROR", "ui-main.php");
        }
    }
}
?>
<div class="wrap bes-admin">
    <h1>BSEasy Sync</h1>
    <nav class="bes-tabs">
        <button type="button" class="active" data-tab="felder">Felder</button>
        <button type="button" data-tab="kalender">Kalender</button>
        <button type="button" data-tab="map">Karte</button>
        <button type="button" data-tab="sync">Sync</button>
    </nav>

    <section id="tab-felder" class="tab active">
        <?php require_once __DIR__ . '/ui-felder.php'; ?>
    </section>

    <section id="tab-kalender" class="tab">
        <?php require_once __DIR__ . '/ui-kalender.php'; ?>
    </section>

    <section id="tab-map" class="tab">
        <?php require_once __DIR__ . '/ui-map.php'; ?>
    </section>

    <section id="tab-sync" class="tab">
        <?php require_once __DIR__ . '/ui-sync.php'; ?>
    </section>
    
    <!-- Footer mit Credits -->
    <div class="bes-footer">
        <div class="bes-footer__logo">
            <img src="<?php echo esc_url( BES_URL . 'img/logo-trans.800x0.webp' ); ?>"
                 alt="<?php echo esc_attr__( 'Firmenlogo', 'besync' ); ?>"
                 class="bes-footer__logo-img">
        </div>
        <div class="bes-footer__text">
            <p class="bes-footer__line">
                <strong>BSEasy Sync</strong> v<?php echo defined('BES_VERSION') ? esc_html(BES_VERSION) : '3.0.0'; ?> 
                | Entwickelt von <a href="https://bezugssysteme.de" target="_blank" rel="noopener">Tom Evers</a> 
                | <a href="https://bezugssysteme.de" target="_blank" rel="noopener">bezugssysteme.de</a>
            </p>
            <p class="bes-footer__small">
                Open Source unter <a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener">GPL v2</a> 
                | Synchronisiert Daten aus <a href="https://easyverein.com" target="_blank" rel="noopener">EasyVerein</a> mit WordPress
            </p>
        </div>
    </div>
</div>

<?php
// Debug-Log für JS nur im Entwicklungsmodus
if (defined('WP_DEBUG') && WP_DEBUG && function_exists('bes_write_debug_log')) {
    bes_write_debug_log("Inline Script wird eingefügt - jQuery sollte verfügbar sein", "INFO", "ui-main.php");
}
?>

<script type="text/javascript">
if (typeof WP_DEBUG !== 'undefined' && WP_DEBUG) {
  // SOFORTIGER Test ohne jQuery-Abhängigkeit
  console.log("BES: Inline Script gestartet - jQuery verfügbar:", typeof jQuery !== 'undefined');
  console.log("BES: bes_debug_ajax verfügbar:", typeof bes_debug_ajax !== 'undefined');

  // Test ob das Script überhaupt ausgeführt wird
  if (typeof bes_debug_ajax === 'undefined') {
      console.error("BES KRITISCH: bes_debug_ajax ist NICHT verfügbar!");
  } else {
      console.log("BES: bes_debug_ajax gefunden:", bes_debug_ajax);
  }

  (function() {
      function waitForJQuery(callback) {
          if (typeof jQuery !== 'undefined') {
              callback(jQuery);
          } else {
              setTimeout(function() { waitForJQuery(callback); }, 50);
          }
      }
      
      waitForJQuery(function($) {
          function besDebugLog(message, level, context) {
              level = level || 'INFO';
              context = context || 'ui-main.php';
              
              if (level === 'ERROR') {
                  console.error("BES [" + level + "] [" + context + "]:", message);
              } else {
                  console.log("BES [" + level + "] [" + context + "]:", message);
              }
              
              if (typeof bes_debug_ajax === 'undefined' || !bes_debug_ajax.ajax_url) {
                  return;
              }
              
              $.ajax({
                  url: bes_debug_ajax.ajax_url,
                  method: "POST",
                  dataType: "json",
                  data: {
                      action: "bes_debug_log",
                      _ajax_nonce: bes_debug_ajax.nonce,
                      message: message,
                      level: level,
                      context: context
                  }
              });
          }
          
          besDebugLog("Inline Debug-Script geladen - jQuery verfügbar: " + (typeof $ !== 'undefined'), "INFO", "ui-main.php");
          besDebugLog("bes_debug_ajax verfügbar: " + (typeof bes_debug_ajax !== 'undefined'), "INFO", "ui-main.php");
          
          if (typeof bes_debug_ajax !== 'undefined' && bes_debug_ajax.ajax_url) {
              $.ajax({
                  url: bes_debug_ajax.ajax_url,
                  method: "POST",
                  dataType: "json",
                  data: {
                      action: "bes_debug_log",
                      _ajax_nonce: bes_debug_ajax.nonce,
                      message: "TEST: AJAX-Request funktioniert",
                      level: "INFO",
                      context: "ui-main.php"
                  }
              });
          }
          
          $(document).ready(function() {
              besDebugLog("jQuery DOM-Ready erreicht", "INFO", "ui-main.php");
          });
      });
  })();
}
</script>