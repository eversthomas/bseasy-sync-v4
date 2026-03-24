<?php
/**
 * Sync-Tab: Karte „Cache-Verwaltung“ (benötigt Variablen aus ui-sync.php).
 *
 * Erwartete Variablen: $cache_stats, $cache_enabled, $dev_mode, $cache_duration_minutes
 *
 * @package BSEasySync
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

    <!-- Cache-Verwaltung -->
    <div class="bes-card bes-card-muted">
        <h3 class="bes-card-title">Cache-Verwaltung</h3>
        <div class="bes-card-block">
            <p class="bes-card-text" style="margin-bottom: 15px;">
                <strong>Zweck:</strong> Verwaltet den lokalen Cache für bessere Performance. Der Cache speichert gerenderte Inhalte, Karten-Daten und API-Antworten temporär.<br>
                <strong>Wann nutzen:</strong> Bei Problemen mit veralteten Daten oder nach größeren Änderungen. Der Cache wird automatisch nach <?php echo esc_html($cache_duration_minutes); ?> Minuten erneuert.
            </p>
        </div>
        
        <?php if ($cache_stats): ?>
            <div class="bes-card-block">
                <p class="bes-card-text">
                    <strong>Status:</strong> 
                    <?php if ($cache_enabled): ?>
                        <span class="bes-text-success">Aktiv</span>
                        <?php if ($dev_mode): ?>
                            <span class="bes-text-warning">(Dev-Mode: <?php echo esc_html($cache_duration_minutes); ?> Min)</span>
                        <?php else: ?>
                            <span class="bes-text-muted">(<?php echo esc_html($cache_duration_minutes); ?> Min)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="bes-text-error">Deaktiviert</span>
                    <?php endif; ?>
                </p>
                <p class="bes-card-text">
                    <strong>Einträge:</strong>
                    <span id="bes-cache-total"><?php echo esc_html($cache_stats['total_entries']); ?></span>
                    <span id="bes-cache-details">
                        (Renderer: <span id="bes-cache-render"><?php echo esc_html($cache_stats['render_cache']); ?></span>,
                        Map: <span id="bes-cache-map"><?php echo esc_html($cache_stats['map_cache']); ?></span><?php 
                        if (isset($cache_stats['select_options_cache']) && $cache_stats['select_options_cache'] > 0): 
                            ?>, Select-Options: <span id="bes-cache-select"><?php echo esc_html($cache_stats['select_options_cache']); ?></span><?php 
                        endif;
                        if (isset($cache_stats['rate_limit_cache']) && $cache_stats['rate_limit_cache'] > 0): 
                            ?>, Rate-Limit: <span id="bes-cache-rate-limit"><?php echo esc_html($cache_stats['rate_limit_cache']); ?></span><?php 
                        endif;
                        if (isset($cache_stats['other_cache']) && $cache_stats['other_cache'] > 0): 
                            ?>, Sonstige: <span id="bes-cache-other"><?php echo esc_html($cache_stats['other_cache']); ?></span><?php 
                        endif;
                        ?>)
                    </span>
                </p>
                <?php if ($cache_stats['total_size_mb'] > 0): ?>
                    <p class="bes-card-text">
                        <strong>Größe:</strong> <span id="bes-cache-size"><?php echo esc_html($cache_stats['total_size_mb']); ?></span> MB
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="bes-card-actions">
            <button type="button" id="bes-clear-cache" class="button button-secondary">
                <span class="bes-spinner" aria-hidden="true"></span>
                <span class="bes-btn-label">Cache leeren</span>
            </button>
            <?php if ($dev_mode): ?>
                <span class="bes-card-hint">
                    Dev-Mode aktiv: Cache-Dauer reduziert auf <?php echo esc_html($cache_duration_minutes); ?> Minuten
                </span>
            <?php endif; ?>
        </div>
        
        <div id="bes-cache-message" class="bes-card-message"></div>
    </div>
