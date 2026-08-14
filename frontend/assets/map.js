/**
 * E2T Members Map - Leaflet.js Integration
 * Zeigt Mitglieder auf einer interaktiven Karte
 */

document.addEventListener('DOMContentLoaded', function () {
  const mapElement = document.getElementById('bes-members-map');
  const dataElement = document.getElementById('bes-map-data');

  if (!mapElement) {
    console.log('E2T Map: Container nicht gefunden');
    return;
  }

  // Versuche Daten zu laden: zuerst aus Script-Tag, dann aus Data-Attributen
  let data = null;
  
  // Methode 1: Script-Tag mit JSON
  if (dataElement && dataElement.textContent) {
    try {
      data = JSON.parse(dataElement.textContent);
      console.log('E2T Map: Daten aus Script-Tag geladen');
    } catch (e) {
      console.warn('E2T Map: Fehler beim Parsen des Script-Tags, versuche Data-Attribute', e);
    }
  }
  
  // Methode 2: Fallback - Data-Attribute
  if (!data && mapElement) {
    try {
      const markersAttr = mapElement.getAttribute('data-map-markers');
      const filtersAttr = mapElement.getAttribute('data-map-filters');
      const settingsAttr = mapElement.getAttribute('data-map-settings');
      const uploadsUrl = mapElement.getAttribute('data-uploads-url') || '';
      
      if (markersAttr && filtersAttr && settingsAttr) {
        data = {
          markers: JSON.parse(markersAttr),
          filterFields: JSON.parse(filtersAttr),
          mapSettings: JSON.parse(settingsAttr),
          uploadsUrl: uploadsUrl
        };
        console.log('E2T Map: Daten aus Data-Attributen geladen');
      }
    } catch (e) {
      console.error('E2T Map: Fehler beim Lesen der Data-Attribute', e);
    }
  }

  if (!data) {
    console.error('E2T Map: Keine Daten gefunden (weder Script-Tag noch Data-Attribute)');
    return;
  }

  try {
    console.log('E2T Map: Daten geladen', data);
    console.log('E2T Map: Marker count:', data.markers ? data.markers.length : 0);
    console.log('E2T Map: Filter fields:', data.filterFields);

    // ======================================
    // 1. Karte initialisieren
    // ======================================
    const mapSettings = data.mapSettings;
    console.log('E2T Map: Settings -', mapSettings);
    const map = L.map('bes-members-map').setView(mapSettings.center, mapSettings.zoom);
    console.log('E2T Map: Karte initialisiert');

    // Speichere Map-Instanz global für Toggle-Funktionalität
    window.besMapInstance = map;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19,
    }).addTo(map);

    // ======================================
    // 2. Marker und Filtering vorbereiten
    // ======================================
    const markers = data.markers;
    const filterFields = data.filterFields;
    let markerClusterGroup = L.markerClusterGroup({
      maxClusterRadius: 80,
      disableClusteringAtZoom: 13,
    });

    // Speichere alle Layer für Filtering
    const allMarkers = {};
    const filterOptions = {};

    // ======================================
    // 3. Filterliste mit Optionen füllen
    // ======================================
    const filterFieldsArray = Array.isArray(filterFields) ? filterFields : [];
    console.log('E2T Map: filterFieldsArray:', filterFieldsArray);

    const countryFilterLabels = data.countryFilterLabels || {};
    const fieldMetaById = {};
    filterFieldsArray.forEach((field) => {
      filterOptions[field.id] = new Set();
      fieldMetaById[field.id] = field;
    });

    markers.forEach((marker) => {
      filterFieldsArray.forEach((field) => {
        const fieldId = field.id;
        if (marker.filters[fieldId]) {
          marker.filters[fieldId].forEach((val) => {
            if (val && val.trim() !== '') {
              filterOptions[fieldId].add(val);
            }
          });
        }
      });
    });

    const filterbar = document.querySelector('.bes-map-filterbar') || document.querySelector('.bes-filterbar');
    const selectFilters = filterbar ? filterbar.querySelectorAll('select') : [];
    const zipInputs = filterbar ? filterbar.querySelectorAll('.bes-filter-zip') : [];
    const cityInputs = filterbar ? filterbar.querySelectorAll('.bes-filter-city') : [];
    const searchInput = filterbar ? filterbar.querySelector('#bes-search-map') : null;
    const resetButton = document.getElementById('bes-reset-map');

    // Sortiere Optionen und fülle Select-Felder (vorher leeren, "Alle" behalten)
    selectFilters.forEach((select) => {
      if (select.classList.contains('bes-radius-select')) {
        return;
      }
      const fieldId = select.getAttribute('data-field');
      if (!fieldId) {
        return;
      }
      const isCountry = fieldMetaById[fieldId] && fieldMetaById[fieldId].countryFilter;
      const options = Array.from(filterOptions[fieldId] || []).sort((a, b) => {
        if (isCountry) {
          const la = countryFilterLabels[a] || a;
          const lb = countryFilterLabels[b] || b;
          return la.localeCompare(lb, 'de', { sensitivity: 'base' });
        }
        return a.localeCompare(b, 'de', { sensitivity: 'base' });
      });

      const firstOption = select.querySelector('option[value=""]');
      select.innerHTML = '';
      if (firstOption) {
        select.appendChild(firstOption);
      } else {
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Alle';
        select.appendChild(defaultOpt);
      }

      options.forEach((option) => {
        const opt = document.createElement('option');
        opt.value = option;
        opt.textContent = isCountry && countryFilterLabels[option] ? countryFilterLabels[option] : option;
        select.appendChild(opt);
      });

      select.addEventListener('change', applyFilters);
    });

    // Event-Listener für Textfelder (PLZ, Stadt) - Live-Filtering während Eingabe
    zipInputs.forEach((input) => {
      input.addEventListener('input', applyFilters);
      input.addEventListener('change', applyFilters);
    });
    
    cityInputs.forEach((input) => {
      input.addEventListener('input', applyFilters);
      input.addEventListener('change', applyFilters);
    });

    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    // ======================================
    // 4. Modal-Container erstellen (global, einmalig)
    // ======================================
    let modal = document.getElementById('bes-map-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'bes-map-modal';
      modal.className = 'bes-map-modal hidden';
      modal.innerHTML = `
        <div class="bes-map-modal-backdrop"></div>
        <div class="bes-map-modal-dialog" role="dialog" aria-modal="true" aria-label="Mitglied">
          <button class="bes-map-modal-close" aria-label="Schließen">×</button>
          <div class="bes-map-modal-content"></div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const modalBackdrop = modal.querySelector('.bes-map-modal-backdrop');
    const modalDialog = modal.querySelector('.bes-map-modal-dialog');
    const modalContent = modal.querySelector('.bes-map-modal-content');
    const modalClose = modal.querySelector('.bes-map-modal-close');

    function openModal(html) {
      modalContent.innerHTML = html;
      modal.classList.remove('hidden');
      document.body.classList.add('bes-modal-open');

      // Fokus setzen
      modalDialog.focus({ preventScroll: true });
    }

    function closeModal() {
      modal.classList.add('hidden');
      modalContent.innerHTML = '';
      document.body.classList.remove('bes-modal-open');
    }

    modalBackdrop.addEventListener('click', closeModal);
    modalClose.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeModal();
      }
    });

    // ======================================
    // 5. Marker erstellen und Click -> Modal öffnen
    // ======================================
    markers.forEach((markerData) => {
      const icon = L.divIcon({
        className: 'bes-marker',
        html: `<div class="bes-marker-icon" title="${markerData.name}">📍</div>`,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
      });

      const marker = L.marker([markerData.lat, markerData.lng], { icon });

      marker.on('click', () => {
        // Modal-HTML aus den gerenderten Bereichen
        const html = createModalContent(markerData);
        openModal(html);
      });

      marker.data = markerData;
      allMarkers[markerData.id] = marker;
      markerClusterGroup.addLayer(marker);
    });

    map.addLayer(markerClusterGroup);
    
    // Speichere ursprüngliche Ansicht für Reset
    const initialView = {
      center: [mapSettings.center[0], mapSettings.center[1]],
      zoom: mapSettings.zoom
    };

    // ======================================
    // 5. Filter anwenden
    // ======================================
    function applyFilters() {
      const selectedFilters = {};
      const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

      // Sammle Select-Filter
      selectFilters.forEach((select) => {
        const fieldId = select.getAttribute('data-field');
        const value = select.value;
        if (value) {
          selectedFilters[fieldId] = { type: 'select', value };
        }
      });

      // Sammle Textfeld-Filter (PLZ und Stadt)
      zipInputs.forEach((input) => {
        const fieldId = input.getAttribute('data-field');
        const value = input.value.trim();
        if (value) {
          selectedFilters[fieldId] = { type: 'zip', value };
        }
      });
      
      cityInputs.forEach((input) => {
        const fieldId = input.getAttribute('data-field');
        const value = input.value.trim();
        if (value) {
          selectedFilters[fieldId] = { type: 'city', value };
        }
      });

      console.log('E2T Map: Filter angewendet', selectedFilters);

      // Durchsuche alle Marker und zeige/verstecke basierend auf Filtern
      markerClusterGroup.clearLayers();

      markers.forEach((markerData) => {
        let matches = true;

        // Globale Suche (in allen Filterwerten)
        if (searchValue) {
          let foundInData = false;
          // Suche in Name und Stadt
          const nameCity = `${markerData.name || ''} ${markerData.city || ''}`.toLowerCase();
          if (nameCity.includes(searchValue)) {
            foundInData = true;
          }
          // Suche in allen Filterwerten
          if (!foundInData) {
            for (const fieldId in markerData.filters) {
              const values = markerData.filters[fieldId] || [];
              if (values.some(v => String(v).toLowerCase().includes(searchValue))) {
                foundInData = true;
                break;
              }
            }
          }
          if (!foundInData) {
            matches = false;
          }
        }

        // Prüfe alle aktiven Filter
        if (matches) {
          for (const fieldId in selectedFilters) {
            const filterDef = selectedFilters[fieldId];
            const selectedValue = String(filterDef.value).toLowerCase();
            const markerValues = markerData.filters[fieldId] || [];

            const valueFound = markerValues.some((v) => {
              if (!v) return false;
              const vStr = String(v).toLowerCase();

              if (filterDef.type === 'zip') {
                return vStr.startsWith(selectedValue);
              }
              
              if (filterDef.type === 'city') {
                return vStr.includes(selectedValue);
              }

              // Select/Default: Gemeinsame Regel mit Liste: exact OR value contains filter
              // Reihenfolge: exact first, dann contains (konsistent mit Liste)
              if (vStr === selectedValue) return true;
              return vStr.includes(selectedValue);
            });

            if (!valueFound) {
              matches = false;
              break;
            }
          }
        }

        if (matches && allMarkers[markerData.id]) {
          markerClusterGroup.addLayer(allMarkers[markerData.id]);
        }
      });
    }

    // ======================================
    // 6. Reset-Button
    // ======================================
    resetButton?.addEventListener('click', function () {
      // Filter zurücksetzen
      selectFilters.forEach((select) => {
        select.value = '';
      });
      zipInputs.forEach((input) => {
        input.value = '';
      });
      cityInputs.forEach((input) => {
        input.value = '';
      });
      if (searchInput) {
        searchInput.value = '';
      }
      
      // Alle Popups schließen
      map.closePopup();
      
      // Karte zur ursprünglichen Ansicht zurücksetzen
      map.setView(initialView.center, initialView.zoom);
      
      // Filter anwenden (zeigt alle Marker wieder)
      applyFilters();
    });

    // ======================================
    // 7. Toggle-Button im Modal (Event-Delegation, konsistent mit Cards)
    // ======================================
    document.addEventListener('click', function (e) {
      const toggleBtn = e.target.closest('.bes-popup-toggle-btn');
      if (!toggleBtn) return;

      e.preventDefault();
      e.stopPropagation();

      const modalRoot = toggleBtn.closest('.bes-marker-modal');
      if (!modalRoot) return;

      const expanded = modalRoot.classList.contains('is-expanded');

      if (expanded) {
        // Schließen
        modalRoot.classList.remove('is-expanded');
        toggleBtn.setAttribute('aria-expanded', 'false');
        toggleBtn.textContent = 'Mehr anzeigen';
      } else {
        // Öffnen
        modalRoot.classList.add('is-expanded');
        toggleBtn.setAttribute('aria-expanded', 'true');
        toggleBtn.textContent = 'Weniger anzeigen';
      }
    });

    // ======================================
    // 8. Modal-Inhalt erstellen (wie Cards-Ansicht, konsistentes Design)
    // ======================================
    function createModalContent(markerData) {
      let html = '<div class="bes-card bes-marker-modal">';

      // Profilbild
      if (markerData.image) {
        const altText = markerData.imageAlt || markerData.name || 'Profilbild';
        html += `<div class="bes-popup-image-wrapper"><img loading="lazy" src="${markerData.image}" alt="${altText}" class="bes-popup-image"></div>`;
      }

      html += '<div class="bes-popup-content">';

      // Above-Bereich (wie Cards)
      if (markerData.popupAbove) {
        html += `<div class="bes-popup-fields-top">${markerData.popupAbove}</div>`;
      }

      // Below-Bereich (wie Cards) - mit Toggle-Button
      if (markerData.popupBelow) {
        html += `<button class="bes-toggle-btn bes-popup-toggle-btn" aria-expanded="false">Mehr anzeigen</button>`;
        html += `<div class="bes-popup-fields-middle">${markerData.popupBelow}</div>`;
      }

      html += '</div></div>';

      return html;
    }

    console.log('E2T Map: Erfolgreich initialisiert mit ' + markers.length + ' Markern');
  } catch (error) {
    console.error('E2T Map: Fehler', error);
  }
});

