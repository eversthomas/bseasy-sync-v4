/**
 * Easy2Transfer Frontend JS (mit Live-PLZ-Filter + Infinite Scroll)
 * ------------------------------------------------
 * - Feld "zip" wird als Texteingabe behandelt (statt Dropdown)
 * - Filter kombinierbar (UND-Logik)
 * - Live-Filterung beim Tippen
 * - Infinite Scroll beim Scrollen
 */

window.addEventListener("load", function () {
  const $ = jQuery;
  console.log("✅ Frontend-Script gestartet");

  const batchSize = 25;
  let currentIndex = batchSize;
  let infiniteScrollEnabled = true;
  let currentObserver = null; // Referenz auf aktuellen Observer für Bereinigung

  // Delay um sicherzustellen dass DOM komplett geladen ist
  setTimeout(function() {
    const $cards = $(".bes-member-card");
    const $grid = $(".bes-members-grid");
    const $btnMore = $("#bes-load-more");

    console.log("⏰ Verzögertes Init - Cards gefunden:", $cards.length);

    // Verstecke den Button, nutzen wir Infinite Scroll
    if ($btnMore.length) $btnMore.hide();

    // ============================================
    // 🧹 Observer-Bereinigung
    // ============================================
    function cleanupObserver() {
      if (currentObserver) {
        currentObserver.disconnect();
        currentObserver = null;
        console.log('🧹 Alten Observer bereinigt');
      }
    }

    // ============================================
    // 🔢 Initialanzeige (erste 25 Karten)
    // ============================================
    function showInitialCards() {
      console.log("📦 Zeige erste", batchSize, "Karten von", $cards.length);
      $cards.hide().slice(0, batchSize).show();
      currentIndex = batchSize;
      
      // Bereinige alte Observer
      cleanupObserver();
      
      // Starte Infinite Scroll, wenn > 25 Mitglieder
      // Übergib $cards als jQuery-Objekt (wird in initInfiniteScroll konvertiert)
      if ($cards.length > batchSize) {
        initInfiniteScroll($cards);
      }
    }

    // ============================================
    // ⬇️ INFINITE SCROLL (Intersection Observer)
    // ============================================
    function initInfiniteScroll(cardsToShow) {
      // Normalisiere cardsToShow: kann jQuery-Objekt oder Array von jQuery-Objekten sein
      let cardsArray;
      let isJQueryObject = false;
      
      if (!cardsToShow) {
        // Fallback: alle Cards
        cardsArray = $cards.toArray(); // Konvertiere jQuery-Objekt zu Array
        isJQueryObject = false;
      } else if (cardsToShow instanceof jQuery) {
        // jQuery-Objekt: konvertiere zu Array
        cardsArray = cardsToShow.toArray();
        isJQueryObject = false;
      } else if (Array.isArray(cardsToShow)) {
        // Bereits ein Array (von jQuery-Objekten)
        cardsArray = cardsToShow;
        isJQueryObject = false;
      } else {
        console.error('❌ Unbekannter Typ für cardsToShow:', typeof cardsToShow);
        return;
      }
      
      const totalCards = cardsArray.length;
      
      if (totalCards <= batchSize || !infiniteScrollEnabled) {
        console.log('❌ Infinite Scroll: Zu wenige Karten oder deaktiviert', totalCards, batchSize, infiniteScrollEnabled);
        return;
      }

      console.log('✅ Initialisiere Infinite Scroll mit', totalCards, 'Karten');

      // Bereinige alten Observer vor Erstellung eines neuen
      cleanupObserver();

      // Entferne alten Marker falls vorhanden
      $('#bes-scroll-marker').remove();

      // Marker-Element am Ende des Grids positionieren
      // WICHTIG: Marker muss im Grid-Kontext sein, daher am Ende des Grids anhängen
      const $marker = $('<div id="bes-scroll-marker" style="height:1px; margin-top:2rem; grid-column: 1 / -1;"></div>');
      $grid.append($marker);
      
      console.log('✅ Marker am Ende des Grids erstellt und positioniert');

      const markerElement = document.getElementById('bes-scroll-marker');
      if (!markerElement) {
        console.error('❌ Marker-Element konnte nicht gefunden werden!');
        return;
      }

      // Erstelle neuen Observer
      currentObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          console.log('👁️ Observer Event:', {
            isIntersecting: entry.isIntersecting,
            currentIndex: currentIndex,
            totalCards: totalCards,
            intersectionRatio: entry.intersectionRatio
          });
          
          if (entry.isIntersecting && currentIndex < totalCards) {
            console.log('🔄 Infinite Scroll: Lade ab Index', currentIndex, 'von', totalCards);
            
            // Zeige nächsten Batch an
            // cardsArray ist ein Array von DOM-Elementen oder jQuery-Objekten
            const nextBatch = cardsArray.slice(currentIndex, currentIndex + batchSize);
            console.log('📦 Nächster Batch:', nextBatch.length, 'Cards');
            
            // Konvertiere zu jQuery-Objekt für fadeIn
            nextBatch.forEach((card, idx) => {
              const $card = card instanceof jQuery ? card : $(card);
              if ($card.length) {
                $card.fadeIn(200);
                console.log(`  ✅ Card ${currentIndex + idx} eingeblendet`);
              } else {
                console.warn(`  ⚠️ Card ${currentIndex + idx} konnte nicht gefunden werden`);
              }
            });
            
            currentIndex += batchSize;

            // Log für Debugging
            if (currentIndex >= totalCards) {
              console.log('✅ Alle Karten geladen!');
              // Optional: Observer disconnecten wenn alle geladen
              // cleanupObserver();
            } else {
              console.log(`📊 Fortschritt: ${currentIndex} von ${totalCards} Cards geladen`);
            }
          }
        });
      }, { 
        rootMargin: '500px', // Startet 500px VOR dem Ende zu laden
        threshold: 0.1 // Triggert schon bei 10% Sichtbarkeit
      });

      currentObserver.observe(markerElement);
      console.log('👁️ Observer gestartet für Marker');
    }

    // Initiale Anzeige
    showInitialCards();

    // ============================================
    // 🧩 Filter- und Suchlogik
    // ============================================
    function initFilters() {
      const $filterbar = $(".bes-filterbar").not(".bes-map-filterbar").first();
      if ($filterbar.length === 0) return;

      const $selects = $filterbar.find("select");
      const $inputs = $filterbar.find(".bes-filter-zip, .bes-filter-city"); // Bereits vorhandene Inputs
      const $search  = $filterbar.find("#bes-search");
      const $reset   = $filterbar.find("#bes-reset");

      console.log(
        "🧭 Filter initialisiert:",
        $selects.length,
        "Dropdowns gefunden,",
        $inputs.length,
        "Input-Felder (PLZ/Stadt) gefunden"
      );

      // ============================================
      // 🏗️ Dropdowns aufbauen, PLZ-Feld ggf. zu Textfeld machen
      // ============================================
      // Debug: Logge alle Feld-IDs für Diagnose
      console.log("🔍 Debug: Gefundene Select-Feld-IDs:", 
        Array.from($selects).map(s => $(s).data("field"))
      );
      console.log("🔍 Debug: Anzahl Karten:", $cards.length);

      $selects.each(function () {
        const $select = $(this);
        // WICHTIG: Feld-ID trimmen und validieren
        let fieldId = String($select.data("field") || "").trim();
        
        // Validierung: Feld-ID darf nicht leer sein
        if (!fieldId) {
          console.warn("⚠️ Select ohne gültiges data-field Attribut gefunden, überspringe");
          return;
        }

        // 🔹 Sonderfall PLZ und Stadt: als Textfelder rendern
        const fieldIdLower = fieldId.toLowerCase();
        const isZipField =
          fieldIdLower.includes("zip") || fieldIdLower.includes("plz");
        const isCityField =
          fieldIdLower.includes("city") || fieldIdLower.includes("stadt") || fieldIdLower.includes("ort");

        if (isZipField || isCityField) {
          const placeholder = isZipField ? "PLZ eingeben …" : "Stadt eingeben …";
          const className = isZipField ? "bes-filter-zip" : "bes-filter-city";
          console.log("✏️ Ersetze Dropdown für", isZipField ? "PLZ" : "Stadt", "-Feld", fieldId, "durch Eingabefeld");
          const $input = $("<input>")
            .attr({
              type: "text",
              placeholder: placeholder,
              class: className,
              "data-field": fieldId, // WICHTIG: echte Feld-ID behalten!
            })
            .on("input", function () {
              applyFilters();
            });

          $select.replaceWith($input);
          return; // nächste Schleife
        }

        // 🔸 normale Dropdowns befüllen (mit data-value, falls vorhanden)
        // Hilfsfunktion: HTML-Tags entfernen und Text extrahieren
        const stripHtml = function(html) {
          if (!html) return "";
          // Erstelle temporäres DOM-Element, um HTML zu parsen
          const tmp = document.createElement("DIV");
          tmp.innerHTML = html;
          return tmp.textContent || tmp.innerText || "";
        };

        // Prüfe ob Select bereits Optionen hat (vom PHP gerendert)
        const existingOptions = $select.find("option").length;
        const hasExistingOptions = existingOptions > 1; // Mehr als nur "Alle"
        
        if (hasExistingOptions) {
          console.log(`✅ Select für ${fieldId} hat bereits ${existingOptions} Optionen vom PHP - prüfe auf Vollständigkeit`);
          // Optional: Validierung ob die Optionen korrekt sind
          // Für jetzt: Wir befüllen trotzdem neu, um sicherzustellen dass alle Werte enthalten sind
        }

        const values = new Set();
        console.groupCollapsed(`⚙️ Baue Dropdown für Feld ${fieldId}`);

        // Zähle wie viele Karten dieses Feld haben
        let cardsWithField = 0;
        let cardsWithoutField = 0;

        // WICHTIG: Nur Werte für DIESES spezifische Feld sammeln
        $cards.each(function () {
          // ROBUST: Verwende filter() statt CSS-Selektor für exakte Übereinstimmung
          // Das funktioniert auch mit Sonderzeichen wie Punkten in der ID (z.B. "cfraw.50799935")
          const $card = $(this);
          const $field = $card.find('.bes-field').filter(function() {
            const fieldDataId = $(this).attr('data-id');
            return fieldDataId === fieldId; // Exakte String-Übereinstimmung
          });
          
          if ($field.length === 0) {
            cardsWithoutField++;
            return; // Feld nicht in dieser Karte gefunden
          }

          cardsWithField++;

          // Prefer data-value (pipe-getrennte Werte)
          const dataVal = $field.attr("data-value") || "";
          if (dataVal) {
            const parts = dataVal.split("|").map(v => {
              // HTML-Tags entfernen und trimmen
              const cleaned = stripHtml(v.trim());
              return cleaned;
            }).filter(Boolean);
            
            parts.forEach(v => {
              // Zusätzliche Validierung: Nur gültige Werte hinzufügen
              if (v && v !== "null" && v !== "undefined" && v !== "[]" && v.trim() !== "") {
                values.add(v);
              }
            });
          } else {
            // Fallback: Textinhalt ohne Label
            const val = $field
              .clone()
              .children("strong")
              .remove()
              .end()
              .text()
              .replace(/^\s*[:\-–]\s*/, "")
              .trim();
            
            if (val && val !== "null" && val !== "undefined" && val !== "[]" && val !== "") {
              values.add(val);
            }
          }
        });

        console.log(`📊 Statistik für ${fieldId}: ${cardsWithField} Karten mit Feld, ${cardsWithoutField} ohne Feld`);

        const sorted = Array.from(values).sort((a, b) =>
          a.localeCompare(b, "de", { sensitivity: "base" })
        );

        console.log(`✅ ${sorted.length} eindeutige Werte für Feld ${fieldId} gefunden`);

        // Leere Select (behalte nur "Alle" Option falls vorhanden)
        const $firstOption = $select.find('option[value=""]').first();
        $select.empty();
        if ($firstOption.length) {
          $select.append($firstOption);
        } else {
          $select.append(`<option value="">Alle</option>`);
        }

        // Füge sortierte Optionen hinzu
        sorted.forEach((val) => {
          // HTML-Escaping für Option-Wert und -Text
          const escapedVal = $("<div>").text(val).html();
          $select.append(`<option value="${escapedVal}">${escapedVal}</option>`);
        });

        console.log(`✅ Dropdown für ${fieldId} befüllt mit ${sorted.length} Optionen`);
        console.groupEnd();
      });

      // ============================================
      // 🔍 Filterlogik anwenden (inkl. PLZ-Textfeld + Suche)
      // ============================================
      function applyFilters() {
        // Hilfsfunktion: HTML-Tags entfernen und Text extrahieren
        const stripHtml = function(html) {
          if (!html) return "";
          const tmp = document.createElement("DIV");
          tmp.innerHTML = html;
          return tmp.textContent || tmp.innerText || "";
        };

        const searchVal = $search.val().toLowerCase().trim();

        // filters = { feldId: { type: 'select'|'zip'|'city', value: '...' }, ... }
        const filters = {};

        // Dropdown-Filter
        $filterbar.find("select").each(function () {
          // WICHTIG: Feld-ID trimmen für exakte Übereinstimmung
          const fieldId = String($(this).data("field") || "").trim();
          if (!fieldId) {
            console.warn("⚠️ Select ohne gültiges data-field Attribut gefunden");
            return;
          }
          const val = $(this).val();
          if (val && val !== "") {
            filters[fieldId] = { type: "select", value: String(val).toLowerCase() };
          }
        });

        // PLZ- und Stadt-Textfelder
        $filterbar.find(".bes-filter-zip, .bes-filter-city").each(function () {
          // WICHTIG: Feld-ID trimmen für exakte Übereinstimmung
          const fieldId = String($(this).data("field") || "").trim();
          if (!fieldId) {
            console.warn("⚠️ Input ohne gültiges data-field Attribut gefunden");
            return;
          }
          const val = $(this).val().toLowerCase().trim();
          if (val) {
            const isZip = $(this).hasClass("bes-filter-zip");
            filters[fieldId] = { type: isZip ? "zip" : "city", value: val };
            console.log(`📍 ${isZip ? "PLZ" : "Stadt"}-Filter gefunden:`, fieldId, "=", val);
          }
        });

        console.group("🧮 applyFilters()");
        console.log("🔎 Aktive Filter:", filters, "| Suchwert:", searchVal);

        const visibleCards = [];

        $cards.each(function () {
          const $card = $(this);
          let visible = true;

          // 1) Globale Freitext-Suche (in allen Bereichen der Card)
          if (searchVal) {
            const cardText = $card.text().toLowerCase();
            if (!cardText.includes(searchVal)) {
              visible = false;
            }
          }

          // 2) Feldbasierte Filter (nur wenn Filter aktiv sind)
          if (visible && Object.keys(filters).length > 0) {
            for (const [fieldId, filter] of Object.entries(filters)) {
              // WICHTIG: Feld-ID trimmen für exakte Übereinstimmung
              const trimmedFieldId = String(fieldId || "").trim();
              if (!trimmedFieldId) {
                console.warn("⚠️ Leere Feld-ID in Filter gefunden, überspringe");
                continue;
              }
              
              // ROBUST: Verwende filter() statt CSS-Selektor für exakte Übereinstimmung
              // Das funktioniert auch mit Sonderzeichen wie Punkten in der ID (z.B. "cfraw.50799935")
              const $fieldEl = $card.find('.bes-field').filter(function() {
                const fieldDataId = $(this).attr('data-id');
                return fieldDataId === trimmedFieldId; // Exakte String-Übereinstimmung
              });
              
              // Wenn Feld nicht existiert UND Filter aktiv ist → Karte ausblenden
              if ($fieldEl.length === 0) {
                visible = false;
                break;
              }

              // Prefer data-value, ansonsten Text
              const rawAttr = $fieldEl.attr("data-value") || "";
              const fieldText = $fieldEl
                .clone()
                .children("strong")
                .remove()
                .end()
                .text()
                .trim()
                .toLowerCase();

              if (filter.type === "zip") {
                // PLZ: startsWith, sowohl auf data-value als auch Text
                const candidates = rawAttr
                  ? rawAttr.split("|").map(v => stripHtml(v.trim()).toLowerCase())
                  : [fieldText];

                const zipMatch = candidates.some(v => v.startsWith(filter.value));
                if (!zipMatch) {
                  visible = false;
                  break;
                }
              } else if (filter.type === "city") {
                // Stadt: includes (wie normale Suche)
                const candidates = rawAttr
                  ? rawAttr.split("|").map(v => stripHtml(v.trim()).toLowerCase())
                  : [fieldText];

                const cityMatch = candidates.some(v => v.includes(filter.value));
                if (!cityMatch) {
                  visible = false;
                  break;
                }
              } else {
                // normale Select-Filter: exakte Übereinstimmung oder Teilübereinstimmung
                // WICHTIG: Bei Select-Filtern sollte exakt verglichen werden
                // HTML-Tags aus data-value entfernen
                const candidates = rawAttr
                  ? rawAttr.split("|").map(v => stripHtml(v.trim()).toLowerCase())
                  : [fieldText];

                // Exakte Übereinstimmung ODER Teilübereinstimmung (für Mehrfachwerte)
                // Gemeinsame Regel mit Map: exact OR value contains filter (kein reverse-contains)
                const hasMatch = candidates.some(v => {
                  // Exakte Übereinstimmung
                  if (v === filter.value) return true;
                  // Teilübereinstimmung: value enthält filter (nicht umgekehrt)
                  if (v.includes(filter.value)) return true;
                  return false;
                });
                
                if (!hasMatch) {
                  visible = false;
                  break;
                }
              }
            }
          }

          if (visible) visibleCards.push($card);
        });

        console.log("📊 Sichtbare Karten:", visibleCards.length);
        console.groupEnd();

        // 3) Anzeige aktualisieren (Infinite Scroll resetten)
        $cards.hide();
        visibleCards.forEach(function($card, i) {
          if (i < batchSize) {
            // Bilder in sichtbaren Cards sofort laden (entferne lazy loading)
            const $img = $card.find('.bes-member-image img');
            if ($img.length) {
              $img.attr('loading', 'eager');
              // Stelle sicher, dass Bild geladen wird (falls es noch nicht geladen wurde)
              const imgElement = $img[0];
              if (imgElement && !imgElement.complete && imgElement.naturalWidth === 0) {
                // Bild noch nicht geladen - trigger load event
                const src = imgElement.src;
                imgElement.src = '';
                imgElement.src = src;
              }
            }
            $card.fadeIn(150);
          }
        });

        // Reset currentIndex für Infinite Scroll
        currentIndex = batchSize;
        infiniteScrollEnabled = visibleCards.length > batchSize;

        // Bereinige alten Observer und Marker
        cleanupObserver();
        $('#bes-scroll-marker').remove();

        // Starte Infinite Scroll mit gefilterten Karten (wenn mehr als batchSize vorhanden)
        // visibleCards ist bereits ein Array von jQuery-Objekten
        if (visibleCards.length > batchSize) {
          initInfiniteScroll(visibleCards);
        }
      }

      // ============================================
      // ⚙️ Events binden
      // ============================================
      // Alte Event-Handler entfernen, um doppelte Handler zu vermeiden
      $search.off("input").on("input", applyFilters);
      
      // Event-Handler für Selects (auch nach dem Ersetzen von PLZ-Feldern)
      $filterbar.find("select").off("change").on("change", applyFilters);
      
      // WICHTIG: Event-Handler für bereits vorhandene Input-Felder (PLZ/Stadt)
      // Diese werden bereits im PHP als Input gerendert und brauchen Event-Handler
      $filterbar.find(".bes-filter-zip, .bes-filter-city").off("input").on("input", applyFilters);
      
      console.log("✅ Event-Handler gebunden für:", {
        search: $search.length,
        selects: $filterbar.find("select").length,
        inputs: $filterbar.find(".bes-filter-zip, .bes-filter-city").length
      });
      
      $reset.on("click", function () {
        $search.val("");
        $filterbar.find("select").val("");
        $filterbar.find(".bes-filter-zip, .bes-filter-city").val("");
        console.log("🔄 Filter zurückgesetzt");
        
        // Marker entfernen (wird neu erstellt in showInitialCards)
        $('#bes-scroll-marker').remove();
        infiniteScrollEnabled = true;
        
        showInitialCards();
      });

      console.log("✅ Filter-Events gebunden");
      
      // Initiale Filterung durchführen (falls bereits Werte gesetzt sind)
      // Dies stellt sicher, dass die Anzeige korrekt ist, auch wenn Filter bereits Werte haben
      applyFilters();
    }

    // ============================================
    // ⏳ Warte auf Filterbar mit Inhalten
    // ============================================
    // Manchmal auf Production braucht es länger, die Filterbar zu rendern
    let waitCounter = 0;
    const maxWait = 100; // 50 Sekunden max (100 * 500ms)
    
    const filterbarWaitInterval = setInterval(() => {
      const $filterbar = $(".bes-filterbar").not(".bes-map-filterbar").first();
      
      if ($filterbar.length === 0) {
        waitCounter++;
        console.log(`⏳ Warte auf Filterbar (${waitCounter * 500}ms)...`);
        
        if (waitCounter >= maxWait) {
          clearInterval(filterbarWaitInterval);
          console.error("❌ Timeout: Filterbar wurde nicht gefunden!");
          initFilters(); // Fallback: versuche trotzdem zu initialisieren
        }
        return;
      }
      
      // Filterbar existiert - überprüfe ob sie Inhalte hat (Selects/Inputs mit Optionen)
      const $selects = $filterbar.find("select");
      const $inputs = $filterbar.find(".bes-filter-zip");
      const hasSelectOptions = $selects.length > 0 && $selects.first().find("option").length > 1;
      const hasZipInput = $inputs.length > 0;
      
      if (hasSelectOptions || hasZipInput) {
        clearInterval(filterbarWaitInterval);
        console.log("✅ Filterbar gefunden mit Inhalten! Initialisiere Filter...");
        initFilters();
      } else {
        waitCounter++;
        console.log(`⏳ Filterbar existiert, aber hat keine Inhalte (${waitCounter * 500}ms). Selects: ${$selects.length}, Options in erstem Select: ${$selects.length > 0 ? $selects.first().find("option").length : 0}`);
        
        if (waitCounter >= maxWait) {
          clearInterval(filterbarWaitInterval);
          console.warn("⚠️ Filterbar-Timeout - versuche trotzdem zu initialisieren");
          initFilters(); // Fallback
        }
      }
    }, 500);

 // ============================================
