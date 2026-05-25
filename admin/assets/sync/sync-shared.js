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
    }

})(window);
