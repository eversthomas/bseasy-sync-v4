jQuery(document).ready(function ($) {
  console.log('📅 BES Calendar: Script geladen');
  
  // Prüfe ob Kalender-Container vorhanden sind
  const $calendars = $('.bes-calendar');
  console.log('📅 BES Calendar: Gefundene Kalender-Container:', $calendars.length);
  
  if ($calendars.length === 0) {
    console.warn('⚠️ BES Calendar: Keine Kalender-Container gefunden!');
    return;
  }
  
  // Debug-Informationen ausgeben
  $calendars.each(function(index) {
    const $cal = $(this);
    const calendarId = $cal.data('calendar-id') || 'unbekannt';
    const eventsCount = $cal.data('events-count') || 0;
    const limit = $cal.data('limit') || 10;
    const debug = $cal.data('debug') === true;
    
    console.log(`📅 BES Calendar #${index + 1}:`, {
      id: calendarId,
      events: eventsCount,
      limit: limit,
      debug: debug
    });
    
    // Prüfe ob CSS geladen wurde
    const $firstCard = $cal.find('.bes-event-card').first();
    if ($firstCard.length > 0) {
      const styles = window.getComputedStyle($firstCard[0]);
      const bgColor = styles.backgroundColor;
      console.log(`📅 BES Calendar #${index + 1}: CSS geladen, Hintergrundfarbe:`, bgColor);
    } else {
      console.warn(`⚠️ BES Calendar #${index + 1}: Keine Event-Cards gefunden!`);
    }
  });
  
  // Weiterlesen (konsistent mit Card-Toggle)
  $(document).off("click.besCalendarToggle", ".bes-readmore");
  $(document).on("click.besCalendarToggle", ".bes-readmore", function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $btn = $(this);
    const $card = $btn.closest(".bes-event-card");
    const $fullContent = $card.find(".bes-event-full");
    const isExpanded = $card.hasClass("is-expanded");
    
    // Debug-Info
    const contentText = $fullContent.text().trim();
    const hasContent = contentText.length > 0;
    
    console.log('📅 BES Calendar: Weiterlesen-Button geklickt', {
      card: $card.length,
      fullContent: $fullContent.length,
      hasContent: hasContent,
      contentLength: contentText.length,
      contentPreview: contentText.substring(0, 50),
      isExpanded: isExpanded,
      cardClasses: $card.attr('class')
    });
    
    if (!hasContent) {
      console.warn('⚠️ BES Calendar: Keine Beschreibung vorhanden!');
      // Zeige eine Meldung, wenn keine Beschreibung vorhanden ist
      $btn.text("Keine weiteren Informationen");
      setTimeout(function() {
        $btn.text("Weiterlesen");
      }, 2000);
      return;
    }
    
    if (isExpanded) {
      // Schließen
      $card.removeClass("is-expanded");
      $btn.attr("aria-expanded", "false");
      $btn.text("Weiterlesen");
      console.log('📅 BES Calendar: Card geschlossen');
    } else {
      // Öffnen
      $card.addClass("is-expanded");
      $btn.attr("aria-expanded", "true");
      $btn.text("Weniger anzeigen");
      console.log('📅 BES Calendar: Card geöffnet, Klasse hinzugefügt:', $card.hasClass("is-expanded"));
      
      // Smooth Scroll auf Mobile
      if (window.innerWidth < 768) {
        setTimeout(function() {
          $btn[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
        }, 350);
      }
    }
  });

  // Mehr anzeigen
  $(".bes-load-more").on("click", function () {
    const wrap = $(this).closest(".bes-calendar");
    const hidden = wrap.find(".bes-event-card.hidden").slice(0, 10);
    const hiddenCount = hidden.length;
    hidden.removeClass("hidden");
    if (wrap.find(".bes-event-card.hidden").length === 0) $(this).hide();
    console.log(`📅 BES Calendar: ${hiddenCount} weitere Events angezeigt`);
  });
  
  console.log('✅ BES Calendar: Event-Handler registriert');
});
