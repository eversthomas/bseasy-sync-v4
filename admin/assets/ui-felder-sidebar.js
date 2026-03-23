// ui-felder-sidebar.js — Sidebar + Drag&Drop + Favoriten + „Ausgeblendete“ + kompakte, klappbare Felder

jQuery(document).ready(function ($) {
  const msg = $("#bes-message");
  const saveBtn = $("#bes-save-fields");
  const reloadBtn = $("#bes-reload-fields");

  const areas = {
    above: $("#bes-above"),
    below: $("#bes-below"),
    unused: $("#bes-unused"),
  };

  const CATEGORY_LABELS = {
    member: "Mitglied",
    contact: "Kontakt",
    cf: "Mitglied Custom",
    cfraw: "Mitglied Custom (roh)",
    contactcf: "Kontakt Custom",
    contactcfraw: "Kontakt Custom (roh)",
    consent: "Einwilligungen",
    other: "Sonstige Felder",
  };

  let cachedFields = [];
  let activeCategory = null;
  let activeStatusFilter = 'all'; // 'all', 'configured', 'unconfigured'
  let collapseUnused = false;
  let showIgnored = false;
  let frontendOnlyFilter = false; // Nur Frontend-Felder anzeigen

  // ------------------------------------------------------------------
  // 📌 Sidebar entfernt - Filter sind jetzt in der Toolbar
  // ------------------------------------------------------------------
  // Sidebar wird nicht mehr eingefügt, da Filter in der Toolbar sind

  // ------------------------------------------------------------------
  // 🧠 Label-Generierung aus Feld-Daten
  // ------------------------------------------------------------------
  function generateLabelFromField(f) {
    // 1. Bekannte Feldnamen
    const knownFields = {
      'member.id': 'Mitglieds-ID',
      'member.membershipNumber': 'Mitgliedsnummer',
      'contact.firstName': 'Vorname',
      'contact.familyName': 'Nachname',
      'contact.email': 'E-Mail',
      'contact.phone': 'Telefon',
      'contact.city': 'Ort',
      'contact.zip': 'PLZ',
      'cf.50359307': 'Online Angebote',
      'cf.50697357': 'Bereitschaftsdienst',
    };
    
    if (knownFields[f.id]) {
      return knownFields[f.id];
    }
    
    // 2. Aus Beispielwert ableiten
    if (f.example) {
      const exampleStr = Array.isArray(f.example) ? f.example.join(', ') : String(f.example);
      
      // E-Mail Pattern
      if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(exampleStr)) {
        if (f.id.indexOf('email') !== -1) return 'E-Mail';
        return 'E-Mail';
      }
      
      // Telefon Pattern
      if (/^[\d\s\+\-\(\)]+$/.test(exampleStr) && exampleStr.length > 5) {
        if (f.id.indexOf('phone') !== -1 || f.id.indexOf('tel') !== -1) return 'Telefon';
        return 'Telefon';
      }
      
      // URL Pattern
      if (/^https?:\/\//.test(exampleStr)) {
        return 'Website';
      }
      
      // Datum Pattern
      if (/^\d{4}-\d{2}-\d{2}/.test(exampleStr)) {
        return 'Datum';
      }
      
      // Boolean
      if (/^(ja|nein|yes|no|true|false|1|0)$/i.test(exampleStr)) {
        return 'Ja/Nein';
      }
    }
    
    // 3. Aus ID generieren
    let cleanId = f.id;
    const prefixes = ['member.', 'contact.', 'cf.', 'cfraw.', 'contactcf.', 'contactcfraw.', 'consent.'];
    for (const prefix of prefixes) {
      if (cleanId.indexOf(prefix) === 0) {
        cleanId = cleanId.substring(prefix.length);
        break;
      }
    }
    
    // CamelCase zu Label
    let label = cleanId.replace(/([a-z])([A-Z])/g, '$1 $2');
    label = label.replace(/_/g, ' ');
    label = label.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    
    // Typ-Präfix
    const typePrefixes = {
      'member': 'Mitglied: ',
      'contact': 'Kontakt: ',
      'cf': 'Custom Field: ',
      'cfraw': 'Custom Field (roh): ',
      'contactcf': 'Kontakt Custom: ',
      'contactcfraw': 'Kontakt Custom (roh): ',
      'consent': 'Einwilligung: ',
    };
    
    const prefix = typePrefixes[f.type] || '';
    
    // Wenn nur Zahl
    if (/^\d+$/.test(cleanId)) {
      return prefix + 'Feld ' + cleanId;
    }
    
    return prefix + label;
  }

  // ------------------------------------------------------------------
  // ✨ Auto-Label-Generierung
  // ------------------------------------------------------------------
  $("#bes-auto-generate-labels").on("click", function () {
    let count = 0;
    cachedFields.forEach(function (f) {
      // Nur wenn kein Label oder Label = ID
      if (!f.label || f.label === f.id) {
        // Generiere Label basierend auf ID und Beispiel
        const generated = generateLabelFromField(f);
        if (generated && generated !== f.id) {
          f.label = generated;
          count++;
        }
      }
    });
    
    if (count > 0) {
      renderFields();
      msg.text(`✨ ${count} Labels automatisch generiert!`).removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 3000);
    } else {
      msg.text("Alle Felder haben bereits Labels.").removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 3000);
    }
  });

  // ------------------------------------------------------------------
  // 🙈 Unkonfigurierte Felder ausblenden
  // ------------------------------------------------------------------
  $("#bes-hide-unconfigured").on("click", function () {
    let count = 0;
    cachedFields.forEach(function (f) {
      const isConfigured = (f.label && f.label !== f.id) || f.area !== 'unused';
      if (!isConfigured && !f.ignored) {
        f.ignored = true;
        count++;
      }
    });
    
    if (count > 0) {
      renderSidebarCategories();
      renderFields();
      msg.text(`🙈 ${count} unkonfigurierte Felder ausgeblendet.`).removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 3000);
    } else {
      msg.text("Keine unkonfigurierten Felder gefunden.").removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 2000);
    }
  });

  // ------------------------------------------------------------------
  // 👁️ Ausgeblendete Felder wieder anzeigen
  // ------------------------------------------------------------------
  $("#bes-show-hidden").on("click", function () {
    let count = 0;
    cachedFields.forEach(function (f) {
      if (f.ignored) {
        f.ignored = false;
        count++;
      }
    });
    
    if (count > 0) {
      renderSidebarCategories();
      renderFields();
      msg.text(`👁️ ${count} ausgeblendete Felder wieder angezeigt.`).removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 3000);
    } else {
      msg.text("Keine ausgeblendeten Felder gefunden.").removeClass("error").addClass("success");
      setTimeout(() => msg.fadeOut(), 2000);
    }
  });

  // ------------------------------------------------------------------
  // 🧠 Label-Generierung aus Feld-Daten
  // ------------------------------------------------------------------
  function generateLabelFromField(f) {
    // 1. Bekannte Feldnamen
    const knownFields = {
      'member.id': 'Mitglieds-ID',
      'member.membershipNumber': 'Mitgliedsnummer',
      'contact.firstName': 'Vorname',
      'contact.familyName': 'Nachname',
      'contact.email': 'E-Mail',
      'contact.phone': 'Telefon',
      'contact.city': 'Ort',
      'contact.zip': 'PLZ',
      'cf.50359307': 'Online Angebote',
      'cf.50697357': 'Bereitschaftsdienst',
    };
    
    if (knownFields[f.id]) {
      return knownFields[f.id];
    }
    
    // 2. Aus Beispielwert ableiten
    if (f.example) {
      const exampleStr = Array.isArray(f.example) ? f.example.join(', ') : String(f.example);
      
      // E-Mail Pattern
      if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(exampleStr)) {
        if (f.id.indexOf('email') !== -1) return 'E-Mail';
        return 'E-Mail';
      }
      
      // Telefon Pattern
      if (/^[\d\s\+\-\(\)]+$/.test(exampleStr) && exampleStr.length > 5) {
        if (f.id.indexOf('phone') !== -1 || f.id.indexOf('tel') !== -1) return 'Telefon';
        return 'Telefon';
      }
      
      // URL Pattern
      if (/^https?:\/\//.test(exampleStr)) {
        return 'Website';
      }
      
      // Datum Pattern
      if (/^\d{4}-\d{2}-\d{2}/.test(exampleStr)) {
        return 'Datum';
      }
      
      // Boolean
      if (/^(ja|nein|yes|no|true|false|1|0)$/i.test(exampleStr)) {
        return 'Ja/Nein';
      }
    }
    
    // 3. Aus ID generieren
    let cleanId = f.id;
    const prefixes = ['member.', 'contact.', 'cf.', 'cfraw.', 'contactcf.', 'contactcfraw.', 'consent.'];
    for (const prefix of prefixes) {
      if (cleanId.indexOf(prefix) === 0) {
        cleanId = cleanId.substring(prefix.length);
        break;
      }
    }
    
    // CamelCase zu Label
    let label = cleanId.replace(/([a-z])([A-Z])/g, '$1 $2');
    label = label.replace(/_/g, ' ');
    label = label.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    
    // Typ-Präfix
    const typePrefixes = {
      'member': 'Mitglied: ',
      'contact': 'Kontakt: ',
      'cf': 'Custom Field: ',
      'cfraw': 'Custom Field (roh): ',
      'contactcf': 'Kontakt Custom: ',
      'contactcfraw': 'Kontakt Custom (roh): ',
      'consent': 'Einwilligung: ',
    };
    
    const prefix = typePrefixes[f.type] || '';
    
    // Wenn nur Zahl
    if (/^\d+$/.test(cleanId)) {
      return prefix + 'Feld ' + cleanId;
    }
    
    return prefix + label;
  }

  // ------------------------------------------------------------------
  // 🧠 Kategorie aus ID ableiten (Fallback)
  // ------------------------------------------------------------------
  function deriveCategoryFromId(id) {
    if (!id || typeof id !== "string") return "other";
    if (id.indexOf("member.") === 0) return "member";
    if (id.indexOf("contact.") === 0) return "contact";
    if (id.indexOf("cfraw.") === 0) return "cfraw";
    if (id.indexOf("cf.") === 0) return "cf";
    if (id.indexOf("contactcfraw.") === 0) return "contactcfraw";
    if (id.indexOf("contactcf.") === 0) return "contactcf";
    if (id.indexOf("consent.") === 0) return "consent";
    return "other";
  }

  // ------------------------------------------------------------------
  // 🔄 Felder vom Server laden
  // ------------------------------------------------------------------
  function loadFields() {
    msg.text("Lade Felder ...").removeClass("success error");
    saveBtn.prop("disabled", true);

    $.post(
      bes_ajax.ajax_url,
      { action: "bes_get_fields", nonce: bes_ajax.nonce },
      function (response) {
        console.log("bes_get_fields response:", response);

        if (!response || !response.success || !response.data || !response.data.fields) {
          msg
            .text(
              (response && response.data && response.data.message) ||
                "Fehler beim Laden der Felder."
            )
            .addClass("error");
          saveBtn.prop("disabled", false); // WICHTIG: Button wieder aktivieren bei Fehler
          return;
        }

        cachedFields = response.data.fields;

        // Basisaufbereitung / Defaults
        cachedFields.forEach(function (f) {
          if (!f.category) {
            f.category = deriveCategoryFromId(f.id);
          }
          if (!f.category_label) {
            f.category_label = CATEGORY_LABELS[f.category] || f.category;
          }
          if (typeof f.favorite === "undefined") {
            f.favorite = false;
          }
          if (typeof f.ignored === "undefined") {
            f.ignored = false; // neue Eigenschaft für „Ausgeblendete“
          }
        });

        renderSidebarCategories();
        renderFields();
        updateStats();

        msg.text("Felder geladen").addClass("success");
        saveBtn.prop("disabled", false);
      }
    )
    .fail(function (jqXHR, textStatus, errorThrown) {
      console.error("❌ AJAX-Fehler bei bes_get_fields:", {
        status: textStatus,
        error: errorThrown,
        responseText: jqXHR.responseText,
      });

      msg
        .html(
          '<div class="notice notice-error is-dismissible"><p>' +
            "AJAX-Fehler beim Laden der Felder: " +
            (textStatus || "Unbekannter Fehler") +
            "</p></div>"
        )
        .addClass("error");
      saveBtn.prop("disabled", false); // Button wieder aktivieren bei Fehler
    });
  }

  // ------------------------------------------------------------------
  // 🧭 Sidebar-Kategorien rendern
  // ------------------------------------------------------------------
  function renderSidebarCategories() {
    const catUL = $("#bes-categories");
    catUL.empty();

    const categories = {};

    // Kategorien aus den Feldern sammeln
    cachedFields.forEach(function (f) {
      if (!f.category) return;
      if (!categories[f.category]) {
        categories[f.category] = f.category_label || f.category;
      }
    });

    // Zusätzliche „virtuelle“ Kategorien
    categories["favorite"] = "⭐ Favoriten";
    categories["ignored"]  = "🧹 Ausgeblendete";
    categories["none"]     = "Ohne Kategorie";

    Object.keys(categories).forEach(function (key) {
      const label = categories[key];
      const li = $('<li data-cat="' + key + '">' + label + "</li>");
      li.on("click", function () {
        activeCategory = key;
        $("#bes-categories li").removeClass("active");
        li.addClass("active");
        renderFields();
      });
      catUL.append(li);
    });
  }

  // ------------------------------------------------------------------
  // 📊 Statistiken aktualisieren
  // ------------------------------------------------------------------
  function updateStats() {
    const total = cachedFields.length;
    const configured = cachedFields.filter(f => (f.label && f.label !== f.id) || f.area !== 'unused' || f.show).length;
    const inUse = cachedFields.filter(f => f.area === 'above' || f.area === 'below').length;
    
    $("#bes-stat-total").text(total + " Felder");
    $("#bes-stat-configured").text(configured + " konfiguriert");
    $("#bes-stat-in-use").text(inUse + " in Verwendung");
  }

  // ------------------------------------------------------------------
  // 🔍 Filter anwenden (vereinfacht: Suche + Ansicht + Frontend-Only)
  // ------------------------------------------------------------------
  function applyFilters(fields) {
    const search = $("#bes-search").val().toLowerCase();
    const viewFilter = $("#bes-view-filter").val() || 'all';
    const frontendOnly = $("#bes-toggle-frontend-only").is(":checked");

    return fields.filter(function (f) {
      // 0) Status-Filter (konfiguriert/unkonfiguriert)
      if (viewFilter === 'configured') {
        const isConfigured = (f.label && f.label !== f.id) || f.area !== 'unused' || f.show;
        if (!isConfigured) return false;
      } else if (viewFilter === 'unconfigured') {
        const isConfigured = (f.label && f.label !== f.id) || f.area !== 'unused' || f.show;
        if (isConfigured) return false;
      }

      // 1) Ignored-Logik: ignorierte Felder ausblenden (außer wenn explizit angezeigt)
      if (f.ignored && !showIgnored) {
        return false;
      }

      // 2) Frontend-Only Filter: Nur Felder in "above" oder "below"
      if (frontendOnly) {
        if (f.area !== "above" && f.area !== "below") {
          return false;
        }
      }

      // 3) Felder in „above" oder „below" → immer zeigen, sofern nicht ignoriert
      if (f.area === "above" || f.area === "below") {
        return true;
      }

      // 4) Kategorienlogik (nur, wenn wir nicht in „ignored" sind – das ist oben schon erledigt)
      if (activeCategory === "favorite") {
        if (!f.favorite) return false;
      } else if (activeCategory === "none") {
        if (f.category && f.category !== "") return false;
      } else if (
        activeCategory &&
        activeCategory !== "favorite" &&
        activeCategory !== "none" &&
        activeCategory !== "ignored"
      ) {
        if (f.category !== activeCategory) return false;
      }

      // 5) Suchfeld
      if (search) {
        const idMatch =
          f.id && f.id.toLowerCase().indexOf(search) !== -1;
        const labelMatch =
          f.label && f.label.toLowerCase().indexOf(search) !== -1;
        if (!idMatch && !labelMatch) {
          return false;
        }
      }

      return true;
    });
  }

  // ------------------------------------------------------------------
  // 🎨 Felder rendern (kompakt & klappbar)
  // ------------------------------------------------------------------
  function renderFields() {
    $(".bes-sortable").empty();

    const toRender = applyFilters(cachedFields);
    const search = $("#bes-search").val().toLowerCase();

        // Unused-Bereich ggf. einklappen (nur über Toggle steuerbar)
        const $unusedSection = $('[data-area="unused"]');
        if (collapseUnused && !search) {
          $unusedSection.addClass("bes-collapsed");
        } else {
          $unusedSection.removeClass("bes-collapsed");
        }

    toRender.forEach(function (f) {
      const example = f.example
        ? '<div class="bes-example">' + f.example + "</div>"
        : "";

      const favIcon = f.favorite ? "⭐" : "☆";
      const hideIcon = f.ignored ? "🙈" : "👁️"; // Ignoriert / sichtbar
      const labelValue = f.label || "";
      
      // Status-Badge bestimmen
      const isConfigured = (f.label && f.label !== f.id) || f.area !== 'unused' || f.show;
      const isInUse = f.area === 'above' || f.area === 'below';
      let statusBadge = '';
      if (isInUse) {
        statusBadge = '<span class="bes-status-badge bes-status-in-use" title="In Verwendung">✓</span>';
      } else if (isConfigured) {
        statusBadge = '<span class="bes-status-badge bes-status-configured" title="Konfiguriert">○</span>';
      } else {
        statusBadge = '<span class="bes-status-badge bes-status-unconfigured" title="Unkonfiguriert">—</span>';
      }

      // "+ oben" / "+ unten" Buttons nur in rechter Palette (unused)
      const quickAddButtons = f.area === 'unused' ? `
        <div class="bes-quick-add-buttons">
          <button type="button" class="button button-small bes-add-above" title="Zu 'Über dem Button' hinzufügen">+ oben</button>
          <button type="button" class="button button-small bes-add-below" title="Zu 'Unter dem Button' hinzufügen">+ unten</button>
        </div>
      ` : '';

      const card = $(`
        <div class="bes-field-row ${f.ignored ? "bes-ignored" : ""} ${isConfigured ? "bes-configured" : "bes-unconfigured"}" data-id="${f.id}">
          <div class="bes-field-header">
            <span class="drag-handle">☰</span>
            ${statusBadge}
            <button type="button" class="bes-fav-btn" title="Favorit umschalten">${favIcon}</button>
            <button type="button" class="bes-hide-btn" title="Ausblenden / wieder einblenden">${hideIcon}</button>
            <span class="bes-id">${highlightMatch(f.id, search)}</span>
            <div class="bes-header-main">
              <input type="text" class="bes-label" value="${labelValue}" placeholder="Label">
              ${quickAddButtons}
            </div>
            <button type="button" class="bes-toggle-details" aria-expanded="false">Details ▾</button>
          </div>

          <div class="bes-field-body" style="display:none">
            <div class="bes-field-options">
              <label><input type="checkbox" class="bes-show-label" ${f.show_label ? "checked" : ""}> Label anzeigen</label>
              <label><input type="checkbox" class="bes-filterable-field" ${f.filterable ? "checked" : ""}> Filterbar</label>
              <label><input type="checkbox" class="bes-show-in-filterbar" ${f.show_in_filterbar ? "checked" : ""}> In Filterleiste anzeigen</label>
              <label>Filter-Priorität: <input type="number" class="bes-filter-priority" value="${f.filter_priority !== undefined && f.filter_priority !== null ? f.filter_priority : ""}" placeholder="0" min="0" step="1" style="width: 80px;"></label>
              <label>Gruppe: <input type="text" class="bes-inline-group" value="${f.inline_group || ""}"></label>
            </div>
            ${example}
          </div>
        </div>
      `);

      // "+ oben" Button Handler
      card.find(".bes-add-above").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const field = cachedFields.find((x) => x.id === f.id);
        if (!field) return;
        
        // Bestimme nächste order (nach letztem Feld in "above")
        const aboveFields = cachedFields.filter(x => x.area === 'above');
        const maxOrder = aboveFields.length > 0 
          ? Math.max(...aboveFields.map(x => x.order || 0))
          : 0;
        
        field.area = 'above';
        field.order = maxOrder + 1;
        renderFields();
        msg.text(`✓ Feld "${field.label || field.id}" zu "Über dem Button" hinzugefügt`).removeClass("error").addClass("success");
        setTimeout(() => msg.fadeOut(), 2000);
      });

      // "+ unten" Button Handler
      card.find(".bes-add-below").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const field = cachedFields.find((x) => x.id === f.id);
        if (!field) return;
        
        // Bestimme nächste order (nach letztem Feld in "below")
        const belowFields = cachedFields.filter(x => x.area === 'below');
        const maxOrder = belowFields.length > 0 
          ? Math.max(...belowFields.map(x => x.order || 0))
          : 0;
        
        field.area = 'below';
        field.order = maxOrder + 1;
        renderFields();
        msg.text(`✓ Feld "${field.label || field.id}" zu "Unter dem Button" hinzugefügt`).removeClass("error").addClass("success");
        setTimeout(() => msg.fadeOut(), 2000);
      });

      // ⭐ Favorit toggeln
      card.find(".bes-fav-btn").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const field = cachedFields.find((x) => x.id === f.id);
        if (!field) return;
        field.favorite = !field.favorite;
        renderSidebarCategories();
        renderFields();
      });

      // 🧹 Ignoriert / Ausgeblendet toggeln
      card.find(".bes-hide-btn").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const field = cachedFields.find((x) => x.id === f.id);
        if (!field) return;
        field.ignored = !field.ignored;
        renderSidebarCategories();
        renderFields();
      });

      // ✨ Quick-Label generieren
      card.find(".bes-quick-label-btn").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const field = cachedFields.find((x) => x.id === f.id);
        if (!field) return;
        
        const generated = generateLabelFromField(field);
        if (generated && generated !== field.id) {
          field.label = generated;
          card.find(".bes-label").val(generated);
          renderFields();
          msg.text("✨ Label generiert: " + generated).removeClass("error").addClass("success");
          setTimeout(() => msg.fadeOut(), 2000);
        }
      });

      // 🔽 Details auf-/zuklappen
      card.find(".bes-toggle-details").on("click", function (e) {
        e.preventDefault();
        const btn = $(this);
        const body = card.find(".bes-field-body");
        const isOpen = body.is(":visible");
        body.slideToggle(150);
        btn.attr("aria-expanded", !isOpen);
        btn.text(!isOpen ? "Details ▴" : "Details ▾");
      });

      // Bestimme Zielbereich: Felder ohne area oder mit area !== 'above'/'below' → unused
      let areaKey = f.area;
      if (!areaKey || (areaKey !== 'above' && areaKey !== 'below')) {
        areaKey = 'unused';
      }
      
      const target = $("#bes-" + areaKey);
      if (target.length) {
        target.append(card);
      } else {
        // Fallback: immer in unused
        $("#bes-unused").append(card);
      }
    });

    initSortable();
    bindLiveFieldEditors();
    updateStats();
  }

  function bindLiveFieldEditors() {
    $(".bes-label")
      .off("input")
      .on("input", function () {
        const rowId = $(this).closest(".bes-field-row").data("id");
        const field = cachedFields.find((f) => f.id === rowId);
        if (field) {
          field.label = $(this).val();
        }
      });

    $(".bes-show-label")
      .off("change")
      .on("change", function () {
        const row = $(this).closest(".bes-field-row");
        const field = cachedFields.find((f) => f.id === row.data("id"));
        if (field) {
          field.show_label = row.find(".bes-show-label").is(":checked");
        }
      });
    
    // Event-Listener für filterable Checkbox
    $(document).off("change", ".bes-filterable-field").on("change", ".bes-filterable-field", function() {
      const row = $(this).closest(".bes-field-row");
      const fieldId = row.data("id");
      const field = cachedFields.find(f => f.id === fieldId);
      if (field) {
        field.filterable = $(this).is(":checked");
      }
    });

    // Event-Listener für "In Filterleiste anzeigen"
    $(document).off("change", ".bes-show-in-filterbar").on("change", ".bes-show-in-filterbar", function() {
      const row = $(this).closest(".bes-field-row");
      const fieldId = row.data("id");
      const field = cachedFields.find(f => f.id === fieldId);
      if (field) {
        field.show_in_filterbar = $(this).is(":checked");
      }
    });

    // Event-Listener für Filter-Priorität
    $(document).off("input change", ".bes-filter-priority").on("input change", ".bes-filter-priority", function() {
      const row = $(this).closest(".bes-field-row");
      const fieldId = row.data("id");
      const field = cachedFields.find(f => f.id === fieldId);
      if (field) {
        const val = $(this).val();
        field.filter_priority = val !== "" ? parseInt(val, 10) : null;
      }
    });

    $(".bes-inline-group")
      .off("input")
      .on("input", function () {
        const rowId = $(this).closest(".bes-field-row").data("id");
        const field = cachedFields.find((f) => f.id === rowId);
        if (field) {
          field.inline_group = $(this).val().trim();
        }
      });
  }

  // ------------------------------------------------------------------
  // 🧲 Sortable initialisieren
  // ------------------------------------------------------------------
  function initSortable() {
    Object.keys(areas).forEach(function (key) {
      const $el = areas[key];
      if (!$el.length) return;

      if ($el.data("sortable-init")) return;
      $el.data("sortable-init", true);

      new Sortable($el[0], {
        group: "fields",
        animation: 150,
        handle: ".drag-handle",
        ghostClass: "dragging",

        onEnd: function (evt) {
          const fieldId = $(evt.item).data("id");
          const newArea = $(evt.to).closest(".bes-section").data("area");

          const field = cachedFields.find(function (f) {
            return f.id === fieldId;
          });
          if (field) {
            field.area = newArea;
          }

          $(evt.to)
            .children(".bes-field-row")
            .each(function (index) {
              const id = $(this).data("id");
              const f = cachedFields.find(function (x) {
                return x.id === id;
              });
              if (f) {
                f.order = index + 1;
              }
            });

          console.log("Drag&Drop:", fieldId, "→", newArea);
        },
      });
    });
  }

  // ------------------------------------------------------------------
  // 🔄 Live-Filter (vereinfacht)
  // ------------------------------------------------------------------
  $(document).on(
    "input change",
    "#bes-search, #bes-view-filter, #bes-toggle-frontend-only",
    function () {
      renderFields();
    }
  );

  // Reset-Button
  $("#bes-reset-filters").on("click", function () {
    $("#bes-search").val("");
    $("#bes-view-filter").val("all");
    $("#bes-toggle-frontend-only").prop("checked", false);
    activeStatusFilter = 'all';
    frontendOnlyFilter = false;
    renderFields();
    msg.text("Filter zurückgesetzt").removeClass("error").addClass("success");
    setTimeout(() => msg.fadeOut(), 2000);
  });

  // View-Filter Handler (Legacy-Kompatibilität)
  $("#bes-view-filter").on("change", function () {
    activeStatusFilter = $(this).val() || 'all';
    renderFields();
  });

  // Frontend-Only Toggle Handler
  $("#bes-toggle-frontend-only").on("change", function () {
    frontendOnlyFilter = $(this).is(":checked");
    renderFields();
  });

