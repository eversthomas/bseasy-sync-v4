(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};

    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;

    besSyncUi.showSyncCompleteNotification = function (message) {
        // Browser-Benachrichtigung (falls erlaubt)
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('BSEasy Sync abgeschlossen', {
                body: message,
                icon: (typeof besSyncData !== 'undefined' && besSyncData.logoUrl) ? besSyncData.logoUrl : '',
                tag: 'bes-sync-complete',
                requireInteraction: false
            });
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            // Erlaube Benachrichtigungen beim ersten Mal
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    new Notification('BSEasy Sync abgeschlossen', {
                        body: message,
                        icon: (typeof besSyncData !== 'undefined' && besSyncData.logoUrl) ? besSyncData.logoUrl : '',
                        tag: 'bes-sync-complete'
                    });
                }
            });
        }

        // Visuelle Toast-Benachrichtigung
        besSyncUi.showToast('success', '✅ Sync abgeschlossen', message);

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

    besSyncUi.showSyncErrorNotification = function (message) {
        // Browser-Benachrichtigung für Fehler
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('BSEasy Sync Fehler', {
                body: message,
                icon: (typeof besSyncData !== 'undefined' && besSyncData.logoUrl) ? besSyncData.logoUrl : '',
                tag: 'bes-sync-error',
                requireInteraction: true // Fehler sollten Aufmerksamkeit erfordern
            });
        }

        // Visuelle Toast-Benachrichtigung
        besSyncUi.showToast('error', '❌ Sync Fehler', message);
    }

    besSyncUi.showToast = function (type, title, message) {
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

    besSyncUi.updateSyncStatistics = function (statusData) {
        // Aktualisiere die Statistiken oben auf der Seite ohne Seiten-Reload
        if (statusData.members_with_consent !== null && statusData.members_with_consent !== undefined) {
            $('#bes-sync-consent-count').text(statusData.members_with_consent.toLocaleString('de-DE'));
        }

        if (statusData.members_total !== null && statusData.members_total !== undefined) {
            $('#bes-sync-total-count').text(statusData.members_total.toLocaleString('de-DE'));
        }
    }
    });
})(window);
