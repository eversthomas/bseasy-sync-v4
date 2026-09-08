<?php if (!defined('ABSPATH')) exit; ?>

<h2>Kalenderverwaltung</h2>
<p>Hier kannst du mehrere EasyVerein-Kalender anlegen und konfigurieren. Jeder Kalender kann im Frontend per Shortcode angezeigt werden.</p>

<?php
$calendars_saved = get_option('bes_calendars', []);
$using_defaults = empty($calendars_saved);
$calendars = $calendars_saved;

if ($using_defaults) {
  $calendars = [
    ['id' => 'transfer', 'name' => 'Transfer-Angebote', 'url' => '', 'max' => 200],
    ['id' => 'mitglieder', 'name' => 'Mitgliedertermine', 'url' => '', 'max' => 200],
    ['id' => 'intern', 'name' => 'Interne Termine', 'url' => '', 'max' => 200],
  ];
}

if (!empty($_GET['bes_refreshed']) && current_user_can('manage_options')) {
  $refresh_results = get_transient('bes_calendar_refresh_result_' . get_current_user_id());
  delete_transient('bes_calendar_refresh_result_' . get_current_user_id());

  if (is_array($refresh_results) && !empty($refresh_results)) {
    echo '<div class="notice notice-info is-dismissible" style="margin:1em 0;padding:0.75em 1em;"><ul style="margin:0;list-style:disc inside;">';
    foreach ($refresh_results as $r) {
      if ($r['status'] === 'error') {
        echo '<li>⚠️ <strong>' . esc_html($r['name']) . '</strong>: Abruf der ICS-Quelle fehlgeschlagen (Timeout/HTTP-Fehler). Bestehender Cache bleibt erhalten.</li>';
      } elseif ($r['status'] === 'empty') {
        echo '<li>ℹ️ <strong>' . esc_html($r['name']) . '</strong>: Abruf erfolgreich, aber keine Termine in der Quelle gefunden.</li>';
      } else {
        echo '<li>✅ <strong>' . esc_html($r['name']) . '</strong>: aktualisiert, ' . intval($r['count']) . ' Termine geladen.</li>';
      }
    }
    echo '</ul></div>';
  } else {
    echo '<div class="notice notice-warning is-dismissible" style="margin:1em 0;padding:0.75em 1em;">Kein Kalender mit hinterlegter URL zum Aktualisieren gefunden.</div>';
  }
}
?>

<?php if (!$using_defaults): ?>
<p>
  <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bes_refresh_calendar&id='), 'bes_refresh_calendar_')); ?>">
    🔄 Alle Kalender jetzt aktualisieren
  </a>
</p>
<?php endif; ?>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <?php wp_nonce_field('bes_save_calendars', 'bes_calendars_nonce'); ?>
  <input type="hidden" name="action" value="bes_save_calendars">

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width: 20%">Name</th>
        <th>ICS-URL</th>
        <th style="width: 15%">Maximale Anzahl Events</th>
        <th style="width: 15%">Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($calendars as $i => $cal): ?>
      <tr>
        <td>
          <input type="text" name="bes_calendars[<?php echo $i; ?>][name]" value="<?php echo esc_attr($cal['name']); ?>" style="width:100%">
          <input type="hidden" name="bes_calendars[<?php echo $i; ?>][id]" value="<?php echo esc_attr($cal['id']); ?>">
        </td>
        <td><input type="url" name="bes_calendars[<?php echo $i; ?>][url]" value="<?php echo esc_attr($cal['url']); ?>" style="width:100%"></td>
        <td><input type="number" min="10" max="5000" name="bes_calendars[<?php echo $i; ?>][max]" value="<?php echo intval($cal['max']); ?>" style="width:100%"></td>
        <td>
          <?php if (!$using_defaults && !empty($cal['url'])): ?>
          <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bes_refresh_calendar&id=' . rawurlencode($cal['id'])), 'bes_refresh_calendar_' . $cal['id'])); ?>">
            🔄 Aktualisieren
          </a>
          <?php else: ?>
          &mdash;
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($using_defaults): ?>
  <p class="notice notice-warning inline" style="margin:1em 0;padding:0.75em 1em;">
    <strong>Hinweis:</strong> Diese Kalender sind noch nicht gespeichert. Shortcodes funktionieren erst nach dem ersten Speichern.
  </p>
  <?php endif; ?>

  <p><button type="submit" class="button button-primary">💾 Änderungen speichern</button></p>
</form>

<p style="color:#666;font-size:0.9em;">
  Hinweis: Kalenderdaten werden normalerweise 6 Stunden zwischengespeichert. Der „Aktualisieren“-Button holt den aktuellen Stand
  direkt von easyverein und schreibt den Cache sofort neu – nützlich, wenn ein Termin nach einer Änderung noch nicht korrekt
  angezeigt wird.
</p>

<p><strong>Shortcodes:</strong><br>
<code>[bes_kalender id="transfer" limit="10"]</code>,
<code>[bes_kalender id="mitglieder" limit="10"]</code>,
<code>[bes_kalender id="intern" limit="10"]</code></p>
