(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};

    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;

    besSyncUi.schedulePollV3Sync = function (delayMs) {
        clearTimeout(besSyncUi.pollV3SyncTimer);
        besSyncUi.pollV3SyncTimer = setTimeout(besSyncUi.pollV3Status, delayMs || 3000);
    }

    besSyncUi.pollV3Status = function () {
        const v3Nonce = besSyncUi.getV3Nonce();

        $.get(ajaxurl, {
            action: 'bes_v3_status',
            _ajax_nonce: v3Nonce
        }, function(resp) {
            if (resp && resp.success && resp.data) {
                const s = resp.data;
                const progress = Math.max(0, Math.min(100, s.progress || 0));
                const previousState = besSyncUi.syncBox.data('previous-state') || 'idle';
                const stateChanged = previousState !== s.state;

                // Aktualisiere Statusleiste
                besSyncUi.syncBar.css('width', progress + '%');
                besSyncUi.syncStatus.text(s.message || 'Kein Status');
                besSyncUi.syncBox.data('previous-state', s.state);

                if (s.state === 'running') {
                    // Sync läuft
                    besSyncUi.syncBox.show();
                    besSyncUi.syncStatus.css('color', '');
                    besSyncUi.syncBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');

                    // Zeige detaillierte Informationen
                    if (s.current_part && s.total_parts) {
                        besSyncUi.syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
                    } else {
                        besSyncUi.syncStatus.text(s.message || 'V3 Sync läuft...');
                    }
                    $('#bes-v3-sync-message').html('<strong>🔄 ' + (s.message || 'Sync läuft...') + '</strong>');

                    // Zeige Stop-Button, verstecke Reset-Button
                    $('#bes-v3-stop-sync').show();
                    $('#bes-v3-reset-sync').hide();

                    besSyncUi.schedulePollV3Sync(3000);
                } else if (s.state === 'done') {
                    // Sync abgeschlossen
                    besSyncUi.syncBox.show();
                    const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                    besSyncUi.syncBar.css('width', finalProgress + '%');
                    besSyncUi.syncStatus.css('color', '#00a32a');
                    besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

                    let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
                    if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                        successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
                    }
                    besSyncUi.syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                    $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + (s.message || 'Sync abgeschlossen') + '</strong>');

                    // Zeige Reset-Button, verstecke Stop-Button
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Stoppe Polling komplett nach Abschluss
                    clearTimeout(besSyncUi.pollV3SyncTimer);
                    besSyncUi.pollV3SyncTimer = null;

                    // Seite neu laden nach 2 Sekunden (nur wenn kein Modal offen ist)
                    setTimeout(function() {
                        // Prüfe ob Feldauswahl-Modal offen ist
                        if ($('#bes-v3-field-selector-modal').length === 0 || !$('#bes-v3-field-selector-modal').is(':visible')) {
                            location.reload();
                        }
                    }, 2000);
                } else if (s.state === 'error') {
                    // Fehler
                    besSyncUi.syncBox.show();
                    besSyncUi.syncStatus.css('color', '#d63638');
                    besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                    besSyncUi.syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');

                    // Zeige Reset-Button, verstecke Stop-Button
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Stoppe Polling bei Fehler
                    clearTimeout(besSyncUi.pollV3SyncTimer);
                } else if (s.state === 'cancelled') {
                    // Sync gestoppt
                    besSyncUi.syncBox.show();
                    besSyncUi.syncStatus.css('color', '#d63638');
                    besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                    besSyncUi.syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');

                    // Zeige Reset-Button, verstecke Stop-Button
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Stoppe Polling
                    clearTimeout(besSyncUi.pollV3SyncTimer);
                } else if (s.state === 'paused') {
                    // Sync pausiert (Zeitlimit erreicht)
                    besSyncUi.syncBox.show();
                    besSyncUi.syncStatus.css('color', '#dba617');
                    besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-done bes-sync-error').addClass('bes-sync-paused');
                    besSyncUi.syncStatus.html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');
                    $('#bes-v3-sync-message').html('<strong style="color: #dba617;">⏸️ ' + (s.message || 'Sync pausiert (Zeitlimit erreicht)') + '</strong>');

                    // Zeige Start-Button als "Fortsetzen", verstecke Stop- und Reset-Button
                    $('#bes-v3-start-sync').find('.bes-btn-label').text('▶️ Sync fortsetzen');
                    $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false).show();
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').show();

                    // Stoppe Polling (wird erst wieder gestartet, wenn Sync fortgesetzt wird)
                    clearTimeout(besSyncUi.pollV3SyncTimer);
                } else {
                    // Sync nicht aktiv - verstecke Statusleiste
                    besSyncUi.syncBox.hide();
                    $('#bes-v3-sync-message').html('');
                    $('#bes-v3-stop-sync').hide();
                    $('#bes-v3-reset-sync').hide();
                    clearTimeout(besSyncUi.pollV3SyncTimer);
                }
            }
        }).fail(function() {
            // Bei Fehler retry nach 5 Sekunden
            besSyncUi.schedulePollV3Sync(5000);
        });
    }

    window.besSyncUi.initControllerSyncElements = function ($) {
        var besSyncUi = window.besSyncUi;
        // V3 Sync Status Polling mit Statusleiste
        besSyncUi.pollV3SyncTimer = null;
        besSyncUi.syncBar = $('#besV3SyncBarInner');
        besSyncUi.syncStatus = $('#besV3SyncStatus');
        besSyncUi.syncBox = $('#besV3SyncProgress');

    };
    window.besSyncUi.initControllerPageLoad = function ($) {
        var besSyncUi = window.besSyncUi;
        // Prüfe V3 Sync-Status beim Seitenaufruf
        const v3SyncNonce = besSyncUi.getV3Nonce();
        $.get(ajaxurl, {
            action: 'bes_v3_status',
            _ajax_nonce: v3SyncNonce
        }, function(resp) {
            if (resp && resp.success && resp.data) {
        const s = resp.data;
        if (s.state === 'running' && !s.explorer_running) {
            // V3 Sync läuft - starte Polling
            besSyncUi.syncBox.show();
            $('#bes-v3-start-sync').find('.bes-btn-label').text('Läuft...');
            $('#bes-v3-start-sync').addClass('is-bes-loading').prop('disabled', true);
            $('#bes-v3-stop-sync').show();
            $('#bes-v3-reset-sync').hide();

            if (s.current_part && s.total_parts) {
                besSyncUi.syncStatus.text('Durchlauf ' + s.current_part + ' von ' + s.total_parts + ': ' + (s.message || 'Läuft...'));
            } else {
                besSyncUi.syncStatus.text(s.message || 'V3 Sync läuft...');
            }

            besSyncUi.pollV3Status();
        } else if (s.state === 'done' && !s.explorer_running) {
            // V3 Sync ist fertig
            besSyncUi.syncBox.show();
            const progress = Math.max(0, Math.min(100, s.progress || 100));
            besSyncUi.syncBar.css('width', progress + '%');

            let successMsg = '✅ ' + (s.message || 'V3 Sync erfolgreich abgeschlossen');
            if (s.members_with_consent !== null && s.members_with_consent !== undefined) {
                successMsg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
            }
            besSyncUi.syncStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
            besSyncUi.syncStatus.css('color', '#00a32a');
            besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

            $('#bes-v3-stop-sync').hide();
            $('#bes-v3-reset-sync').show();

            // Stoppe Polling komplett nach Abschluss
            clearTimeout(besSyncUi.pollV3SyncTimer);
            besSyncUi.pollV3SyncTimer = null;
        } else if (s.state === 'error' && !s.explorer_running) {
            // V3 Sync hat Fehler
            besSyncUi.syncBox.show();
            besSyncUi.syncStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
            besSyncUi.syncStatus.css('color', '#d63638');
            besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');

            $('#bes-v3-stop-sync').hide();
            $('#bes-v3-reset-sync').show();
        } else if (s.state === 'cancelled' && !s.explorer_running) {
            // V3 Sync wurde gestoppt
            besSyncUi.syncBox.show();
            besSyncUi.syncStatus.html('<strong style="color: #d63638;">⏸️ ' + (s.message || 'Sync gestoppt') + '</strong>');
            besSyncUi.syncStatus.css('color', '#d63638');
            besSyncUi.syncBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');

            $('#bes-v3-stop-sync').hide();
            $('#bes-v3-reset-sync').show();
        }
            }
        }).fail(function() {
            // Bei Fehler einfach verstecken
            besSyncUi.syncBox.hide();
        });

    };
    window.besSyncUi.initControllerHandlers = function ($) {
        var besSyncUi = window.besSyncUi;
        // V3 Sync starten
        $('#bes-v3-start-sync').on('click', function() {
            const btn = $(this);
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');

            // Starte Sync
            besSyncUi.setButtonState(btn, 'Starte...', { loading: true, disabled: true });

            const v3Nonce = besSyncUi.getV3Nonce();

            $.post(ajaxurl, {
        action: 'bes_v3_start_sync',
        _ajax_nonce: v3Nonce,
        batch_size: batchSize,
        auto_continue: autoContinue ? 1 : 0
            }, function(resp) {
        if (resp && resp.success) {
            $('#bes-v3-sync-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>');
            // Zeige Statusleiste und starte Polling
            besSyncUi.syncBox.show();
            if (resp.data.part && resp.data.total_parts) {
                besSyncUi.syncStatus.text('Starte Durchlauf ' + resp.data.part + ' von ' + resp.data.total_parts + '...');
            } else {
                besSyncUi.syncStatus.text(resp.data.msg || 'V3 Sync wird gestartet...');
            }

            // Setze Button-Text zurück (falls vorher "Fortsetzen" war)
            $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');

            // Zeige Stop-Button, verstecke Reset-Button
            $('#bes-v3-stop-sync').show();
            $('#bes-v3-reset-sync').hide();

            // Starte Polling
            setTimeout(function() {
                besSyncUi.pollV3Status();
            }, 2000);
        } else {
            $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>');
            besSyncUi.setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
        }
            }).fail(function() {
        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
        besSyncUi.setButtonState(btn, '✅ V3 Sync starten', { loading: false, disabled: false });
            });
        });

        // V3 Merge
        $('#bes-v3-merge-parts').on('click', function() {
            const btn = $(this);
            besSyncUi.setButtonState(btn, 'Führe zusammen...', { loading: true, disabled: true });

            const v3Nonce = besSyncUi.getV3Nonce();

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
            besSyncUi.setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
        }
            }).fail(function() {
        $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
        besSyncUi.setButtonState(btn, '🔗 V3 Teile zusammenführen', { loading: false, disabled: false });
            });
        });

        // V3 Stop
        $('#bes-v3-stop-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich stoppen?')) {
        return;
            }

            const btn = $(this);
            besSyncUi.setButtonState(btn, 'Stoppe...', { loading: true, disabled: true });

            const v3Nonce = besSyncUi.getV3Nonce();

            $.post(ajaxurl, {
        action: 'bes_v3_stop_sync',
        _ajax_nonce: v3Nonce
            }, function(resp) {
        if (resp && resp.success) {
            besSyncUi.syncStatus.text(resp.data.message || 'Sync wurde gestoppt');
            besSyncUi.syncStatus.css('color', '#d63638');
            besSyncUi.syncBox.show();

            // Buttons zurücksetzen
            $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
            $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
            $('#bes-v3-stop-sync').hide();
            $('#bes-v3-reset-sync').show();

            // Polling stoppen
            clearTimeout(besSyncUi.pollV3SyncTimer);

            // Status einmalig aktualisieren
            setTimeout(besSyncUi.pollV3Status, 1000);
        } else {
            alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
            besSyncUi.setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
        }
            }).fail(function() {
        alert('AJAX-Fehler beim Stoppen');
        besSyncUi.setButtonState(btn, '🛑 V3 Sync stoppen', { loading: false, disabled: false });
            });
        });

        // V3 Consent Audit
        let auditPollTimer = null;

        function pollAuditStatus() {
            const v3Nonce = besSyncUi.getV3Nonce();

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
            besSyncUi.setButtonState($btn, '🔍 Consent Audit läuft...', { loading: true, disabled: true });

            const v3Nonce = besSyncUi.getV3Nonce();

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
                besSyncUi.setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
            }
        },
        error: function() {
            alert('❌ AJAX-Fehler beim Starten des Consent-Audits');
            $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
            besSyncUi.setButtonState($btn, '🔍 Consent Audit', { loading: false, disabled: false });
        }
            });
        });

        // V3 Reset
        $('#bes-v3-reset-sync').on('click', function() {
            if (!confirm('Möchten Sie den V3 Sync wirklich zurücksetzen? Alle Fortschritte gehen verloren.')) {
        return;
            }

            const btn = $(this);
            besSyncUi.setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });

            const v3Nonce = besSyncUi.getV3Nonce();

            $.post(ajaxurl, {
        action: 'bes_v3_reset_sync',
        _ajax_nonce: v3Nonce
            }, function(resp) {
        if (resp && resp.success) {
            besSyncUi.syncStatus.text(resp.data.message || 'Sync wurde zurückgesetzt');
            besSyncUi.syncStatus.css('color', '');
            besSyncUi.syncBox.hide();

            // Buttons zurücksetzen
            $('#bes-v3-start-sync').find('.bes-btn-label').text('✅ V3 Sync starten');
            $('#bes-v3-start-sync').removeClass('is-bes-loading').prop('disabled', false);
            $('#bes-v3-stop-sync').hide();
            $('#bes-v3-reset-sync').hide();

            // Polling stoppen
            clearTimeout(besSyncUi.pollV3SyncTimer);

            // Seite neu laden für vollständige Aktualisierung
            location.reload();
        } else {
            alert('Fehler: ' + (resp.data.error || 'Unbekannt'));
            besSyncUi.setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
        }
            }).fail(function() {
        alert('AJAX-Fehler beim Zurücksetzen');
        besSyncUi.setButtonState(btn, '🔄 V3 Sync zurücksetzen', { loading: false, disabled: false });
            });
        });
    };
    window.besSyncUi.initControllerSettings = function ($) {
        var besSyncUi = window.besSyncUi;
        // V3 Settings speichern (AJAX Handler fehlt noch - wird über POST gemacht)
        $('#bes-v3-batch-size, #bes-v3-auto-continue').on('change', function() {
            const batchSize = parseInt($('#bes-v3-batch-size').val());
            const autoContinue = $('#bes-v3-auto-continue').is(':checked');

            // Speichere via Option (temporär, sollte über AJAX gehen)
            // Wird beim Sync-Start gespeichert
        });
    };
    });
})(window);
