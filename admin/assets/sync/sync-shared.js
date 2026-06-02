(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};
    var besSyncUi = window.besSyncUi;

    besSyncUi.setButtonState = function($btn, label, options = {}) {
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

    besSyncUi.getV3Nonce = function () {
        if (typeof bes_v3_ajax !== 'undefined' && bes_v3_ajax.nonce) {
            return bes_v3_ajax.nonce;
        }
        if (typeof bes_sync_ajax !== 'undefined' && bes_sync_ajax.nonce) {
            return bes_sync_ajax.nonce;
        }
        return '';
    };
    besSyncUi.escapeHtml = function(text) {
        if (text === null || text === undefined) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    };

    besSyncUi.stripLeadingCheckmark = function(message) {
        return String(message || '').replace(/^✅\s*/u, '').trim();
    };

    besSyncUi.appendSyncDuration = function(message, status) {
        if (!message) {
            return message;
        }
        if (message.indexOf(' — Dauer: ') !== -1) {
            return message;
        }
        if (!status) {
            return message;
        }
        if (status.duration_human) {
            return message + ' — Dauer: ' + status.duration_human;
        }
        if (status.duration_sec && status.duration_sec > 0) {
            return message + ' — Dauer: ' + status.duration_sec + ' Sek';
        }
        return message;
    };

    besSyncUi.formatSyncSuccessMessage = function(status) {
        const s = status || {};
        let msg = besSyncUi.stripLeadingCheckmark(s.message || 'V3 Sync erfolgreich abgeschlossen');
        if (s.members_with_consent !== null && s.members_with_consent !== undefined && msg.indexOf('Mitglieder') === -1) {
            msg += ' (' + s.members_with_consent.toLocaleString('de-DE') + ' Mitglieder mit Consent)';
        }
        msg = besSyncUi.appendSyncDuration(msg, s);
        return '✅ ' + msg;
    };

})(window);