// ------------------------------------------------------------------
// 💾 Speichern (alle Felder, nicht nur sichtbare)
// ------------------------------------------------------------------
saveBtn.on("click", function () {
  // 1️⃣ Ausgangspunkt: kompletter Stand aus cachedFields
  const fieldMap = {};

  cachedFields.forEach((f) => {
    // flache Kopie, damit wir nicht direkt cachedFields mutieren müssen
    fieldMap[f.id] = { ...f };
  });

  // optional: show für alle erstmal auf false setzen
  Object.values(fieldMap).forEach((f) => {
    f.show = false;
  });

  // 2️⃣ DOM durchgehen und die sichtbaren Felder in fieldMap aktualisieren
  Object.keys(areas).forEach(function (areaKey) {
    const $el = areas[areaKey];

    $el.find(".bes-field-row").each(function (index) {
      const row = $(this);
      const id = row.data("id");
      const f = fieldMap[id];
      if (!f) return; // sollte nicht passieren, aber sicher ist sicher

      const inlineInput = row.find("input.bes-inline-group");
      const inlineGroupVal = inlineInput.length
        ? inlineInput.val().trim()
        : "";

      f.label = row.find(".bes-label").val();
      f.show = true;
      f.order = index + 1;
      f.area = areaKey;
      f.show_label = row.find(".bes-show-label").is(":checked");
      f.filterable = row.find(".bes-filterable-field").is(":checked");
      f.inline_group = inlineGroupVal;
    });

    // Filterleisten-Reihenfolge speichern
    $("#bes-filterbar").find(".bes-field-row").each(function (index) {
      const id = $(this).data("id");
      const f = fieldMap[id];
      if (f) {
        f.filter_order = index + 1;
      }

      // ❗ favorite und ignored NICHT hier setzen,
      // die kommen direkt aus cachedFields und werden oben übernommen
    });
  });

  // Filterleisten-Reihenfolge speichern
  $("#bes-filterbar").find(".bes-field-row").each(function (index) {
    const id = $(this).data("id");
    const f = fieldMap[id];
    if (f) {
      f.filter_order = index + 1;
    }
  });

  // filter_order für nicht-filterbare Felder entfernen
  Object.values(fieldMap).forEach((f) => {
    if (!f.filterable) {
      delete f.filter_order;
    }
  });

  // 3️⃣ In Array umwandeln
  const fields = Object.values(fieldMap);

  console.log("🔄 Sende Felder an bes_save_fields:", fields);

  msg.text("Speichere ...").removeClass("success error");

  $.ajax({
    url: bes_ajax.ajax_url,
    method: "POST",
    dataType: "json",
    data: {
      action: "bes_save_fields",
      nonce: bes_ajax.nonce,
      fields: JSON.stringify(fields),
    },
  })
    .done(function (response) {
      console.log("✅ bes_save_fields Antwort:", response);

      if (response && response.success) {
        msg.html(
          '<div class="notice notice-success is-dismissible"><p>' +
            response.data.message +
            "</p></div>"
        );
        // Statistiken aktualisieren
        updateStats();
        // Automatisch nach oben zur Meldung scrollen
        $("html, body").animate(
          {
            scrollTop: msg.offset().top - 100
          },
          500
        );
      } else {
        const err =
          (response &&
            response.data &&
            response.data.message) ||
          "Fehler beim Speichern (Server hat keinen Erfolg gemeldet).";
        msg.html(
          '<div class="notice notice-error is-dismissible"><p>' +
            err +
            "</p></div>"
        );
        // Auch bei Fehlern nach oben scrollen
        $("html, body").animate(
          {
            scrollTop: msg.offset().top - 100
          },
          500
        );
      }
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
      console.error("❌ AJAX-Fehler bei bes_save_fields:", {
        status: textStatus,
        error: errorThrown,
        responseText: jqXHR.responseText,
      });

      msg.html(
        '<div class="notice notice-error is-dismissible"><p>' +
          "AJAX-Fehler beim Speichern: " +
          textStatus +
          "</p></div>"
      );
    });
});


  // ------------------------------------------------------------------
  // 🔄 Reload-Button
  // ------------------------------------------------------------------
  reloadBtn.on("click", function () {
    loadFields();
  });

  // ------------------------------------------------------------------
  // 📥 Template importieren
  // ------------------------------------------------------------------
  $("#bes-import-template").on("click", function () {
    const btn = $(this);
    btn.prop("disabled", true).text("Importiere...");
    
    $.ajax({
      url: bes_ajax.ajax_url,
      method: "POST",
      dataType: "json",
      data: {
        action: "bes_import_template",
        nonce: bes_ajax.nonce,
      },
    })
      .done(function (response) {
        if (response && response.success) {
          // Nach Import alles sichtbar machen
          activeStatusFilter = 'all';
          collapseUnused = false;
          showIgnored = false;
          $("#bes-toggle-unused").toggleClass("button-primary", false);
          $("#bes-toggle-ignored").toggleClass("button-primary", false);

          msg.html(
            '<div class="notice notice-success is-dismissible"><p>' +
              (response.data.message || "Template erfolgreich importiert!") +
              "</p></div>"
          );
          // Nach oben scrollen, damit die Meldung sichtbar ist
          $("html, body").animate(
            {
              scrollTop: msg.offset().top - 100
            },
            500
          );
          // Felder neu laden nach erfolgreichem Import
          setTimeout(function() {
            loadFields();
          }, 1000);
        } else {
          const err =
            (response &&
              response.data &&
              response.data.message) ||
            "Fehler beim Importieren des Templates.";
          msg.html(
            '<div class="notice notice-error is-dismissible"><p>' +
              err +
              "</p></div>"
          );
          // Auch bei Fehlern nach oben scrollen
          $("html, body").animate(
            {
              scrollTop: msg.offset().top - 100
            },
            500
          );
        }
      })
      .fail(function () {
        msg.html(
          '<div class="notice notice-error is-dismissible"><p>' +
            "AJAX-Fehler beim Importieren des Templates." +
            "</p></div>"
        );
        $("html, body").animate(
          {
            scrollTop: msg.offset().top - 100
          },
          500
        );
      })
      .always(function () {
        btn.prop("disabled", false).text("📥 Template importieren");
      });
  });

  // ------------------------------------------------------------------
  // 📦 Template exportieren
  // ------------------------------------------------------------------
  $("#bes-export-template").on("click", function () {
    const btn = $(this);
    
    // Funktion zum eigentlichen Export (mit force-Parameter)
    function doExport(force) {
      btn.prop("disabled", true).text("Exportiere...");
      
      $.ajax({
        url: bes_ajax.ajax_url,
        method: "POST",
        dataType: "json",
        data: {
          action: "bes_export_config_template",
          nonce: bes_ajax.nonce,
          force: force ? "1" : "0",
        },
      })
        .done(function (response) {
          if (response && response.success) {
            msg.html(
              '<div class="notice notice-success is-dismissible"><p>' +
                response.data.message +
                "</p><p style='font-size: 12px; color: #666;'>Template gespeichert in: <code>" +
                (response.data.path || "") +
                "</code></p></div>"
            );
            // Nach oben scrollen, damit die Meldung sichtbar ist
            $("html, body").animate(
              {
                scrollTop: msg.offset().top - 100
              },
              500
            );
          } else {
            // Prüfe ob es eine Warnung ist (Template existiert bereits)
            if (
              response &&
              response.data &&
              response.data.warning === true &&
              response.data.template_exists === true
            ) {
              // Zeige Bestätigungsdialog
              if (
                confirm(
                  response.data.message +
                    "\n\n" +
                    "Klicken Sie auf 'OK' um fortzufahren und das vorhandene Template zu überschreiben."
                )
              ) {
                // Erneut exportieren mit force=true
                doExport(true);
                return;
              } else {
                // Benutzer hat abgebrochen
                btn.prop("disabled", false).text("📦 Als Template exportieren");
                return;
              }
            }
            
            // Normale Fehlermeldung
            const err =
              (response &&
                response.data &&
                response.data.message) ||
              "Fehler beim Exportieren.";
            msg.html(
              '<div class="notice notice-error is-dismissible"><p>' +
                err +
                "</p></div>"
            );
            // Auch bei Fehlern nach oben scrollen
            $("html, body").animate(
              {
                scrollTop: msg.offset().top - 100
              },
              500
            );
          }
        })
        .fail(function () {
          msg.html(
            '<div class="notice notice-error is-dismissible"><p>AJAX-Fehler beim Exportieren.</p></div>'
          );
          // Auch bei AJAX-Fehlern nach oben scrollen
          $("html, body").animate(
            {
              scrollTop: msg.offset().top - 100
            },
            500
          );
        })
        .always(function () {
          if (!force) {
            btn.prop("disabled", false).text("📦 Als Template exportieren");
          }
        });
    }
    
    // Starte Export ohne force (prüft auf vorhandenes Template)
    doExport(false);
  });

  $("#bes-collapse-all").on("click", function() {
    $(".bes-field-body").slideUp(200);
    $(".bes-toggle-details").attr("aria-expanded", "false").text("Details ▾");
  });

  $("#bes-expand-all").on("click", function() {
    $(".bes-field-body").slideDown(200);
    $(".bes-toggle-details").attr("aria-expanded", "true").text("Details ▴");
  });

  $("#bes-toggle-unused").on("click", function() {
    collapseUnused = !collapseUnused;
    renderFields();
    $(this).toggleClass("button-primary", !collapseUnused);
  });

  $("#bes-toggle-ignored").on("click", function() {
    showIgnored = !showIgnored;
    renderFields();
    $(this).toggleClass("button-primary", showIgnored);
  });

  // ------------------------------------------------------------------
  // 📊 JSON-Quelle Form Handler (entfällt, V2 ist fest eingestellt)
  // ------------------------------------------------------------------
  // Kein Formular mehr notwendig – falls ältere DOM-Reste existieren, verhindere Aktionen
  $("#bes-json-source-form").on("submit", function(e) {
    e.preventDefault();
    msg.text("JSON-Quelle ist fest auf V2 gesetzt.").addClass("success");
  });

  // ------------------------------------------------------------------
  // 🚀 Start
  // ------------------------------------------------------------------
  loadFields();

  // Standard: "Alle anzeigen" aktiv, Unused ausgeklappt

  // Helfer: Highlight Treffer
  function highlightMatch(text, search) {
    if (!search) return escapeHtml(text);
    const safe = escapeHtml(text);
    const regex = new RegExp("(" + escapeRegex(search) + ")", "ig");
    return safe.replace(regex, '<mark>$1</mark>');
  }

  function escapeHtml(str) {
    return (str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeRegex(str) {
    return (str || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  // ============================================================
  // 🎨 DESIGN-EINSTELLUNGEN
  // ============================================================
  
  const designMessage = $("#bes-design-message");
  const saveDesignBtn = $("#bes-save-design");
  const resetDesignBtn = $("#bes-reset-design");
  
  // Color-Picker Synchronisation (Color-Picker ↔ Text-Input)
  function syncColorPickers() {
    // Card-BG
    $("#bes-design-card-bg").on("input", function() {
      $("#bes-design-card-bg-text").val($(this).val());
    });
    $("#bes-design-card-bg-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-card-bg").val(val);
      }
    });
    
    // Card-Border
    $("#bes-design-card-border").on("input", function() {
      $("#bes-design-card-border-text").val($(this).val());
    });
    $("#bes-design-card-border-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-card-border").val(val);
      }
    });
    
    // Card-Text
    $("#bes-design-card-text").on("input", function() {
      $("#bes-design-card-text-text").val($(this).val());
    });
    $("#bes-design-card-text-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-card-text").val(val);
      }
    });
    
    // Card-Link
    $("#bes-design-card-link").on("input", function() {
      $("#bes-design-card-link-text").val($(this).val());
    });
    $("#bes-design-card-link-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-card-link").val(val);
      }
    });
    
    // Card-Stripe (Linker Rahmen)
    $("#bes-design-card-stripe").on("input", function() {
      $("#bes-design-card-stripe-text").val($(this).val());
    });
    $("#bes-design-card-stripe-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-card-stripe").val(val);
      }
    });
    
    // Button-BG
    $("#bes-design-button-bg").on("input", function() {
      $("#bes-design-button-bg-text").val($(this).val());
    });
    $("#bes-design-button-bg-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-button-bg").val(val);
      }
    });
    
    // Button-BG-Hover
    $("#bes-design-button-bg-hover").on("input", function() {
      $("#bes-design-button-bg-hover-text").val($(this).val());
    });
    $("#bes-design-button-bg-hover-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-button-bg-hover").val(val);
      }
    });
    
    // Button-Text
    $("#bes-design-button-text").on("input", function() {
      $("#bes-design-button-text-text").val($(this).val());
    });
    $("#bes-design-button-text-text").on("input", function() {
      const val = $(this).val();
      if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
        $("#bes-design-button-text").val(val);
      }
    });
  }
  
  // Design speichern
  saveDesignBtn.on("click", function() {
    const $btn = $(this);
    const originalText = $btn.text();
    
    $btn.prop("disabled", true).text("⏳ Speichere...");
    designMessage.hide();
    
    const settings = {
      card_bg: $("#bes-design-card-bg-text").val() || $("#bes-design-card-bg").val(),
      card_border: $("#bes-design-card-border-text").val() || $("#bes-design-card-border").val(),
      card_text: $("#bes-design-card-text-text").val() || $("#bes-design-card-text").val(),
      card_link: $("#bes-design-card-link-text").val() || $("#bes-design-card-link").val(),
      card_stripe: $("#bes-design-card-stripe-text").val() || $("#bes-design-card-stripe").val(),
      image_shadow: $("#bes-design-image-shadow").is(":checked") ? 1 : 0,
      button_bg: $("#bes-design-button-bg-text").val() || $("#bes-design-button-bg").val(),
      button_bg_hover: $("#bes-design-button-bg-hover-text").val() || $("#bes-design-button-bg-hover").val(),
      button_text: $("#bes-design-button-text-text").val() || $("#bes-design-button-text").val(),
    };
    
    $.ajax({
      url: bes_ajax.ajax_url,
      type: "POST",
      data: {
        action: "bes_save_design",
        nonce: bes_ajax.nonce,
        ...settings
      },
      success: function(response) {
        if (response.success) {
          designMessage.text(response.data.message || "Design gespeichert").css("color", "#00a32a").fadeIn();
          setTimeout(() => designMessage.fadeOut(), 3000);
        } else {
          designMessage.text(response.data?.message || "Fehler beim Speichern").css("color", "#d63638").fadeIn();
        }
        $btn.prop("disabled", false).text(originalText);
      },
      error: function() {
        designMessage.text("Fehler: Verbindung zum Server fehlgeschlagen").css("color", "#d63638").fadeIn();
        $btn.prop("disabled", false).text(originalText);
      }
    });
  });
  
  // Design zurücksetzen
  resetDesignBtn.on("click", function() {
    if (!confirm("Möchten Sie das Design wirklich auf die Standardwerte zurücksetzen?")) {
      return;
    }
    
    const $btn = $(this);
    const originalText = $btn.text();
    
    $btn.prop("disabled", true).text("⏳ Setze zurück...");
    designMessage.hide();
    
    $.ajax({
      url: bes_ajax.ajax_url,
      type: "POST",
      data: {
        action: "bes_reset_design",
        nonce: bes_ajax.nonce
      },
      success: function(response) {
        if (response.success) {
          const settings = response.data.settings || {};
          
          // Werte zurücksetzen
          $("#bes-design-card-bg").val(settings.card_bg || "#ffffff");
          $("#bes-design-card-bg-text").val(settings.card_bg || "#ffffff");
          $("#bes-design-card-border").val(settings.card_border || "rgba(0,0,0,0.10)");
          $("#bes-design-card-border-text").val(settings.card_border || "rgba(0,0,0,0.10)");
          $("#bes-design-card-text").val(settings.card_text || "#1c1c1c");
          $("#bes-design-card-text-text").val(settings.card_text || "#1c1c1c");
          $("#bes-design-card-link").val(settings.card_link || "#D99400");
          $("#bes-design-card-link-text").val(settings.card_link || "#D99400");
          $("#bes-design-card-stripe").val(settings.card_stripe || "#F7A600");
          $("#bes-design-card-stripe-text").val(settings.card_stripe || "#F7A600");
          $("#bes-design-image-shadow").prop("checked", settings.image_shadow !== false && settings.image_shadow !== 0);
          $("#bes-design-button-bg").val(settings.button_bg || "#F7A600");
          $("#bes-design-button-bg-text").val(settings.button_bg || "#F7A600");
          $("#bes-design-button-bg-hover").val(settings.button_bg_hover || "#D99400");
          $("#bes-design-button-bg-hover-text").val(settings.button_bg_hover || "#D99400");
          $("#bes-design-button-text").val(settings.button_text || "#000000");
          $("#bes-design-button-text-text").val(settings.button_text || "#000000");
          
          designMessage.text(response.data.message || "Design zurückgesetzt").css("color", "#00a32a").fadeIn();
          setTimeout(() => designMessage.fadeOut(), 3000);
        } else {
          designMessage.text(response.data?.message || "Fehler beim Zurücksetzen").css("color", "#d63638").fadeIn();
        }
        $btn.prop("disabled", false).text(originalText);
      },
      error: function() {
        designMessage.text("Fehler: Verbindung zum Server fehlgeschlagen").css("color", "#d63638").fadeIn();
        $btn.prop("disabled", false).text(originalText);
      }
    });
  });
  
  // Initialisiere Color-Picker-Synchronisation
  syncColorPickers();
});
