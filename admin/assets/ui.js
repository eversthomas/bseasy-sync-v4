jQuery(document).ready(function ($) {
  const TAB_KEY = 'bes_active_tab';

  // Debug-Log-Funktion
  function besDebugLog(message, level, context) {
    level = level || 'INFO';
    context = context || 'ui.js';
    
    // Console-Log (für Browser-Debugging)
    if (level === 'ERROR') {
      console.error("BES [" + level + "] [" + context + "]:", message);
    } else {
      console.log("BES [" + level + "] [" + context + "]:", message);
    }
    
    // Datei-Log (per AJAX)
    if (typeof bes_debug_ajax !== 'undefined' && bes_debug_ajax.ajax_url) {
      $.ajax({
        url: bes_debug_ajax.ajax_url,
        method: "POST",
        dataType: "json",
        data: {
          action: "bes_debug_log",
          _ajax_nonce: bes_debug_ajax.nonce,
          message: message,
          level: level,
          context: context
        }
      }).done(function(response) {
        if (!response || !response.success) {
          console.error("BES Debug-Log AJAX Fehler:", response);
        }
      }).fail(function(xhr, status, error) {
        console.error("BES Debug-Log AJAX fehlgeschlagen:", {
          status: status,
          error: error,
          statusCode: xhr.status,
          responseText: xhr.responseText ? xhr.responseText.substring(0, 200) : 'keine Antwort'
        });
      });
    } else {
      console.warn("BES: bes_debug_ajax nicht verfügbar - AJAX-Logging nicht möglich");
    }
  }
  
  besDebugLog("Tab-Script geladen", "INFO", "ui.js");
  
  // Warte kurz, um sicherzustellen dass DOM vollständig geladen ist
  setTimeout(function() {
    const $buttons = $(".bes-tabs button");
    const $tabs = $(".bes-admin .tab");

    // Helper: Tab aktiv schalten
    function setActiveTab(tab) {
      if (!tab) return;
      $(".bes-tabs button").removeClass("active");
      $(".bes-tabs button[data-tab='" + tab + "']").addClass("active");
      $(".bes-admin .tab").removeClass("active");
      $("#tab-" + tab).addClass("active");
    }
    
    besDebugLog("Gefundene Tab-Buttons: " + $buttons.length, "INFO", "ui.js");
    besDebugLog("Gefundene Tab-Sections: " + $tabs.length, "INFO", "ui.js");
    
    // Detaillierte Informationen über Buttons
    $buttons.each(function(index) {
      const $btn = $(this);
      const tab = $btn.data("tab");
      const id = $btn.attr("id") || "keine-ID";
      besDebugLog("Button " + index + ": data-tab='" + tab + "', id='" + id + "', classes='" + $btn.attr("class") + "'", "INFO", "ui.js");
    });
    
    // Detaillierte Informationen über Tabs
    $tabs.each(function(index) {
      const $tab = $(this);
      const id = $tab.attr("id") || "keine-ID";
      const classes = $tab.attr("class") || "keine-Klassen";
      besDebugLog("Tab " + index + ": id='" + id + "', classes='" + classes + "'", "INFO", "ui.js");
    });
    
    if ($buttons.length === 0) {
      besDebugLog("Keine Tab-Buttons gefunden! Prüfe HTML-Struktur.", "ERROR", "ui.js");
      besDebugLog("HTML-Struktur: .bes-tabs vorhanden: " + ($(".bes-tabs").length > 0), "ERROR", "ui.js");
      besDebugLog("HTML-Struktur: .bes-admin vorhanden: " + ($(".bes-admin").length > 0), "ERROR", "ui.js");
      return;
    }
    
    if ($tabs.length === 0) {
      besDebugLog("Keine Tab-Sections gefunden! Prüfe HTML-Struktur.", "ERROR", "ui.js");
      besDebugLog("HTML-Struktur: .bes-admin vorhanden: " + ($(".bes-admin").length > 0), "ERROR", "ui.js");
      besDebugLog("HTML-Struktur: .tab vorhanden: " + ($(".tab").length > 0), "ERROR", "ui.js");
      return;
    }
    
    // Initial: Letzten Tab aus localStorage wiederherstellen, falls vorhanden
    const savedTab = localStorage.getItem(TAB_KEY);
    if (savedTab && $("#tab-" + savedTab).length) {
      setActiveTab(savedTab);
    }

    // Direkte Bindung statt Delegation für bessere Kompatibilität
    $buttons.off("click.bes-tabs").on("click.bes-tabs", function (e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $button = $(this);
      const tab = $button.data("tab");
      const buttonText = $button.text().trim();
      
      besDebugLog("Tab-Button geklickt: '" + buttonText + "', data-tab='" + tab + "'", "INFO", "ui.js");
      
      if (!tab) {
        besDebugLog("Kein data-tab Attribut gefunden auf Button. Button-HTML: " + $button[0].outerHTML, "ERROR", "ui.js");
        return false;
      }
      
      // Tab aktiv schalten
      setActiveTab(tab);
      besDebugLog("Tab erfolgreich gewechselt zu: " + tab, "INFO", "ui.js");
      localStorage.setItem(TAB_KEY, tab);
      
      // Gewählten Tab anzeigen
      const $targetTab = $("#tab-" + tab);
      besDebugLog("Suche Tab-Element: #tab-" + tab + ", Gefunden: " + $targetTab.length, "INFO", "ui.js");
      
      if ($targetTab.length) {
        // Prüfe ob Tab wirklich sichtbar ist (mit längerem Timeout für CSS-Animationen)
        setTimeout(function() {
          const isVisible = $targetTab.is(":visible");
          const hasActive = $targetTab.hasClass("active");
          const computedDisplay = $targetTab.css("display");
          
          // Nur loggen wenn wirklich ein Problem vorliegt (nicht nur Timing)
          if (!isVisible && hasActive && computedDisplay === "none") {
            // Prüfe nochmal nach kurzer Verzögerung (CSS könnte noch laden)
            setTimeout(function() {
              const stillNotVisible = !$targetTab.is(":visible");
              if (stillNotVisible) {
                besDebugLog("WARNUNG: Tab hat 'active' Klasse, ist aber nicht sichtbar! CSS-Problem?", "WARN", "ui.js");
                besDebugLog("Tab computed style display: " + $targetTab.css("display"), "WARN", "ui.js");
              }
            }, 100);
          }
        }, 150);
      } else {
        besDebugLog("Tab-Element nicht gefunden: #tab-" + tab, "ERROR", "ui.js");
        const availableTabs = $(".bes-admin .tab").map(function() { return this.id; }).get();
        besDebugLog("Verfügbare Tab-IDs: " + JSON.stringify(availableTabs), "ERROR", "ui.js");
      }
      
      return false;
    });
    
    besDebugLog("Tab-Event-Handler registriert für " + $buttons.length + " Buttons", "INFO", "ui.js");
  }, 100);

  // Cache leeren
  $("#bes-clear-cache").on("click", function () {
    const btn = $(this);
    const msgDiv = $("#bes-cache-message");
    const labelEl = btn.find(".bes-btn-label");
    const originalLabel = labelEl.length ? labelEl.text() : btn.text();
    
    btn.addClass("is-bes-loading").prop("disabled", true);
    if (labelEl.length) {
      labelEl.text("Lösche...");
    } else {
      btn.text("Lösche...");
    }
    msgDiv.html("").removeClass("notice-success notice-error");
    
    $.ajax({
      url: bes_cache_ajax.ajax_url,
      method: "POST",
      dataType: "json",
      data: {
        action: "bes_clear_cache",
        _ajax_nonce: bes_cache_ajax.nonce,
      },
    })
      .done(function (response) {
        if (response && response.success) {
          msgDiv.html(
            '<div class="notice notice-success is-dismissible"><p>' +
              response.data.message +
              "</p></div>"
          );
          
          // Statistiken aktualisieren
          if (response.data.stats) {
            const stats = response.data.stats;
            let cacheDetails = "Renderer: " + stats.render_cache + ", Map: " + stats.map_cache;
            
            // Select-Options-Cache hinzufügen (wenn vorhanden)
            if (stats.select_options_cache && stats.select_options_cache > 0) {
              cacheDetails += ", Select-Options: " + stats.select_options_cache;
            }
            
            // Rate-Limit-Cache hinzufügen (wenn vorhanden)
            if (stats.rate_limit_cache && stats.rate_limit_cache > 0) {
              cacheDetails += ", Rate-Limit: " + stats.rate_limit_cache;
            }
            
            // Sonstige Cache-Einträge hinzufügen (wenn vorhanden)
            if (stats.other_cache && stats.other_cache > 0) {
              cacheDetails += ", Sonstige: " + stats.other_cache;
            }
            
            // Sichtbare Werte aktualisieren, falls vorhanden
            $("#bes-cache-total").text(stats.total_entries);
            $("#bes-cache-render").text(stats.render_cache);
            $("#bes-cache-map").text(stats.map_cache);
            
            if (stats.select_options_cache !== undefined && $("#bes-cache-select").length) {
              $("#bes-cache-select").text(stats.select_options_cache || 0);
            }
            
            if (stats.rate_limit_cache !== undefined && $("#bes-cache-rate-limit").length) {
              $("#bes-cache-rate-limit").text(stats.rate_limit_cache || 0);
            }
            
            if (stats.other_cache !== undefined && $("#bes-cache-other").length) {
              $("#bes-cache-other").text(stats.other_cache || 0);
            }
            
            if (typeof stats.total_size_mb !== "undefined" && $("#bes-cache-size").length) {
              $("#bes-cache-size").text(stats.total_size_mb);
            }
            
            // Aktualisiere auch die Details-Anzeige
            if ($("#bes-cache-details").length) {
              let detailsHtml = "(Renderer: " + stats.render_cache + ", Map: " + stats.map_cache;
              if (stats.select_options_cache && stats.select_options_cache > 0) {
                detailsHtml += ", Select-Options: " + stats.select_options_cache;
              }
              if (stats.rate_limit_cache && stats.rate_limit_cache > 0) {
                detailsHtml += ", Rate-Limit: " + stats.rate_limit_cache;
              }
              if (stats.other_cache && stats.other_cache > 0) {
                detailsHtml += ", Sonstige: " + stats.other_cache;
              }
              detailsHtml += ")";
              $("#bes-cache-details").html(detailsHtml);
            }
            
            const statsHtml =
              "<p style='margin:5px 0;font-size:13px;'><strong>Einträge:</strong> " +
              stats.total_entries +
              " (" +
              cacheDetails +
              ")</p>";
            msgDiv.append(statsHtml);
          }
        } else {
          const err =
            (response &&
              response.data &&
              response.data.error) ||
            "Fehler beim Leeren des Caches.";
          msgDiv.html(
            '<div class="notice notice-error is-dismissible"><p>' +
              err +
              "</p></div>"
          );
        }
      })
      .fail(function () {
        msgDiv.html(
          '<div class="notice notice-error is-dismissible"><p>AJAX-Fehler beim Leeren des Caches.</p></div>'
        );
      })
      .always(function () {
        btn.removeClass("is-bes-loading").prop("disabled", false);
        if (labelEl.length) {
          labelEl.text(originalLabel);
        } else {
          btn.text(originalLabel);
        }
      });
  });
});
