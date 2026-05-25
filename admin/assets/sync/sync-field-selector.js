(function (window) {
    'use strict';
    window.besSyncUi = window.besSyncUi || {};

    jQuery(function ($) {
        var besSyncUi = window.besSyncUi;

        besSyncUi.initFieldSelector = function ($) {
        // ============================================================
        // V3 FELDAUSWAHL
        // ============================================================
        const v3Nonce = besSyncUi.getV3Nonce();

        // Feldauswahl-Modal öffnen
        $('#bes-v3-open-field-selector').on('click', function() {
            const btn = $(this);
            besSyncUi.setButtonState(btn, 'Lade Katalog...', { loading: true, disabled: true });
            $('#bes-v3-selection-message').html('');

            // Stoppe alle laufenden Polling-Timer, damit sie nicht stören
            clearTimeout(besSyncUi.pollV3ExplorerTimer);
            clearTimeout(besSyncUi.pollV3SyncTimer);

            // Lade Katalog und Selection parallel
            $.when(
        $.get(ajaxurl, { action: 'bes_v3_load_catalog', _ajax_nonce: v3Nonce }),
        $.get(ajaxurl, { action: 'bes_v3_load_selection', _ajax_nonce: v3Nonce })
            ).done(function(catalogResp, selectionResp) {
        besSyncUi.setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });

        if (!catalogResp[0].success || !selectionResp[0].success) {
            const errorMsg = catalogResp[0].data?.error || selectionResp[0].data?.error || 'Unbekannt';
            console.error('V3 Feldauswahl Fehler:', catalogResp[0], selectionResp[0]);
            $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Laden: ' + errorMsg + '</strong>');
            return;
        }

        const catalog = catalogResp[0].data.catalog;
        const selection = selectionResp[0].data.selection;
        const selectedFields = selection.fields || [];

        // Validiere Katalog-Struktur
        if (!catalog || !catalog.fields) {
            console.error('V3 Katalog ungültig:', catalog);
            $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Katalog hat ungültige Struktur. Bitte Explorer erneut ausführen.</strong>');
            return;
        }

        // Erstelle Modal
        try {
            besSyncUi.showV3FieldSelectorModal(catalog, selectedFields);
        } catch (e) {
            console.error('Fehler beim Erstellen des Modals:', e);
            $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ Fehler beim Öffnen des Modals: ' + e.message + '</strong>');
        }
            }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Fehler beim Laden:', textStatus, errorThrown, jqXHR);
        besSyncUi.setButtonState(btn, '📋 Feldauswahl öffnen', { loading: false, disabled: false });
        $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler: ' + (errorThrown || textStatus) + '</strong>');
            });
        });

        // Selection zurücksetzen
        $('#bes-v3-reset-selection').on('click', function() {
            if (!confirm('Möchten Sie die Feldauswahl wirklich auf die Pflichtfelder zurücksetzen?')) {
        return;
            }

            const btn = $(this);
            besSyncUi.setButtonState(btn, 'Setze zurück...', { loading: true, disabled: true });

            $.post(ajaxurl, {
        action: 'bes_v3_reset_selection',
        _ajax_nonce: v3Nonce
            }, function(resp) {
        if (resp && resp.success) {
            $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ ' + (resp.data?.error || 'Fehler') + '</strong>');
            besSyncUi.setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
        }
            }).fail(function() {
        $('#bes-v3-selection-message').html('<strong style="color: #d63638;">❌ AJAX-Fehler</strong>');
        besSyncUi.setButtonState(btn, '🔄 Auf Pflichtfelder zurücksetzen', { loading: false, disabled: false });
            });
        });

        // Modal für Feldauswahl
        besSyncUi.showV3FieldSelectorModal = function (catalog, selectedFields) {
            // Validiere Parameter
            if (!catalog || typeof catalog !== 'object') {
        throw new Error('Katalog ist ungültig oder leer');
            }
            if (!catalog.fields || typeof catalog.fields !== 'object') {
        throw new Error('Katalog enthält keine Felder-Struktur');
            }
            if (!Array.isArray(selectedFields)) {
        selectedFields = [];
            }

            // Entferne vorhandenes Modal falls vorhanden
            $('#bes-v3-field-selector-modal').remove();

            const selectedSet = new Set(selectedFields);
            // Lade requiredFields aus PHP (sollte ein Array sein)
            const requiredFieldsRaw = (typeof besSyncData !== 'undefined' && besSyncData.requiredFields) ? besSyncData.requiredFields : ['member.id','member.membershipNumber','syncedAt'];
            // Stelle sicher, dass requiredFields ein Array ist
            let requiredFieldsArray;
            if (Array.isArray(requiredFieldsRaw)) {
        requiredFieldsArray = requiredFieldsRaw;
            } else if (requiredFieldsRaw && typeof requiredFieldsRaw === 'object') {
        // Falls es ein Objekt ist, konvertiere zu Array
        requiredFieldsArray = Object.values(requiredFieldsRaw);
            } else if (requiredFieldsRaw) {
        // Falls es ein einzelner Wert ist, mache ein Array daraus
        requiredFieldsArray = [requiredFieldsRaw];
            } else {
        // Fallback auf Standard-Pflichtfelder
        requiredFieldsArray = ['member.id', 'member.membershipNumber', 'syncedAt'];
            }
            const requiredSet = new Set(requiredFieldsArray);

            let html = '<div id="bes-v3-field-selector-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;overflow-y:auto;padding:20px;">';
            html += '<div style="max-width:900px;margin:20px auto;background:#fff;border-radius:8px;padding:20px;box-shadow:0 4px 20px rgba(0,0,0,0.3);">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #ddd;padding-bottom:15px;">';
            html += '<h2 style="margin:0;">📋 V3 Feldauswahl</h2>';
            html += '<button type="button" id="bes-v3-close-modal" class="button" style="margin-left:20px;">✕ Schließen</button>';
            html += '</div>';

            // Statistik
            html += '<div style="background:#f0f0f1;padding:15px;border-radius:6px;margin-bottom:20px;">';
            html += '<strong>Ausgewählt:</strong> <span id="bes-v3-selected-count">' + selectedFields.length + '</span> Felder | ';
            html += '<strong>Pflichtfelder:</strong> ' + requiredFieldsArray.length + ' (immer enthalten)';
            html += '</div>';

            // Quick Actions
            html += '<div style="margin-bottom:20px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
            html += '<button type="button" class="button button-small" id="bes-v3-select-all">Alle auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-deselect-all">Alle abwählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-select-filled">Nur befüllte Felder auswählen</button>';
            html += '<button type="button" class="button button-small" id="bes-v3-toggle-filled-only">Nur Felder mit Inhalt anzeigen</button>';
            html += '<input type="text" id="bes-v3-filter-fields" placeholder="Felder filtern..." style="margin-left:auto;min-width:220px;width:250px;padding:5px;">';
            html += '</div>';

            // Felder-Gruppen in gewünschter Reihenfolge:
            // 1) Contact-Felder, 2) Member Custom Fields, 3) Member-Felder, 4) Contact Custom Fields
            const fieldGroups = [
        { key: 'contact', title: '📧 Contact-Felder', fields: catalog.fields?.contact || [] },
        { key: 'member_cf', title: '🏷️ Member Custom Fields', fields: catalog.fields?.member_cf || [] },
        { key: 'member', title: '👤 Member-Felder', fields: catalog.fields?.member || [] },
        { key: 'contact_cf', title: '🏷️ Contact Custom Fields', fields: catalog.fields?.contact_cf || [] }
            ];

            html += '<div style="max-height:500px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:15px;">';

            fieldGroups.forEach(function(group) {
        if (group.fields.length === 0) return;

        html += '<div class="bes-v3-field-group" style="margin-bottom:25px;">';
        html += '<h3 style="margin:0 0 10px 0;font-size:16px;color:#2271b1;border-bottom:1px solid #ddd;padding-bottom:5px;">' + group.title + ' (' + group.fields.length + ')</h3>';

        // Felder innerhalb der Gruppe sortieren:
        // 1) Pflichtfelder, 2) nach Befüllungsgrad (filled_pct) absteigend, 3) Felder mit Beispielen vor Feldern ohne Beispiele, 4) Name alphabetisch
        const sortedFields = (group.fields || []).slice().sort(function(a, b) {
            const aKey = a.key;
            const bKey = b.key;
            const aRequired = requiredSet.has(aKey);
            const bRequired = requiredSet.has(bKey);
            if (aRequired !== bRequired) {
                return aRequired ? -1 : 1;
            }
            const aFilled = a.filled_pct || 0;
            const bFilled = b.filled_pct || 0;
            if (aFilled !== bFilled) {
                return bFilled - aFilled;
            }
            const aHasExample = Array.isArray(a.example_values) && a.example_values.length > 0;
            const bHasExample = Array.isArray(b.example_values) && b.example_values.length > 0;
            if (aHasExample !== bHasExample) {
                return aHasExample ? -1 : 1;
            }
            const aName = (a.meta?.name || a.field_key || aKey || '').toLowerCase();
            const bName = (b.meta?.name || b.field_key || bKey || '').toLowerCase();
            if (aName < bName) return -1;
            if (aName > bName) return 1;
            return 0;
        });

        sortedFields.forEach(function(field) {
            const fieldKey = field.key;
            const isSelected = selectedSet.has(fieldKey);
            const isRequired = requiredSet.has(fieldKey);
            const filledPct = field.filled_pct || 0;
            const fieldName = field.meta?.name || field.field_key || fieldKey;

            // Beispielwerte extrahieren
            const exampleValues = field.example_values || [];
            let exampleText = '';
            if (exampleValues.length > 0) {
                // Nimm ersten Beispielwert (oder mehrere, max 2)
                const examples = exampleValues.slice(0, 2);
                exampleText = examples.map(function(ex) {
                    // Kürze sehr lange Werte
                    const str = String(ex);
                    if (str.length > 80) {
                        return str.substring(0, 77) + '...';
                    }
                    return str;
                }).join(' | ');
            }

            html += '<div class="bes-v3-field-item" data-field-key="' + besSyncUi.escapeHtml(fieldKey) + '" data-filled-pct="' + filledPct + '" style="padding:10px;border-bottom:1px solid #f0f0f1;">';
            html += '<label style="cursor:pointer;display:flex;align-items:flex-start;">';
            html += '<input type="checkbox" class="bes-v3-field-checkbox" value="' + besSyncUi.escapeHtml(fieldKey) + '" ' +
                (isSelected ? 'checked' : '') + ' ' + (isRequired ? 'disabled' : '') + ' style="margin-right:10px;margin-top:3px;flex-shrink:0;">';
            html += '<div style="flex:1;min-width:0;">';
            // Feldname und ID (leicht ausgegraut)
            html += '<div style="margin-bottom:4px;">';
            html += '<span style="color:#666;font-weight:500;">' + besSyncUi.escapeHtml(fieldName) + '</span> ';
            html += '<code style="font-size:11px;color:#999;background:#f5f5f5;padding:2px 4px;border-radius:3px;">' + besSyncUi.escapeHtml(fieldKey) + '</code>';
            if (isRequired) {
                html += ' <span style="color:#d63638;font-size:11px;margin-left:5px;">(Pflichtfeld)</span>';
            }
            html += '</div>';
            // Beispielinhalt (fett, schwarz)
            if (exampleText) {
                html += '<div style="font-weight:bold;color:#000;font-size:13px;margin-top:3px;line-height:1.4;">' + besSyncUi.escapeHtml(exampleText) + '</div>';
            } else {
                html += '<div style="color:#999;font-size:12px;font-style:italic;margin-top:3px;">Kein Beispiel verfügbar</div>';
            }
            // Befüllungsprozentsatz
            html += '<div style="font-size:11px;color:#666;margin-top:4px;">' + filledPct + '% befüllt</div>';
            html += '</div>';
            html += '</label>';
            html += '</div>';
        });

        html += '</div>';
            });

            html += '</div>'; // End scrollable area

            html += '<div style="margin-top:20px;padding-top:15px;border-top:2px solid #ddd;display:flex;justify-content:space-between;align-items:center;">';
            html += '<div><strong id="bes-v3-final-count">' + selectedFields.length + '</strong> Felder ausgewählt</div>';
            html += '<div>';
            html += '<button type="button" id="bes-v3-cancel-selection" class="button" style="margin-right:10px;">Abbrechen</button>';
            html += '<button type="button" id="bes-v3-save-selection" class="button button-primary">💾 Auswahl speichern</button>';
            html += '</div>';
            html += '</div>';

            html += '</div></div>';

            $('body').append(html);

            // Event Handler
            $('#bes-v3-close-modal, #bes-v3-cancel-selection').on('click', function() {
        $('#bes-v3-field-selector-modal').remove();
            });

            $('#bes-v3-select-all').on('click', function() {
        $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', true);
        updateSelectionCount();
            });

            $('#bes-v3-deselect-all').on('click', function() {
        $('.bes-v3-field-checkbox:not(:disabled)').prop('checked', false);
        updateSelectionCount();
            });

            $('#bes-v3-select-filled').on('click', function() {
        $('.bes-v3-field-checkbox:not(:disabled)').each(function() {
            const $item = $(this).closest('.bes-v3-field-item');
            const filledAttr = $item.data('filled-pct');
            const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
            $(this).prop('checked', filledPct > 0);
        });
        updateSelectionCount();
            });

            function applyFieldVisibility() {
        const filter = $('#bes-v3-filter-fields').val().toLowerCase();
        const filledOnly = $('#bes-v3-toggle-filled-only').data('filledOnly') === true;

        $('.bes-v3-field-item').each(function() {
            const $item = $(this);
            const text = $item.text().toLowerCase();
            const matchesText = !filter || text.includes(filter);

            const filledAttr = $item.data('filled-pct');
            const filledPct = typeof filledAttr === 'number' ? filledAttr : parseInt(filledAttr, 10) || 0;
            const hasContent = filledPct > 0;

            const visible = matchesText && (!filledOnly || hasContent);
            $item.toggle(visible);
        });
            }

            $('#bes-v3-filter-fields').on('input', function() {
        applyFieldVisibility();
            });

            $('#bes-v3-toggle-filled-only').on('click', function() {
        const $btn = $(this);
        const active = $btn.data('filledOnly') === true;
        const makeActive = !active;
        $btn.data('filledOnly', makeActive);
        $btn.toggleClass('button-primary', makeActive);
        applyFieldVisibility();
            });

            $('.bes-v3-field-checkbox').on('change', updateSelectionCount);

            function updateSelectionCount() {
        const count = $('.bes-v3-field-checkbox:checked').length;
        $('#bes-v3-selected-count, #bes-v3-final-count').text(count);
            }

            $('#bes-v3-save-selection').on('click', function() {
        const btn = $(this);
        const selected = $('.bes-v3-field-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        besSyncUi.setButtonState(btn, 'Speichere...', { loading: true, disabled: true });

        $.post(ajaxurl, {
            action: 'bes_v3_save_selection',
            _ajax_nonce: v3Nonce,
            fields: selected
        }, function(resp) {
            if (resp && resp.success) {
                $('#bes-v3-selection-message').html('<strong style="color: #00a32a;">✅ ' + resp.data.message + '</strong>');
                $('#bes-v3-field-selector-modal').remove();
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                alert('Fehler: ' + (resp.data?.error || 'Unbekannt'));
                besSyncUi.setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
            }
        }).fail(function() {
            alert('AJAX-Fehler beim Speichern');
            besSyncUi.setButtonState(btn, '💾 Auswahl speichern', { loading: false, disabled: false });
        });
            });
        }
    };
    });
})(window);
