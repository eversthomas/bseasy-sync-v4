<?php
/**
 * Sync-Tab: Layout-Skelett.
 *
 * Bindet Datenvorbereitung und HTML-Partials ein, enthält den gemeinsamen
 * JavaScript-Handler-Block für Explorer, Feldauswahl und Sync.
 *
 * Architektur:
 *   sync-data-loader.php   — PHP-Datenvorbereitung (keine HTML-Ausgabe)
 *   sync-tab-explorer.php  — HTML: V3 Explorer + Feldauswahl
 *   sync-tab-sync.php      — HTML: V3 Sync-Karte
 *
 * @package BSEasySync
 * @author Tom Evers <https://bezugssysteme.de>
 */
// Kein schließendes PHP-Tag - WordPress Best Practice

if (!defined('ABSPATH')) exit;

// ── Datenvorbereitung (PHP, kein HTML) ───────────────────────────────────────
require_once BES_DIR . 'admin/views/partials/sync-data-loader.php';
?>

<?php require_once BES_DIR . 'admin/views/partials/sync-onboarding-hint.php'; ?>

<div class="bes-top-grid">

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-cache-card.php'; ?>

    <!-- API-Zugangsdaten -->
    <div class="bes-card">
        <h3 class="bes-card-title">API-Zugangsdaten</h3>
        <div class="bes-card-block">
            <p class="bes-card-text" style="margin-bottom: 15px;">
                <strong>Zweck:</strong> Konfiguration der Verbindung zur EasyVerein API. Der API-Token authentifiziert die Anfragen, die Consent-Feld-ID bestimmt, welche Mitglieder synchronisiert werden.<br>
                <strong>Wann nutzen:</strong> Bei der ersten Einrichtung oder bei Token-Wechsel. Die Consent-Feld-ID kann auch aus dem V3 Explorer Feldkatalog (Custom Fields) entnommen werden.
            </p>
        </div>
        <form method="post" class="bes-sync-form">
            <?php wp_nonce_field('bes_admin_save', 'bes_admin_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="bes_token">API-Token</label></th>
                    <td>
                        <?php
                        $encrypted_token = get_option('bes_api_token', '');
                        $display_token = '';
                        if (!empty($encrypted_token)) {
                            if (function_exists('bes_decrypt_token')) {
                                $display_token = bes_decrypt_token($encrypted_token);
                            } else {
                                $display_token = $encrypted_token;
                            }
                        }
                        ?>
                        <input type="password" id="bes_token" name="bes_token"
                            value="<?php echo esc_attr($display_token); ?>"
                            style="width:400px;"
                            placeholder="API-Token eingeben">
                        <p class="description">Token wird verschlüsselt gespeichert. Leer lassen, um den aktuellen Token beizubehalten.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bes_consent_field_id">Consent-Feld-ID</label></th>
                    <td>
                        <input type="number" id="bes_consent_field_id" name="bes_consent_field_id"
                            value="<?php echo esc_attr(get_option('bes_consent_field_id', '282018660')); ?>"
                            style="width:200px;">
                        <p class="description">ID des Custom Fields, das die Einwilligung zur Veröffentlichung bestimmt. Standard: 282018660</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="bes_sync_all_members">Sync-Modus</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="bes_sync_all_members" name="bes_sync_all_members" value="1"
                                <?php checked(get_option('bes_sync_all_members', false)); ?>>
                            <strong>Alle Mitglieder synchronisieren (ohne Consent-Filter)</strong>
                        </label>
                        <p class="description" style="margin-top: 10px;">
                            <span style="color: #d63638; font-weight: bold;">⚠️ WARNUNG:</span> Wenn aktiviert, werden <strong>alle Mitglieder</strong> synchronisiert, unabhängig von der Consent-Einstellung.
                            Dies kann datenschutzrechtliche Probleme verursachen. Nur verwenden, wenn Sie sicher sind, dass alle Mitglieder veröffentlicht werden dürfen.
                        </p>
                        <p class="description" style="margin-top: 5px;">
                            <strong>Standard:</strong> Nur Mitglieder mit aktiver Consent (Consent-Feld-ID = true) werden synchronisiert.
                        </p>
                    </td>
                </tr>
            </table>
            <p class="bes-action-row">
                <button type="submit" class="button">Einstellungen speichern</button>
            </p>
        </form>
    </div>
</div>

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-explorer.php'; ?>

<?php require_once BES_DIR . 'admin/views/partials/sync-tab-sync.php'; ?>

<!-- V2-Progress-Box wurde entfernt - V3 verwendet eigene Statusleiste im V3-Bereich -->

<script>
    jQuery(function($) {
        // V2-JavaScript-Handler wurden entfernt - nur noch V3 wird verwendet

        // Helper-Funktion für Button-States (wird von V3 verwendet)
        function setButtonState($btn, label, options = {}) {
            const opts = Object.assign({ loading: false, disabled: false }, options);
            $btn.toggleClass('is-bes-loading', !!opts.loading);
            $btn.prop('disabled', !!opts.disabled);
            const labelEl = $btn.find('.bes-btn-label');
            if (labelEl.length) {
                labelEl.text(label);
            } else {
                $btn.text(label);
            }
        }

        // V2-JavaScript-Handler wurden entfernt - nur noch V3 wird verwendet

        // ============================================================
        // V3 EXPLORER & SYNC
        // ============================================================

        // V3 Explorer Status Polling mit Statusleiste - Definition weiter unten (ab Zeile 828)


        // Neue Funktionen für Benachrichtigungen
        function showSyncCompleteNotification(message) {
            // Browser-Benachrichtigung (falls erlaubt)
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('BSEasy Sync abgeschlossen', {
                    body: message,
                    icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                    tag: 'bes-sync-complete',
                    requireInteraction: false
                });
            } else if ('Notification' in window && Notification.permission !== 'denied') {
                // Erlaube Benachrichtigungen beim ersten Mal
                Notification.requestPermission().then(function(permission) {
                    if (permission === 'granted') {
                        new Notification('BSEasy Sync abgeschlossen', {
                            body: message,
                            icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                            tag: 'bes-sync-complete'
                        });
                    }
                });
            }

            // Visuelle Toast-Benachrichtigung
            showToast('success', '✅ Sync abgeschlossen', message);

            // Seiten-Titel aktualisieren (falls Tab nicht aktiv)
            if (document.hidden) {
                const originalTitle = document.title;
                document.title = '✅ ' + message + ' - ' + originalTitle;

                // Zurücksetzen nach 5 Sekunden oder wenn Tab wieder aktiv wird
                const resetTitle = function() {
                    document.title = originalTitle;
                    document.removeEventListener('visibilitychange', resetTitle);
                };
                document.addEventListener('visibilitychange', resetTitle);
                setTimeout(resetTitle, 5000);
            }
        }

        function showSyncErrorNotification(message) {
            // Browser-Benachrichtigung für Fehler
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('BSEasy Sync Fehler', {
                    body: message,
                    icon: '<?php echo esc_url(BES_URL . "img/logo-trans.800x0.webp"); ?>',
                    tag: 'bes-sync-error',
                    requireInteraction: true // Fehler sollten Aufmerksamkeit erfordern
                });
            }

            // Visuelle Toast-Benachrichtigung
            showToast('error', '❌ Sync Fehler', message);
        }

        function showToast(type, title, message) {
            // Erstelle Toast-Element
            const toast = $('<div>')
                .addClass('bes-toast bes-toast-' + type)
                .html('<strong>' + title + '</strong><br>' + message)
                .css({
                    position: 'fixed',
                    top: '20px',
                    right: '20px',
                    background: type === 'success' ? '#00a32a' : '#d63638',
                    color: '#fff',
                    padding: '15px 20px',
                    borderRadius: '4px',
                    boxShadow: '0 2px 10px rgba(0,0,0,0.2)',
                    zIndex: 999999,
                    maxWidth: '400px',
                    animation: 'slideInRight 0.3s ease-out'
                });

            $('body').append(toast);

            // Auto-Entfernen nach 5 Sekunden
            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Klick zum Schließen
            toast.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }

        function updateSyncStatistics(statusData) {
            // Aktualisiere die Statistiken oben auf der Seite ohne Seiten-Reload
            if (statusData.members_with_consent !== null && statusData.members_with_consent !== undefined) {
                $('#bes-sync-consent-count').text(statusData.members_with_consent.toLocaleString('de-DE'));
            }

            if (statusData.members_total !== null && statusData.members_total !== undefined) {
                $('#bes-sync-total-count').text(statusData.members_total.toLocaleString('de-DE'));
            }
        }


        // V2-Funktion startSync() wurde entfernt - V3 verwendet andere Funktionen

        // V2-Buttons (btnConsent, btnMerge) und V2-Merge-Handler wurden entfernt - V3 verwendet andere Buttons

        // V2-Stop- und Reset-Button Handler wurden entfernt - V3 verwendet andere Handler

        // V2-Autostart und Status-Polling wurde entfernt - V3 verwendet andere Funktionen

        // ============================================================
        // V3 EXPLORER & SYNC
        // ============================================================

        // V3 Explorer Status Polling mit Statusleiste
        let pollV3ExplorerTimer = null;
        const explorerBar = $('#besV3ExplorerBarInner');
        const explorerStatus = $('#besV3ExplorerStatus');
        const explorerBox = $('#besV3ExplorerProgress');

        function schedulePollV3Explorer(delayMs) {
            clearTimeout(pollV3ExplorerTimer);
            pollV3ExplorerTimer = setTimeout(pollV3ExplorerStatus, delayMs || 3000);
        }

        function pollV3ExplorerStatus() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
            const messageEl = $('#bes-v3-explorer-message');

            $.get(ajaxurl, {
                action: 'bes_v3_status',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success && resp.data) {
                    const s = resp.data;
                    const progress = Math.max(0, Math.min(100, s.progress || 0));
                    const previousState = explorerBox.data('previous-state') || 'idle';
                    const stateChanged = previousState !== s.state;

                    // Debug-Ausgabe
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('Explorer Status:', { state: s.state, explorer_running: s.explorer_running, progress: progress, message: s.message });
                    }

                    // Aktualisiere Statusleiste
                    explorerBar.css('width', progress + '%');
                    explorerStatus.text(s.message || 'Kein Status');
                    explorerBox.data('previous-state', s.state);

                    if (s.explorer_running || s.state === 'running') {
                        // Explorer läuft
                        explorerBox.show();
                        explorerStatus.css('color', '');
                        explorerBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');
                        explorerStatus.text(s.message || 'API Explorer läuft...');
                        messageEl.html('<strong>🔄 ' + (s.message || 'API Explorer läuft...') + '</strong>');

                        // Zeige Stop-Button falls vorhanden
                        $('#bes-v3-stop-explorer').show();

                        schedulePollV3Explorer(3000);
                    } else if (s.state === 'done') {
                        // Explorer abgeschlossen
                        explorerBox.show();
                        const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                        explorerBar.css('width', finalProgress + '%');
                        explorerStatus.css('color', '#00a32a');
                        explorerBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

                        let successMsg = '✅ ' + (s.message || 'API Explorer abgeschlossen');
                        if (s.explorer_field_count !== null && s.explorer_field_count !== undefined) {
                            successMsg += ' (' + s.explorer_field_count.toLocaleString('de-DE') + ' Felder gefunden)';
                        }
                        explorerStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                        messageEl.html('<strong style="color: #00a32a;">✅ ' + (s.message || 'API Explorer abgeschlossen') + '</strong>').addClass('success');

                        $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                        $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                        $('#bes-v3-stop-explorer').hide();

                        // Stoppe Polling komplett nach Abschluss
                        clearTimeout(pollV3ExplorerTimer);

                        // Seite neu laden nach 2 Sekunden, damit Feldkatalog-Prüfung aktualisiert wird
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else if (s.state === 'error') {
                        // Fehler
                        explorerBox.show();
                        explorerStatus.css('color', '#d63638');
                        explorerBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        explorerStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                        messageEl.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>').addClass('error');

                        $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                        $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                        $('#bes-v3-stop-explorer').hide();

                        // Stoppe Polling bei Fehler
                        clearTimeout(pollV3ExplorerTimer);
                    } else {
                        // Explorer nicht mehr aktiv - verstecke Statusleiste und stoppe Polling komplett
                        if (typeof console !== 'undefined' && console.log) {
                            console.log('Explorer Status: nicht aktiv', { state: s.state, explorer_running: s.explorer_running });
                        }
                        explorerBox.hide();
                        messageEl.html('');
                        clearTimeout(pollV3ExplorerTimer);
                        pollV3ExplorerTimer = null; // Stelle sicher, dass Timer null ist
                    }
                } else {
                    // Response hat keine Daten
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('Explorer Status-Response hat keine Daten:', resp);
                    }
                    // Retry nach 5 Sekunden
                    schedulePollV3Explorer(5000);
                }
            }).fail(function(xhr, statusText, error) {
                // Bei Fehler retry nach 5 Sekunden
                console.warn('Explorer Status-Abfrage fehlgeschlagen:', { xhr, statusText, error });
                messageEl.html('<strong style="color: #d63638;">⚠️ Status konnte nicht abgerufen werden</strong>');
                schedulePollV3Explorer(5000);
            });
        }

        // Prüfe Explorer-Status beim Seitenaufruf
        <?php if ($explorer_running): ?>
        setTimeout(function() {
            pollV3ExplorerStatus();
        }, 1000);
        <?php endif; ?>

        // Prüfe V3 Sync-Status beim Seitenaufruf
        const v3SyncNonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;
        $.get(ajaxurl, {
            action: 'bes_v3_status',
            _ajax_nonce: v3SyncNonce
        }, function(resp) {
            if (resp && resp.success && resp.data) {
                const s = resp.data;
                if (s.state === 'running' && !s.explorer_running) {
                    // V3 Sync läuft - starte Polling
                    syncBox.show();
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('Läuft...');
                    $('#bes-v3-start-sync').addClass('is-bes-loading').prop('disabled', true);
                    $('#bes-v3-stop-sync').show();
                    $('#bes-v3-reset-sync').hide();

                    if (s.current_part && s.total_parts) {
                        syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
                    } else {
                        syncStatus.text(s.message || 'V3 Sync läuft...');
                    }

                    pollV3Status();
                } else if (s.state === 'done' && !s.explorer_running) {
                    // V3 Sync ist fertig
                    syncBox.show();
                    const progress = Math.max(0, Math.min(100, s.progress || 100));
                    syncBar.css('width', progress + '%');

                    let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
                    if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                        successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
                    }
                    syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                    syncStatus.css('color', '#00a32a');
                    syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Stoppe Polling komplett nach Abschluss
                    clearTimeout(pollV3SyncTimer);
                    pollV3SyncTimer = null;
                } else if (s.state === 'error' && !s.explorer_running) {
                    // V3 Sync hat Fehler
                    syncBox.show();
                    syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                    syncStatus.css('color', '#d63638');
                    syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');

                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                } else if (s.state === 'cancelled' && !s.explorer_running) {
                    // V3 Sync wurde gestoppt
                    syncBox.show();
                    syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                    syncStatus.css('color', '#d63638');
                    syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');

                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();
                }
            }
        }).fail(function() {
            // Bei Fehler einfach verstecken
            syncBox.hide();
        });

        // V3 Explorer
        $('#bes-v3-run-explorer').on('click', function() {
            const btn = $(this);
            const sampleSize = parseInt($('#bes-v3-explorer-sample-size').val());
            const freshFromApi = $('#bes-v3-explorer-fresh').is(':checked');
            const messageEl = $('#bes-v3-explorer-message');

            setButtonState(btn, 'Starte...', { loading: true, disabled: true });
            messageEl.html('').removeClass('error success');

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.post(ajaxurl, {
                action: 'bes_v3_run_explorer',
                _ajax_nonce: v3Nonce,
                sample_size: sampleSize,
                fresh_from_api: freshFromApi ? 1 : 0
            }, function(resp) {
                if (resp && resp.success) {
                    messageEl.html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>').addClass('success');
                    // Zeige Statusleiste und starte Polling
                    explorerBox.show();
                    explorerStatus.text('API Explorer wird gestartet...');
                    setTimeout(function() {
                        pollV3ExplorerStatus();
                    }, 2000);
                } else {
                    messageEl.html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>').addClass('error');
                    setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
                }
            }).fail(function() {
                messageEl.html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>').addClass('error');
                setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
            });
        });

        // V3 Sync starten
        $('#bes-v3-start-sync').on('click', function() {
            const btn = $(this);
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');

            // Starte Sync
            setButtonState(btn, 'Starte...', { loading: true, disabled: true });

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.post(ajaxurl, {
                action: 'bes_v3_start_sync',
                _ajax_nonce: v3Nonce,
                batch_size: batchSize,
                auto_continue: autoContinue ? 1 : 0
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>');
                    // Zeige Statusleiste und starte Polling
                    syncBox.show();
                    if (resp.data.part && resp.data.total_parts) {
                        syncStatus.text('Starte Durchlauf ' + resp.data.part + ' von ' + resp.data.total_parts + '...');
                    } else {
                        syncStatus.text(resp.data.msg || 'V3 Sync wird gestartet...');
                    }

                    // Setze Button-Text zurück (falls vorher "Fortsetzen" war)
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');

                    // Zeige Stop-Button, verstecke Reset-Button
                    $('#bes-v3-stop-sync').show();
                    $('#bes-v3-reset-sync').hide();

                    // Starte Polling
                    setTimeout(function() {
                        pollV3Status();
                    }, 2000);
                } else {
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
            });
        });

        // V3 Merge
        $('#bes-v3-merge-parts').on('click', function() {
            const btn = $(this);
            setButtonState(btn, 'Führe zusammen...', { loading: true, disabled: true });

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.post(ajaxurl, {
                action: 'bes_v3_merge_parts',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
            });
        });

        // V3 Stop
        $('#bes-v3-stop-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich stoppen?')) {
                return;
            }

            const btn = $(this);
            setButtonState(btn, 'Stoppe...', { loading: true, disabled: true });

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.post(ajaxurl, {
                action: 'bes_v3_stop_sync',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    syncStatus.text(resp.data.message || 'Sync wurde gestoppt');
                    syncStatus.css('color', '#d63638');
                    syncBox.show();

                    // Buttons zurücksetzen
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
                    $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Polling stoppen
                    clearTimeout(pollV3SyncTimer);

                    // Status einmalig aktualisieren
                    setTimeout(pollV3Status, 1000);
                } else {
                    alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
                    setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
                }
            }).fail(function() {
                alert('AJAX-Fehler beim Stoppen');
                setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
            });
        });

        // V3 Consent Audit
        let auditPollTimer = null;

        function pollAuditStatus() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bes_v3_audit_status',
                    _ajax_nonce: v3Nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.running) {
                            // Audit läuft noch - Status anzeigen
                            if (data.status_message) {
                                $('#bes-v3-sync-message').html('<strong style="color: #2271b1;">🔄 ' + data.status_message + '</strong>');
                            }
                            // Nächsten Poll in 3 Sekunden
                            auditPollTimer = setTimeout(pollAuditStatus, 3000);
                        } else if (data.has_result && data.audit) {
                            // Audit abgeschlossen - Ergebnis anzeigen
                            clearTimeout(auditPollTimer);
                            const audit = data.audit;
                            let msg = '✅ Consent-Audit abgeschlossen\n\n';
                            msg += 'Consent-Feld:\n';
                            if (audit.consent_field_meta) {
                                msg += '- ID: ' + audit.consent_field_meta.id + '\n';
                                msg += '- Name: ' + (audit.consent_field_meta.name || 'N/A') + '\n';
                                msg += '- Type: ' + (audit.consent_field_meta.type || 'N/A') + '\n\n';
                            }
                            msg += 'Serverfilter:\n';
                            if (audit.server_filter_counts) {
                                msg += '- A (CURRENT): ' + audit.server_filter_counts.A_CURRENT.count + ' IDs\n';
                                msg += '- B (EXPANDED): ' + audit.server_filter_counts.B_EXPANDED_VALUES.count + ' IDs\n';
                                msg += '- C (NO ACTIVE): ' + audit.server_filter_counts.C_NO_ACTIVE_FILTER.count + ' IDs\n\n';
                            }
                            msg += 'Lokaler Check:\n';
                            if (audit.local_check_results) {
                                msg += '- CHECK_OLD: ' + audit.local_check_results.check_old_count + ' IDs\n';
                                msg += '- CHECK_NEW: ' + audit.local_check_results.check_new_count + ' IDs\n';
                                msg += '- CF nicht gefunden: ' + audit.local_check_results.cf_not_found_count + '\n';
                                msg += '- API-Fehler: ' + audit.local_check_results.member_cf_api_error_count + '\n\n';
                            }
                            msg += 'Differenzliste: ' + (audit.difference_list ? audit.difference_list.count : 0) + ' IDs\n\n';
                            msg += 'Ergebnis gespeichert in: ' + data.audit_file;

                            alert(msg);
                            console.log('Audit-Ergebnis:', audit);

                            $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ Consent-Audit abgeschlossen. Siehe Browser-Konsole für Details.</strong>');
                            $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                            $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                        } else if (data.state === 'error') {
                            // Fehler
                            clearTimeout(auditPollTimer);
                            $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (data.status_message || 'Fehler') + '</strong>');
                            $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                            $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                        } else {
                            // Noch kein Ergebnis - weiter pollen
                            auditPollTimer = setTimeout(pollAuditStatus, 3000);
                        }
                    }
                },
                error: function() {
                    clearTimeout(auditPollTimer);
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler beim Status-Abruf</strong>');
                    $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                    $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                }
            });
        }

        $('#bes-v3-audit-consent').on('click', function() {
            const $btn = $(this);
            setButtonState($btn, '🔍 Consent Audit läuft...', { loading: true, disabled: true });

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'bes_v3_audit_consent',
                    _ajax_nonce: v3Nonce,
                },
                success: function(response) {
                    if (response.success) {
                        $('#bes-v3-sync-message').html('<strong style="color: #2271b1;">🔄 Consent-Audit gestartet – läuft im Hintergrund...</strong>');
                        // Starte Polling
                        setTimeout(function() {
                            pollAuditStatus();
                        }, 2000);
                    } else {
                        alert('❌ Fehler: ' + (response.data.error || 'Unbekannter Fehler'));
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (response.data.error || 'Fehler') + '</strong>');
                        setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
                    }
                },
                error: function() {
                    alert('❌ AJAX-Fehler beim Starten des Consent-Audits');
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                    setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
                }
            });
        });

        // V3 Reset
        $('#bes-v3-reset-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich zurücksetzen? Alle Fortschritte gehen verloren.')) {
                return;
            }

            const btn = $(this);
            setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });

            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.post(ajaxurl, {
                action: 'bes_v3_reset_sync',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    syncStatus.text(resp.data.message || 'Sync wurde zurückgesetzt');
                    syncStatus.css('color', '');
                    syncBox.hide();

                    // Buttons zurücksetzen
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
                    $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').hide();

                    // Polling stoppen
                    clearTimeout(pollV3SyncTimer);

                    // Seite neu laden für vollständige Aktualisierung
                    location.reload();
                } else {
                    alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
                    setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
                }
            }).fail(function() {
                alert('AJAX-Fehler beim Zurücksetzen');
                setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
            });
        });

        // V3 Sync Status Polling mit Statusleiste
        let pollV3SyncTimer = null;
        const syncBar = $('#besV3SyncBarInner');
        const syncStatus = $('#besV3SyncStatus');
        const syncBox = $('#besV3SyncProgress');

        function schedulePollV3Sync(delayMs) {
            clearTimeout(pollV3SyncTimer);
            pollV3SyncTimer = setTimeout(pollV3Status, delayMs || 3000);
        }

        function pollV3Status() {
            const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

            $.get(ajaxurl, {
                action: 'bes_v3_status',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success && resp.data) {
                    const s = resp.data;
                    const progress = Math.max(0, Math.min(100, s.progress || 0));
                    const previousState = syncBox.data('previous-state') || 'idle';
                    const stateChanged = previousState !== s.state;

                    // Aktualisiere Statusleiste
                    syncBar.css('width', progress + '%');
                    syncStatus.text(s.message || 'Kein Status');
                    syncBox.data('previous-state', s.state);

                    if (s.state === 'running') {
                        // Sync läuft
                        syncBox.show();
                        syncStatus.css('color', '');
                        syncBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');

                        // Zeige detaillierte Informationen
                        if (s.current_part && s.total_parts) {
                            syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
                        } else {
                            syncStatus.text(s.message || 'V3 Sync läuft...');
                        }
                        $('#bes-v3-sync-message').html('<strong>🔄 ' + (s.message || 'Sync läuft...') + '</strong>');

                        // Zeige Stop-Button, verstecke Reset-Button
                        $('#bes-v3-stop-sync').show();
                        $('#bes-v3-reset-sync').hide();

                        schedulePollV3Sync(3000);
                    } else if (s.state === 'done') {
                        // Sync abgeschlossen
                        syncBox.show();
                        const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                        syncBar.css('width', finalProgress + '%');
                        syncStatus.css('color', '#00a32a');
                        syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

                        let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
                        if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                            successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
                        }
                        syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + (s.message || 'Sync abgeschlossen') + '</strong>');

                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();

                        // Stoppe Polling komplett nach Abschluss
                        clearTimeout(pollV3SyncTimer);
                        pollV3SyncTimer = null;

                        // Seite neu laden nach 2 Sekunden (nur wenn kein Modal offen ist)
                        setTimeout(function() {
                            // Prüfe ob Feldauswahl-Modal offen ist
                            if ($('#bes-v3-field-selector-modal').length === 0 || !$('#bes-v3-field-selector-modal').is(':visible')) {
                                location.reload();
                            }
                        }, 2000);
                    } else if (s.state === 'error') {
                        // Fehler
                        syncBox.show();
                        syncStatus.css('color', '#d63638');
                        syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');

                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();

                        // Stoppe Polling bei Fehler
                        clearTimeout(pollV3SyncTimer);
                    } else if (s.state === 'cancelled') {
                        // Sync gestoppt
                        syncBox.show();
                        syncStatus.css('color', '#d63638');
                        syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                        syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');

                        // Zeige Reset-Button, verstecke Stop-Button
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();

                        // Stoppe Polling
                        clearTimeout(pollV3SyncTimer);
                    } else if (s.state === 'paused') {
                        // Sync pausiert (Zeitlimit erreicht)
                        syncBox.show();
                        syncStatus.css('color', '#dba617');
                        syncBox.removeClass('bes-sync-running bes-sync-done bes-sync-error').addClass('bes-sync-paused');
                        syncStatus.html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');
                        $('#bes-v3-sync-message').html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');

                        // Zeige Start-Button als "Fortsetzen", verstecke Stop- und Reset-Button
                        $('#bes-v3-start-sync').find('.bes-btn-label').text('▶️ Sync fortsetzen');
                        $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false).show();
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').show();

                        // Stoppe Polling (wird erst wieder gestartet, wenn Sync fortgesetzt wird)
                        clearTimeout(pollV3SyncTimer);
                    } else {
                        // Sync nicht aktiv - verstecke Statusleiste
                        syncBox.hide();
                        $('#bes-v3-sync-message').html('');
                        $('#bes-v3-stop-sync').hide();
                        $('#bes-v3-reset-sync').hide();
                        clearTimeout(pollV3SyncTimer);
                    }
                }
            }).fail(function() {
                // Bei Fehler retry nach 5 Sekunden
                schedulePollV3Sync(5000);
            });
        }

        // V3 Settings speichern (AJAX Handler fehlt noch - wird über POST gemacht)
        $('#bes-v3-batch-size, #bes-v3-auto-continue').on('change', function() {
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');

            // Speichere via Option (temporär, sollte über AJAX gehen)
            // Wird beim Sync-Start gespeichert
        });

        // ============================================================
        // V3 FELDAUSWAHL
        // ============================================================
        const v3Nonce = (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) ? bes_v3_ajax.nonce : bes_sync_ajax.nonce;

        // Feldauswahl-Modal öffnen
        $('#bes-v3-open-field-selector').on('click', function() {
            const btn = $(this);
            setButtonState(btn, 'Lade Katalog...', { loading: true, disabled: true });
            $('#bes-v3-selection-message').html('');

            // Stoppe alle laufenden Polling-Timer, damit sie nicht stören
            clearTimeout(pollV3ExplorerTimer);
            clearTimeout(pollV3SyncTimer);

            // Lade Katalog und Selection parallel
            $.when(
                $.get(ajaxurl, { action: 'bes_v3_load_catalog', _ajax_nonce: v3Nonce }),
                $.get(ajaxurl, { action: 'bes_v3_load_selection', _ajax_nonce: v3Nonce })
            ).done(function(catalogResp, selectionResp) {
                setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });

                if (!catalogResp[0].success || !selectionResp[0].success) {
                    const errorMsg = catalogResp[0].data?.error || selectionResp[0].data?.error || 'Unbekannt';
                    console.error('V3 Feldauswahl Fehler:', catalogResp[0], selectionResp[0]);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Laden: ' + errorMsg + '</strong>');
                    return;
                }

                const catalog = catalogResp[0].data.catalog;
                const selection = selectionResp[0].data.selection;
                const selectedFields = selection.fields || [];

                // Validiere Katalog-Struktur
                if (!catalog || !catalog.fields) {
                    console.error('V3 Katalog ungültig:', catalog);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Katalog hat ungültige Struktur. Bitte Explorer erneut ausführen.</strong>');
                    return;
                }

                // Erstelle Modal
                try {
                    showV3FieldSelectorModal(catalog, selectedFields);
                } catch (e) {
                    console.error('Fehler beim Erstellen des Modals:', e);
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Öffnen des Modals: ' + e.message + '</strong>');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Fehler beim Laden:', textStatus, errorThrown, jqXHR);
                setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });
                $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler: ' + (errorThrown || textStatus) + '</strong>');
            });
        });

        // Selection zurücksetzen
        $('#bes-v3-reset-selection').on('click', function() {
            if (!confirm('Möchten Sie die Feldauswahl wirklich auf die Pflichtfelder zurücksetzen?')) {
                return;
            }

            const btn = $(this);
            setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });

            $.post(ajaxurl, {
                action: 'bes_v3_reset_selection',
                _ajax_nonce: v3Nonce
            }, function(resp) {
                if (resp && resp.success) {
                    $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ ' + (resp.data?.error || 'Fehler') + '</strong>');
                    setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
                }
            }).fail(function() {
                $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
                setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
            });
        });

        // Modal für Feldauswahl
        function showV3FieldSelectorModal(catalog, selectedFields) {
            // Validiere Parameter
            if (!catalog || typeof catalog !== 'object') {
                throw new Error('Katalog ist ungültig oder leer');
            }
            if (!catalog.fields || typeof catalog.fields !== 'object') {
                throw new Error('Katalog enthält keine Felder-Struktur');
            }
            if (!Array.isArray(selectedFields)) {
                selectedFields = [];
            }

            // Entferne vorhandenes Modal falls vorhanden
            $('#bes-v3-field-selector-modal').remove();

            const selectedSet = new Set(selectedFields);
            // Lade requiredFields aus PHP (sollte ein Array sein)
            const requiredFieldsRaw = <?php
                if (defined('BES_V3_REQUIRED_FIELDS')) {
                    echo json_encode(BES_V3_REQUIRED_FIELDS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                } else {
                    echo '["member.id","member.membershipNumber","syncedAt"]';
                }
            ?>;
            // Stelle sicher, dass requiredFields ein Array ist
            let requiredFieldsArray;
            if (Array.isArray(requiredFieldsRaw)) {
                requiredFieldsArray = requiredFieldsRaw;
            } else if (requiredFieldsRaw && typeof requiredFieldsRaw === 'object') {
                // Falls es ein Objekt ist, konvertiere zu Array
                requiredFieldsArray = Object.values(requiredFieldsRaw);
            } else if (requiredFieldsRaw) {
                // Falls es ein einzelner Wert ist, mache ein Array daraus
                requiredFieldsArray = [requiredFieldsRaw];
            } else {
                // Fallback auf Standard-Pflichtfelder
                requiredFieldsArray = ['member.id', 'member.membershipNumber', 'syncedAt'];
            }
            const requiredSet = new Set(requiredFieldsArray);

            let html = '<div id="bes-v3-field-selector-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;overflow-y:auto;padding:20px;">';
            html += '<div style="max-width:900px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #ddd;padding-bottom:15px;">';
            html += '<h2 style="margin:0;">📋 V3 Feldauswahl</h2>';
            html += '<button type="button" id="bes-v3-close-modal" class="button" style="margin-left:20px;">✕ Schließen</button>';
            html += '</div>';

            // Statistik
            html += '<div style="background:#f0f0f1;padding:15px;border-radius:6px;margin-bottom:20px;">';
            html += '<strong>Ausgewählt:</strong> <span id="bes-v3-selected-count">' + selectedFields.length + '</span> Felder | ';
            html += '<strong>Pflichtfelder:</strong> ' + requiredFieldsArray.length + ' (immer enthalten)';
            html += '</div>';

            // Quick Actions
            html += '<div style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
            html += '<button type="button" class="button button-small" id="bes-v3-select-all">Alle auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-deselect-all">Alle abwählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-select-filled">Nur befüllte Felder auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-toggle-filled-only">Nur Felder mit Inhalt anzeigen</button>';
            html += '<input type="text" id="bes-v3-filter-fields" placeholder="Felder filtern..." style="margin-left:auto;min-width:220px;width:250px;padding:5px;">';
            html += '</div>';

            // Felder-Gruppen in gewünschter Reihenfolge:
            // 1) Contact-Felder, 2) Member Custom Fields, 3) Member-Felder, 4) Contact Custom Fields
            const fieldGroups = [
                { key: 'contact', title: '📧 Contact-Felder', fields: catalog.fields?.contact || [] },
                { key: 'member_cf', title: '🏷️ Member Custom Fields', fields: catalog.fields?.member_cf || [] },
                { key: 'member', title: '👤 Member-Felder', fields: catalog.fields?.member || [] },
                { key: 'contact_cf', title: '🏷️ Contact Custom Fields', fields: catalog.fields?.contact_cf || [] }
            ];

            html += '<div style="max-height:500px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:15px;">';

            fieldGroups.forEach(function(group) {
                if (group.fields.length === 0) return;

                html += '<div class="bes-v3-field-group" style="margin-bottom:25px;">';
                html += '<h3 style="margin:0 0 10px 0;font-size:16px;color:#2271b1;border-bottom:1px solid #ddd;padding-bottom:5px;">' + group.title + ' (' + group.fields.length + ')</h3>';

                // Felder innerhalb der Gruppe sortieren:
                // 1) Pflichtfelder, 2) nach Befüllungsgrad (filled_pct) absteigend, 3) Felder mit Beispielen vor Feldern ohne Beispiele, 4) Name alphabetisch
                const sortedFields = (group.fields || []).slice().sort(function(a, b) {
                    const aKey = a.key;
                    const bKey = b.key;
                    const aRequired = requiredSet.has(aKey);
                    const bRequired = requiredSet.has(bKey);
                    if (aRequired !== bRequired) {
                        return aRequired ? -1 : 1;
                    }
                    const aFilled = a.filled_pct || 0;
                    const bFilled = b.filled_pct || 0;
                    if (aFilled !== bFilled) {
                        return bFilled - aFilled;
                    }
                    const aHasExample = Array.isArray(a.example_values) && a.example_values.length > 0;
                    const bHasExample = Array.isArray(b.example_values) && b.example_values.length > 0;
                    if (aHasExample !== bHasExample) {
                        return aHasExample ? -1 : 1;
                    }
                    const aName = (a.meta?.name || a.field_key || aKey || '').toLowerCase();
                    const bName = (b.meta?.name || b.field_key || bKey || '').toLowerCase();
                    if (aName < bName) return -1;
                    if (aName > bName) return 1;
                    return 0;
                });

                sortedFields.forEach(function(field) {
                    const fieldKey = field.key;
                    const isSelected = selectedSet.has(fieldKey);
                    const isRequired = requiredSet.has(fieldKey);
                    const filledPct = field.filled_pct || 0;
                    const fieldName = field.meta?.name || field.field_key || fieldKey;

                    // Beispielwerte extrahieren
                    const exampleValues = field.example_values || [];
                    let exampleText = '';
                    if (exampleValues.length > 0) {
                        // Nimm ersten Beispielwert (oder mehrere, max 2)
                        const examples = exampleValues.slice(0, 2);
                        exampleText = examples.map(function(ex) {
                            // Kürze sehr lange Werte
                            const str = String(ex);
                            if (str.length > 80) {
                                return str.substring(0, 77) + '...';
                            }
                            return str;
                        }).join(' | ');
                    }

                    html += '<div class="bes-v3-field-item" data-field-key="' + escapeHtml(fieldKey) + '" data-filled-pct="' + filledPct + '" style="padding:10px;border-bottom:1px solid #f0f0f1;">';
                    html += '<label style="cursor:pointer;display:flex;align-items:flex-start;">';
                    html += '<input type="checkbox" class="bes-v3-field-checkbox" value="' + escapeHtml(fieldKey) + '" ' +
                        (isSelected ? 'checked' : '') + ' ' + (isRequired ? 'disabled' : '') + ' style="margin-right:10px;margin-top:3px;flex-shrink:0;">';
                    html += '<div style="flex:1;min-width:0;">';
                    // Feldname und ID (leicht ausgegraut)
                    html += '<div style="margin-bottom:4px;">';
                    html += '<span style="color:#666;font-weight:500;">' + escapeHtml(fieldName) + '</span> ';
                    html += '<code style="font-size:11px;color:#999;background:#f5f5f5;padding:2px 4px;border-radius:3px;">' + escapeHtml(fieldKey) + '</code>';
                    if (isRequired) {
                        html += ' <span style="color:#d63638;font-size:11px;margin-left:5px;">(Pflichtfeld)</span>';
                    }
                    html += '</div>';
                    // Beispielinhalt (fett, schwarz)
                    if (exampleText) {
                        html += '<div style="font-weight:bold;color:#000;font-size:13px;margin-top:3px;line-height:1.4;">' + escapeHtml(exampleText) + '</div>';
                    } else {
                        html += '<div style="color:#999;font-size:12px;font-style:italic;margin-top:3px;">Kein Beispiel verfügbar</div>';
                    }
                    // Befüllungsprozentsatz
                    html += '<div style="font-size:11px;color:#666;margin-top:4px;">' + filledPct + '% befüllt</div>';
                    html += '</div>';
                    html += '</label>';
                    html += '</div>';
                });

                html += '</div>';
            });

            html += '</div>'; // End scrollable area

            html += '<div style="margin-top:20px;padding-top:15px;border-top:2px solid #ddd;display:flex;justify-content:space-between;align-items:center;">';
            html += '<div><strong id="bes-v3-final-count">' + selectedFields.length + '</strong> Felder ausgewählt</div>';
            html += '<div>';
            html += '<button type="button" id="bes-v3-cancel-selection" class="button" style="margin-right:10px;">Abbrechen</button>';
            html += '<button type="button" id="bes-v3-save-selection" class="button button-primary">💾 Auswahl speichern</button>';
            html += '</div>';
            html += '</div>';

            html += '</div></div>';

            $('body').append(html);

            // Event Handler
            $('#bes-v3-close-modal, #bes-v3-cancel-selection').on('click', function() {
                $('#bes-v3-field-selector-modal').remove();
            });

            $('#bes-v3-select-all').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', true);
                updateSelectionCount();
            });

            $('#bes-v3-deselect-all').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', false);
                updateSelectionCount();
            });

            $('#bes-v3-select-filled').on('click', function() {
                $('.bes-v3-field-checkbox:not(:disabled)').each(function() {
                    const $item = $(this).closest('.bes-v3-field-item');
                    const filledAttr = $item.data('filled-pct');
                    const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
                    $(this).prop('checked', filledPct > 0);
                });
                updateSelectionCount();
            });

            function applyFieldVisibility() {
                const filter = $('#bes-v3-filter-fields').val().toLowerCase();
                const filledOnly = $('#bes-v3-toggle-filled-only').data('filledOnly') === true;

                $('.bes-v3-field-item').each(function() {
                    const $item = $(this);
                    const text = $item.text().toLowerCase();
                    const matchesText = !filter || text.includes(filter);

                    const filledAttr = $item.data('filled-pct');
                    const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
                    const hasContent = filledPct > 0;

                    const visible = matchesText && (!filledOnly || hasContent);
                    $item.toggle(visible);
                });
            }

            $('#bes-v3-filter-fields').on('input', function() {
                applyFieldVisibility();
            });

            $('#bes-v3-toggle-filled-only').on('click', function() {
                const $btn = $(this);
                const active = $btn.data('filledOnly') === true;
                const makeActive = !active;
                $btn.data('filledOnly', makeActive);
                $btn.toggleClass('button-primary', makeActive);
                applyFieldVisibility();
            });

            $('.bes-v3-field-checkbox').on('change', updateSelectionCount);

            function updateSelectionCount() {
                const count = $('.bes-v3-field-checkbox:checked').length;
                $('#bes-v3-selected-count, #bes-v3-final-count').text(count);
            }

            $('#bes-v3-save-selection').on('click', function() {
                const btn = $(this);
                const selected = $('.bes-v3-field-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                setButtonState(btn, 'Speichere...', { loading: true, disabled: true });

                $.post(ajaxurl, {
                    action: 'bes_v3_save_selection',
                    _ajax_nonce: v3Nonce,
                    fields: selected
                }, function(resp) {
                    if (resp && resp.success) {
                        $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
                        $('#bes-v3-field-selector-modal').remove();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        alert('Fehler: ' + (resp.data?.error || 'Unbekannt'));
                        setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
                    }
                }).fail(function() {
                    alert('AJAX-Fehler beim Speichern');
                    setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
                });
            });
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) {
                return '';
            }
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }
    });
</script>
<?php
// Kein schließendes PHP-Tag - WordPress Best Practice
