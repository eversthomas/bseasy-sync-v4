// DIAGNOSE – entfernen nach Fix
console.log('[BES Calendar] calendar.js Datei geladen');

(function (besCalendarJQuery) {
  if (typeof besCalendarJQuery === 'undefined' || !besCalendarJQuery) {
    console.error('[BES Calendar] jQuery nicht verfügbar – calendar.js abgebrochen');
    return;
  }

  besCalendarJQuery(document).ready(function ($) {
    // DIAGNOSE – entfernen nach Fix
    console.log('[BES Calendar] DOM ready – calendar.js ausgeführt');

    const $calendars = $('.bes-calendar');
    console.log('[BES Calendar] .bes-calendar Container gefunden:', $calendars.length);

    if ($calendars.length === 0) {
      console.warn('[BES Calendar] Kein .bes-calendar im DOM – weitere Logs entfallen');
      return;
    }

    function isCalendarDebug($cal) {
      return $cal && $cal.length && $cal.data('debug') === true;
    }

    function calLog($cal, message, data) {
      if (!isCalendarDebug($cal)) {
        return;
      }
      if (data !== undefined) {
        console.log(message, data);
      } else {
        console.log(message);
      }
    }

    function calWarn($cal, message, data) {
      if (!isCalendarDebug($cal)) {
        return;
      }
      if (data !== undefined) {
        console.warn(message, data);
      } else {
        console.warn(message);
      }
    }

    // Debug-Informationen ausgeben
    $calendars.each(function(index) {
      const $cal = $(this);
      const calendarId = $cal.data('calendar-id') || 'unbekannt';
      const eventsCount = $cal.data('events-count') || 0;
      const limit = $cal.data('limit') || 10;

      calLog($cal, '📅 BES Calendar #' + (index + 1) + ':', {
        id: calendarId,
        events: eventsCount,
        limit: limit,
        debug: true
      });

      const $firstCard = $cal.find('.bes-event-card').first();
      if ($firstCard.length > 0) {
        const styles = window.getComputedStyle($firstCard[0]);
        calLog($cal, '📅 BES Calendar #' + (index + 1) + ': CSS geladen, Hintergrundfarbe:', styles.backgroundColor);
      } else {
        calWarn($cal, '⚠️ BES Calendar #' + (index + 1) + ': Keine Event-Cards gefunden!');
      }

      // DIAGNOSE – entfernen nach Fix: Card-Level-Inspektion pro Event
      if (isCalendarDebug($cal)) {
        $cal.find('.bes-event-card').each(function(cardIndex) {
          const $card = $(this);
          const $fullContent = $card.find('.bes-event-full');
          const rawHtml = $fullContent.html() || '';
          const textContent = $fullContent.text().trim();

          console.log('[BES Calendar] Card #' + (cardIndex + 1) + ' Diagnose:', {
            fullContentPresent: $fullContent.length,
            rawHtmlPreview: rawHtml.substring(0, 200),
            textContent: textContent,
            textLength: textContent.length,
            dataHasDescription: $card.attr('data-has-description'),
            dataDescSource: $card.attr('data-desc-source'),
            dataDescLength: $card.attr('data-desc-length')
          });
        });
      }
    });

    // Weiterlesen (konsistent mit Card-Toggle)
    $(document).off("click.besCalendarToggle", ".bes-readmore");
    $(document).on("click.besCalendarToggle", ".bes-readmore", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const $btn = $(this);
      const $card = $btn.closest(".bes-event-card");
      const $cal = $btn.closest(".bes-calendar");
      const $fullContent = $card.find(".bes-event-full");
      const isExpanded = $card.hasClass("is-expanded");

      const contentText = $fullContent.text().trim();
      const hasContent = contentText.length > 0;

      calLog($cal, '📅 BES Calendar: Weiterlesen-Button geklickt', {
        card: $card.length,
        fullContent: $fullContent.length,
        hasContent: hasContent,
        contentLength: contentText.length,
        contentPreview: contentText.substring(0, 50),
        isExpanded: isExpanded,
        cardClasses: $card.attr('class')
      });

      if (!hasContent) {
        // DIAGNOSE – entfernen nach Fix
        console.error('[BES Calendar] hasContent=false. html:', $fullContent.html());
        calWarn($cal, '⚠️ BES Calendar: Keine Beschreibung vorhanden!');
        $btn.text("Keine weiteren Informationen");
        setTimeout(function() {
          $btn.text("Weiterlesen");
        }, 2000);
        return;
      }

      if (isExpanded) {
        $card.removeClass("is-expanded");
        $btn.attr("aria-expanded", "false");
        $btn.text("Weiterlesen");
        calLog($cal, '📅 BES Calendar: Card geschlossen');
      } else {
        $card.addClass("is-expanded");
        $btn.attr("aria-expanded", "true");
        $btn.text("Weniger anzeigen");
        calLog($cal, '📅 BES Calendar: Card geöffnet, Klasse hinzugefügt:', $card.hasClass("is-expanded"));

        if (window.innerWidth < 768) {
          setTimeout(function() {
            $btn[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
          }, 350);
        }
      }
    });

    // DIAGNOSE – entfernen nach Fix
    console.log('[BES Calendar] Klick-Handler registriert auf', $('.bes-readmore').length, 'Buttons');

    // Mehr anzeigen
    $(".bes-load-more").on("click", function () {
      const wrap = $(this).closest(".bes-calendar");
      const hidden = wrap.find(".bes-event-card.hidden").slice(0, 10);
      const hiddenCount = hidden.length;
      hidden.removeClass("hidden");
      if (wrap.find(".bes-event-card.hidden").length === 0) {
        $(this).hide();
      }
      calLog(wrap, '📅 BES Calendar: ' + hiddenCount + ' weitere Events angezeigt');
    });
  });
})(typeof jQuery !== 'undefined' ? jQuery : undefined);
