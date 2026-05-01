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
    const $cards = $(".member-card");
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
      // 🔗 URL-Hash-Sync: Filter-State in URL speichern
      // ============================================
      function updateUrlHash() {
        const parts = [];
        const sv = $filterbar.find("#bes-search").val();
        if (sv && sv.trim()) {
          parts.push("search=" + encodeURIComponent(sv.trim()));
        }
        $filterbar.find("select, .bes-filter-zip, .bes-filter-city").each(function () {
          const fid = String($(this).data("field") || "").trim();
          const val = $(this).val();
          if (fid && val && val.trim()) {
            parts.push("f_" + encodeURIComponent(fid) + "=" + encodeURIComponent(val.trim()));
          }
        });
        if (parts.length) {
          history.replaceState(null, "", "#bes:" + parts.join("|"));
        } else if (window.location.hash.startsWith("#bes:")) {
          history.replaceState(null, "", window.location.pathname + window.location.search);
        }
      }

      function restoreFromUrlHash() {
        const hash = window.location.hash;
        if (!hash.startsWith("#bes:")) return;
        const pairs = hash.slice(5).split("|");
        pairs.forEach(function (pair) {
          const eq = pair.indexOf("=");
          if (eq === -1) return;
          const k = decodeURIComponent(pair.slice(0, eq));
          const v = decodeURIComponent(pair.slice(eq + 1));
          if (!k || !v) return;
          if (k === "search") {
            $filterbar.find("#bes-search").val(v);
          } else if (k.startsWith("f_")) {
            const fid = k.slice(2);
            const $el = $filterbar.find("[data-field]").filter(function () {
              return String($(this).data("field") || "").trim() === fid;
            });
            if ($el.length) {
              let setVal = v;
              if (
                $el.is("select") &&
                $el.attr("data-bes-country-filter") === "1" &&
                window.bes_ajax &&
                bes_ajax.country_filter_alias_normalize
              ) {
                const nv = bes_ajax.country_filter_alias_normalize[v.toLowerCase()];
                if (nv) {
                  setVal = nv;
                }
              }
              $el.val(setVal);
            }
          }
        });
        console.log("🔗 Filter aus URL-Hash wiederhergestellt");
      }

      // ============================================
      // 📍 Radius-Suche
      // ============================================
      let radiusAllowedIds = null; // null = kein Radius-Filter aktiv; Set = Whitelist

      function runRadiusSearch(locationVal, radiusKm) {
        const q = (locationVal || "").trim();
        if (!q || radiusKm <= 0) {
          radiusAllowedIds = null;
          applyFilters();
          return;
        }
        // Alle Karten-IDs (DOM-Reihenfolge): Centroid-Fallback im Backend soll nicht von
        // :visible abhängen (Infinite Scroll / gefilterte Anzeige liefert sonst willkürliche Teilmengen).
        const allMemberIds = [];
        $cards.each(function () {
          const id = String($(this).data("member-id") || "").trim();
          if (id) allMemberIds.push(id);
        });
        $.ajax({
          url: bes_ajax.ajax_url,
          type: "POST",
          data: {
            action: "bes_radius_search",
            nonce: bes_ajax.nonce,
            location: q,
            radius_km: radiusKm,
            center_member_ids: allMemberIds,
          },
          success: function (resp) {
            if (resp.success && Array.isArray(resp.data.member_ids)) {
              radiusAllowedIds = new Set(resp.data.member_ids.map(String));
              console.log("📍 Radius-Filter: " + radiusAllowedIds.size + " Treffer (" + radiusKm + " km)");
            } else {
              radiusAllowedIds = null; // Geocoding fehlgeschlagen → Fallback auf String-Matching
            }
            applyFilters();
          },
          error: function () {
            radiusAllowedIds = null;
            applyFilters();
          },
        });
      }

      // Radius-Dropdown ein-/ausblenden je nach PLZ/Stadt-Eingabe
      function updateRadiusVisibility($input) {
        const fid = String($input.data("field") || "").trim();
        const $wrapper = $filterbar.find(".bes-radius-wrapper").filter(function () {
          return $(this).data("for-field") === fid;
        });
        if (!$wrapper.length) return;
        const v = ($input.val() || "").trim();
        if (v.length > 0) {
          $wrapper.show();
        } else {
          $wrapper.hide();
          // Radius zurücksetzen wenn Feld geleert
          const $rs = $wrapper.find(".bes-radius-select");
          $rs.val("10");
          radiusAllowedIds = null;
        }
      }

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

        // Radius-Selects haben data-for-field statt data-field → überspringen
        if ($select.hasClass("bes-radius-select") || $select.data("for-field") !== undefined) {
          return;
        }

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
        const isCountrySelect = $select.attr("data-bes-country-filter") === "1";
        const countryLabels =
          window.bes_ajax && bes_ajax.country_filter_labels ? bes_ajax.country_filter_labels : {};
        sorted.forEach((val) => {
          const escapedVal = $("<div>").text(val).html();
          const labelText =
            isCountrySelect && countryLabels[val] ? countryLabels[val] : val;
          const escapedLabel = $("<div>").text(labelText).html();
          $select.append(`<option value="${escapedVal}">${escapedLabel}</option>`);
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
          // Radius-Selects überspringen (haben data-for-field, nicht data-field)
          if ($(this).hasClass("bes-radius-select") || $(this).data("for-field") !== undefined) {
            return;
          }
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

              if (filter.type === "zip" || filter.type === "city") {
                // Wenn Radius-Filter aktiv → Geo-Check übernimmt, String-Matching überspringen
                if (radiusAllowedIds !== null) {
                  continue;
                }
                const candidates = rawAttr
                  ? rawAttr.split("|").map(v => stripHtml(v.trim()).toLowerCase())
                  : [fieldText];

                let locationMatch;
                if (filter.type === "zip") {
                  locationMatch = candidates.some(v => v.startsWith(filter.value));
                } else {
                  locationMatch = candidates.some(v => v.includes(filter.value));
                }
                if (!locationMatch) {
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

          // 3) Radius-Filter (server-seitig geocoded)
          if (visible && radiusAllowedIds !== null) {
            const memberId = String($card.data("member-id") || "").trim();
            if (!memberId || !radiusAllowedIds.has(memberId)) {
              visible = false;
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

        // Filter-State in URL-Hash speichern
        updateUrlHash();
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
      $filterbar.find(".bes-filter-zip, .bes-filter-city").off("input").on("input", function () {
        updateRadiusVisibility($(this));
        // Wenn Feld geleert → Radius deaktiviert, sofort filtern
        // Wenn Feld hat Wert → erst bei Radius-Select-Change oder direkt (Exakt-Modus)
        const fid = String($(this).data("field") || "").trim();
        const $rs = $filterbar.find(".bes-radius-select").filter(function () {
          return $(this).data("for-field") === fid;
        });
        const radiusKm = parseInt($rs.val() || "0", 10);
        if (radiusAllowedIds === null || !$(this).val() || !$(this).val().trim()) {
          // Kein Radius aktiv oder Feld leer → direkt filtern (String-Matching)
          applyFilters();
        } else {
          // Radius war aktiv → neue Suche mit aktuellem Ort + bestehendem Radius
          runRadiusSearch($(this).val(), radiusKm);
        }
      });

      // Radius-Dropdown: Suche starten wenn Wert geändert wird
      $filterbar.off("change.besRadius", ".bes-radius-select").on("change.besRadius", ".bes-radius-select", function () {
        const fid = String($(this).data("for-field") || "").trim();
        const $locationInput = $filterbar.find(".bes-filter-zip, .bes-filter-city").filter(function () {
          return String($(this).data("field") || "").trim() === fid;
        });
        const locationVal = $locationInput.val() || "";
        const radiusKm = parseInt($(this).val(), 10) || 0;
        runRadiusSearch(locationVal, radiusKm);
      });

      console.log("✅ Event-Handler gebunden für:", {
        search: $search.length,
        selects: $filterbar.find("select").length,
        inputs: $filterbar.find(".bes-filter-zip, .bes-filter-city").length
      });
      
      $reset.on("click", function () {
        $search.val("");
        $filterbar.find("select").val("");
        $filterbar.find(".bes-filter-zip, .bes-filter-city").val("");
        // Radius-Filter zurücksetzen
        radiusAllowedIds = null;
        $filterbar.find(".bes-radius-wrapper").hide();
        $filterbar.find(".bes-radius-select").val("10");
        // URL-Hash löschen
        if (window.location.hash.startsWith("#bes:")) {
          history.replaceState(null, "", window.location.pathname + window.location.search);
        }
        console.log("🔄 Filter zurückgesetzt");
        
        // Marker entfernen (wird neu erstellt in showInitialCards)
        $('#bes-scroll-marker').remove();
        infiniteScrollEnabled = true;
        
        showInitialCards();
      });

      console.log("✅ Filter-Events gebunden");
      
      // URL-Hash wiederherstellen (falls vorhanden) vor initialer Filterung
      restoreFromUrlHash();

      // PLZ/Stadt aus Hash: Umkreis-Zeile zeigen und 10-km-Suche sofort anwenden
      (function applyInitialLocationRadius() {
        let loc = null;
        let $inp = null;
        $filterbar.find(".bes-filter-zip").each(function () {
          const v = ($(this).val() || "").trim();
          if (v && !loc) {
            loc = v;
            $inp = $(this);
          }
        });
        if (!loc) {
          $filterbar.find(".bes-filter-city").each(function () {
            const v = ($(this).val() || "").trim();
            if (v && !loc) {
              loc = v;
              $inp = $(this);
            }
          });
        }
        if (loc && $inp && $inp.length) {
          updateRadiusVisibility($inp);
          const fid = String($inp.data("field") || "").trim();
          const $rs = $filterbar.find(".bes-radius-select").filter(function () {
            return $(this).data("for-field") === fid;
          });
          const radiusKm = parseInt($rs.val() || "0", 10);
          if (radiusKm > 0) {
            runRadiusSearch(loc, radiusKm);
            return;
          }
        }
        applyFilters();
      })();
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
// ⬇️ Toggle (Expand/Collapse) – Accordion
// ============================================
$(".member-card").each(function () {
  $(this).find(".expand-btn").attr("aria-expanded", "false");
});

$(document).off("click.besToggle", ".expand-btn");
$(document).on("click.besToggle", ".expand-btn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  const $btn  = $(this);
  const $card = $btn.closest(".member-card");
  const isOpen = $card.hasClass("is-open");

  if (isOpen) {
    $card.removeClass("is-open");
    $btn.attr("aria-expanded", "false");
  } else {
    // Accordion: alle anderen Cards im Grid schließen
    const $grid = $card.closest(".bes-members-grid");
    const $others = ($grid.length ? $grid : $(document))
      .find(".member-card.is-open")
      .not($card);

    $others.each(function () {
      $(this).removeClass("is-open");
      $(this).find(".expand-btn").attr("aria-expanded", "false");
    });

    $card.addClass("is-open");
    $btn.attr("aria-expanded", "true");

    if (window.innerWidth < 768) {
      setTimeout(function () {
        $btn[0].scrollIntoView({ behavior: "smooth", block: "nearest" });
      }, 350);
    }
  }
});

// Ende des verzögerten Inits
  }, 0);
});