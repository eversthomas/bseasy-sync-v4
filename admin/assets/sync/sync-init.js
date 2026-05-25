(function (window) {
    'use strict';
    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;
        // Reihenfolge 1:1 wie im ursprünglichen ui-sync.php <script>-Block
        besSyncUi.initExplorerPolling($);
        besSyncUi.initControllerSyncElements($);
        besSyncUi.initControllerPageLoad($);
        besSyncUi.initExplorerRunButton($);
        besSyncUi.initControllerHandlers($);
        besSyncUi.initAudit($);
        besSyncUi.initControllerSettings($);
        besSyncUi.initFieldSelector($);
    });
})(window);
