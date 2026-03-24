<?php if (!defined('ABSPATH')) exit; ?>

<h2>Kalenderverwaltung</h2>
<p>Hier kannst du mehrere EasyVerein-Kalender anlegen und konfigurieren. Jeder Kalender kann im Frontend per Shortcode angezeigt werden.</p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <?php wp_nonce_field('bes_save_calendars', 'bes_calendars_nonce'); ?>
  <input type="hidden" name="action" value="bes_save_calendars">

  <table class="widefat striped">
    <thead>
      <tr>
        <th style="width: 20%">Name</th>
        <th>ICS-URL</th>
        <th style="width: 15%">Maximale Anzahl Events</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $calendars = get_option('bes_calendars', []);

      // Initialwerte, falls noch leer
      if (empty($calendars)) {
        $calendars = [
          ['id' => 'transfer', 'name' => 'Transfer-Angebote', 'url' => '', 'max' => 200],
          ['id' => 'mitglieder', 'name' => 'Mitgliedertermine', 'url' => '', 'max' => 200],
          ['id' => 'intern', 'name' => 'Interne Termine', 'url' => '', 'max' => 200],
        ];
      }

      foreach ($calendars as $i => $cal):
      ?>
      <tr>
        <td>
          <input type="text" name="bes_calendars[<?php echo $i; ?>][name]" value="<?php echo esc_attr($cal['name']); ?>" style="width:100%">
          <input type="hidden" name="bes_calendars[<?php echo $i; ?>][id]" value="<?php echo esc_attr($cal['id']); ?>">
        </td>
        <td><input type="url" name="bes_calendars[<?php echo $i; ?>][url]" value="<?php echo esc_attr($cal['url']); ?>" style="width:100%"></td>
        <td><input type="number" min="10" max="5000" name="bes_calendars[<?php echo $i; ?>][max]" value="<?php echo intval($cal['max']); ?>" style="width:100%"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p><button type="submit" class="button button-primary">💾 Änderungen speichern</button></p>
</form>

<p><strong>Shortcodes:</strong><br>
<code>[bes_kalender id="transfer" limit="10"]</code>,
<code>[bes_kalender id="mitglieder" limit="10"]</code>,
<code>[bes_kalender id="intern" limit="10"]</code></p>