// ⬇️ Toggle ("Mehr anzeigen" / "Weniger anzeigen") – Moderne Slide-Down Animation
// ============================================

// Initialisierung: Alle Cards starten im geschlossenen Zustand
$(".bes-member-card").each(function () {
  const $card = $(this);
  const $btn = $card.find(".bes-toggle-btn");
  
  if ($btn.length) {
    $btn.attr("aria-expanded", "false");
  }
});

// Event-Delegation für Toggle-Buttons (funktioniert auch für dynamisch hinzugefügte Cards)
// Accordion-Verhalten: Nur eine Card gleichzeitig geöffnet
$(document).off("click.besToggle", ".bes-toggle-btn");
$(document).on("click.besToggle", ".bes-toggle-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();
  
  const $btn = $(this);
  const $card = $btn.closest(".bes-member-card");
  const isExpanded = $card.hasClass("is-expanded");
  
  // Alle Toggle-Buttons innerhalb der Card finden
  const $allButtons = $card.find(".bes-toggle-btn");
  
  if (isExpanded) {
    // Schließen
    $card.removeClass("is-expanded");
    $allButtons.attr("aria-expanded", "false");
    // Texte bleiben unverändert: oberer Button "Mehr anzeigen", unterer Button "Weniger anzeigen"
  } else {
    // Accordion-Verhalten: Alle anderen Cards im selben Grid schließen
    const $grid = $card.closest(".bes-members-grid");
    if ($grid.length > 0) {
      // Alle anderen Cards im Grid finden und schließen
      $grid.find(".bes-member-card.is-expanded").not($card).each(function() {
        const $otherCard = $(this);
        const $otherButtons = $otherCard.find(".bes-toggle-btn");
        $otherCard.removeClass("is-expanded");
        $otherButtons.attr("aria-expanded", "false");
      });
    } else {
      // Fallback: Alle Cards auf der Seite schließen (falls kein Grid vorhanden)
      $(".bes-member-card.is-expanded").not($card).each(function() {
        const $otherCard = $(this);
        const $otherButtons = $otherCard.find(".bes-toggle-btn");
        $otherCard.removeClass("is-expanded");
        $otherButtons.attr("aria-expanded", "false");
      });
    }
    
    // Aktuelle Card öffnen
    $card.addClass("is-expanded");
    $allButtons.attr("aria-expanded", "true");
    // Texte bleiben unverändert: oberer Button "Mehr anzeigen", unterer Button "Weniger anzeigen"
    
    // Smooth Scroll zu Button nach Animation (nur auf Mobile, wenn nötig)
    if (window.innerWidth < 768) {
      setTimeout(function() {
        $btn[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
      }, 350);
    }
  }
});

// Ende des verzögerten Inits
  }, 0);
});