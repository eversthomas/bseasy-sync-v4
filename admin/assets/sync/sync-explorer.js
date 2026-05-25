(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};

    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;

    besSyncUi.schedulePollV3Explorer = function (delayMs) {
        clearTimeout(besSyncUi.pollV3ExplorerTimer);
        besSyncUi.pollV3ExplorerTimer = setTimeout(besSyncUi.pollV3ExplorerStatus, delayMs || 3000);
    }

    besSyncUi.pollV3ExplorerStatus = function () {
        const v3Nonce = besSyncUi.getV3Nonce();
        const messageEl = $('#bes-v3-explorer-message');

        $.get(ajaxurl, {
            action: 'bes_v3_status',
            _ajax_nonce: v3Nonce
        }, function(resp) {
            if (resp && resp.success && resp.data) {
                const s = resp.data;
                const progress = Math.max(0, Math.min(100, s.progress || 0));
                const previousState = besSyncUi.explorerBox.data('previous-state') || 'idle';
                const stateChanged = previousState !== s.state;

                // Debug-Ausgabe
                if (typeof console !== 'undefined' && console.log) {
                    console.log('Explorer Status:', { state: s.state, explorer_running: s.explorer_running, progress: progress, message: s.message });
                }

                // Aktualisiere Statusleiste
                besSyncUi.explorerBar.css('width', progress + '%');
                besSyncUi.explorerStatus.text(s.message || 'Kein Status');
                besSyncUi.explorerBox.data('previous-state', s.state);

                if (s.explorer_running || s.state === 'running') {
                    // Explorer läuft
                    besSyncUi.explorerBox.show();
                    besSyncUi.explorerStatus.css('color', '');
                    besSyncUi.explorerBox.removeClass('bes-sync-done bes-sync-error').addClass('bes-sync-running');
                    besSyncUi.explorerStatus.text(s.message || 'API Explorer läuft...');
                    messageEl.html('<strong>🔄 ' + (s.message || 'API Explorer läuft...') + '</strong>');

                    // Zeige Stop-Button falls vorhanden
                    $('#bes-v3-stop-explorer').show();

                    besSyncUi.schedulePollV3Explorer(3000);
                } else if (s.state === 'done') {
                    // Explorer abgeschlossen
                    besSyncUi.explorerBox.show();
                    const finalProgress = Math.max(0, Math.min(100, s.progress || 100));
                    besSyncUi.explorerBar.css('width', finalProgress + '%');
                    besSyncUi.explorerStatus.css('color', '#00a32a');
                    besSyncUi.explorerBox.removeClass('bes-sync-running bes-sync-error').addClass('bes-sync-done');

                    let successMsg = '✅ ' + (s.message || 'API Explorer abgeschlossen');
                    if (s.explorer_field_count !== null && s.explorer_field_count !== undefined) {
                        successMsg += ' (' + s.explorer_field_count.toLocaleString('de-DE') + ' Felder gefunden)';
                    }
                    besSyncUi.explorerStatus.html('<strong style="color: #00a32a;">' + successMsg + '</strong>');
                    messageEl.html('<strong style="color: #00a32a;">✅ ' + (s.message || 'API Explorer abgeschlossen') + '</strong>').addClass('success');

                    $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                    $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-explorer').hide();

                    // Stoppe Polling komplett nach Abschluss
                    clearTimeout(besSyncUi.pollV3ExplorerTimer);

                    // Seite neu laden nach 2 Sekunden, damit Feldkatalog-Prüfung aktualisiert wird
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else if (s.state === 'error') {
                    // Fehler
                    besSyncUi.explorerBox.show();
                    besSyncUi.explorerStatus.css('color', '#d63638');
                    besSyncUi.explorerBox.removeClass('bes-sync-running bes-sync-done').addClass('bes-sync-error');
                    besSyncUi.explorerStatus.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>');
                    messageEl.html('<strong style="color: #d63638;">❌ ' + (s.message || 'Fehler') + '</strong>').addClass('error');

                    $('#bes-v3-run-explorer').find('.bes-btn-label').text('🔍 API Explorer ausführen');
                    $('#bes-v3-run-explorer').removeClass('is-bes-loading').prop('disabled', false);
                    $('#bes-v3-stop-explorer').hide();

                    // Stoppe Polling bei Fehler
                    clearTimeout(besSyncUi.pollV3ExplorerTimer);
                } else {
                    // Explorer nicht mehr aktiv - verstecke Statusleiste und stoppe Polling komplett
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('Explorer Status: nicht aktiv', { state: s.state, explorer_running: s.explorer_running });
                    }
                    besSyncUi.explorerBox.hide();
                    messageEl.html('');
                    clearTimeout(besSyncUi.pollV3ExplorerTimer);
                    besSyncUi.pollV3ExplorerTimer = null; // Stelle sicher, dass Timer null ist
                }
            } else {
                // Response hat keine Daten
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('Explorer Status-Response hat keine Daten:', resp);
                }
                // Retry nach 5 Sekunden
                besSyncUi.schedulePollV3Explorer(5000);
            }
        }).fail(function(xhr, statusText, error) {
            // Bei Fehler retry nach 5 Sekunden
            console.warn('Explorer Status-Abfrage fehlgeschlagen:', { xhr, statusText, error });
            messageEl.html('<strong style="color: #d63638;">⚠️ Status konnte nicht abgerufen werden</strong>');
            besSyncUi.schedulePollV3Explorer(5000);
        });
    }

    window.besSyncUi.initExplorerPolling = function ($) {
        var besSyncUi = window.besSyncUi;
        besSyncUi.pollV3ExplorerTimer = null;
        besSyncUi.explorerBar = $('#besV3ExplorerBarInner');
        besSyncUi.explorerStatus = $('#besV3ExplorerStatus');
        besSyncUi.explorerBox = $('#besV3ExplorerProgress');

        // Prüfe Explorer-Status beim Seitenaufruf
        if (typeof besSyncData !== 'undefined' && besSyncData.explorerRunning) {
            setTimeout(function() {
        besSyncUi.pollV3ExplorerStatus();
            }, 1000);
        }
    };
    window.besSyncUi.initExplorerRunButton = function ($) {
        var besSyncUi = window.besSyncUi;
        // V3 Explorer
        $('#bes-v3-run-explorer').on('click', function() {
            const btn = $(this);
            const sampleSize = parseInt($('#bes-v3-explorer-sample-size').val());
            const freshFromApi = $('#bes-v3-explorer-fresh').is(':checked');
            const messageEl = $('#bes-v3-explorer-message');

            besSyncUi.setButtonState(btn, 'Starte...', { loading: true, disabled: true });
            messageEl.html('').removeClass('error success');

            const v3Nonce = besSyncUi.getV3Nonce();

            $.post(ajaxurl, {
        action: 'bes_v3_run_explorer',
        _ajax_nonce: v3Nonce,
        sample_size: sampleSize,
        fresh_from_api: freshFromApi ? 1 : 0
            }, function(resp) {
        if (resp && resp.success) {
            messageEl.html('<strong style="color: #00a32a;">✅ ' + resp.data.msg + '</strong>').addClass('success');
            // Zeige Statusleiste und starte Polling
            besSyncUi.explorerBox.show();
            besSyncUi.explorerStatus.text('API Explorer wird gestartet...');
            setTimeout(function() {
                besSyncUi.pollV3ExplorerStatus();
            }, 2000);
        } else {
            messageEl.html('<strong style="color: #d63638;">❌ ' + (resp.data.error || 'Fehler') + '</strong>').addClass('error');
            besSyncUi.setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
        }
            }).fail(function() {
        messageEl.html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>').addClass('error');
        besSyncUi.setButtonState(btn, '🔍 API Explorer ausführen', { loading: false, disabled: false });
            });
        });

    };
    });
})(window);
