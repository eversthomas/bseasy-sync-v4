(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};

    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;

        besSyncUi.initAudit = function ($) {
        // V3 Consent Audit
        besSyncUi.auditPollTimer = null;

        besSyncUi.pollAuditStatus = function () {
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
                    besSyncUi.auditPollTimer = setTimeout(besSyncUi.pollAuditStatus, 3000);
                } else if (data.has_result && data.audit) {
                    // Audit abgeschlossen - Ergebnis anzeigen
                    clearTimeout(besSyncUi.auditPollTimer);
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
                    clearTimeout(besSyncUi.auditPollTimer);
                    $('#bes-v3-sync-message').html('<strong style="color: #d63638;">❌ ' + (data.status_message || 'Fehler') + '</strong>');
                    $('#bes-v3-audit-consent').find('.bes-btn-label').text('🔍 Consent Audit');
                    $('#bes-v3-audit-consent').removeClass('is-bes-loading').prop('disabled', false);
                } else {
                    // Noch kein Ergebnis - weiter pollen
                    besSyncUi.auditPollTimer = setTimeout(besSyncUi.pollAuditStatus, 3000);
                }
            }
        },
        error: function() {
            clearTimeout(besSyncUi.auditPollTimer);
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
                    besSyncUi.pollAuditStatus();
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

    };
    });
})(window);
