/**
 * Field Intelligence Dashboard
 * 
 * Modal mit Analysen, Vorschlägen und Empfehlungen für Felder
 */

jQuery(document).ready(function ($) {
    let intelligenceData = null;
    let activeTab = 'overview';

    // Modal HTML erstellen
    const modalHTML = `
        <div id="bes-field-intelligence-modal" class="bes-modal" style="display:none;">
            <div class="bes-modal-overlay"></div>
            <div class="bes-modal-content">
                <div class="bes-modal-header">
                    <h2>🧠 Field Intelligence Dashboard</h2>
                    <p style="margin:0;color:#666;font-size:13px;">Analysen, Vorschläge und Empfehlungen für Ihre Felder</p>
                    <button class="bes-modal-close" aria-label="Schließen">×</button>
                </div>
                <div class="bes-modal-body">
                    <div class="bes-modal-tabs">
                        <button class="bes-tab-btn active" data-tab="overview">📊 Übersicht</button>
                        <button class="bes-tab-btn" data-tab="suggestions">🔍 Vorschläge</button>
                        <button class="bes-tab-btn" data-tab="recommendations">🎯 Empfehlungen</button>
                        <button class="bes-tab-btn" data-tab="duplicates">🔗 Duplikate</button>
                        <button class="bes-tab-btn" data-tab="groupings">📦 Gruppierungen</button>
                        <button class="bes-tab-btn" data-tab="help">📚 Hilfe</button>
                    </div>
                    
                    <div class="bes-modal-tab-content">
                        <div id="bes-tab-overview" class="bes-tab-pane active">
                            <div class="bes-loading">Lade Analysen...</div>
                        </div>
                        <div id="bes-tab-suggestions" class="bes-tab-pane">
                            <div class="bes-loading">Lade Vorschläge...</div>
                        </div>
                        <div id="bes-tab-recommendations" class="bes-tab-pane">
                            <div class="bes-loading">Lade Empfehlungen...</div>
                        </div>
                        <div id="bes-tab-duplicates" class="bes-tab-pane">
                            <div class="bes-loading">Lade Duplikate...</div>
                        </div>
                        <div id="bes-tab-groupings" class="bes-tab-pane">
                            <div class="bes-loading">Lade Gruppierungen...</div>
                        </div>
                        <div id="bes-tab-help" class="bes-tab-pane">
                            <div class="bes-help-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Modal zum Body hinzufügen
    $('body').append(modalHTML);

    const $modal = $('#bes-field-intelligence-modal');
    const $overlay = $modal.find('.bes-modal-overlay');
    const $closeBtn = $modal.find('.bes-modal-close');
    const $tabBtns = $modal.find('.bes-tab-btn');
    const $tabPanes = $modal.find('.bes-tab-pane');

    // Button zum Öffnen (wird in ui-felder.php hinzugefügt)
    $(document).on('click', '#bes-field-intelligence-btn', function(e) {
        e.preventDefault();
        openModal();
    });

    // Modal schließen
    $closeBtn.on('click', closeModal);
    $overlay.on('click', closeModal);
    
    // ESC-Taste schließt Modal
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            closeModal();
        }
    });

    // Tab-Wechsel
    $tabBtns.on('click', function() {
        const tab = $(this).data('tab');
        switchTab(tab);
    });

    function openModal() {
        $modal.fadeIn(200);
        loadIntelligenceData();
    }

    function closeModal() {
        $modal.fadeOut(200);
    }

    function switchTab(tab) {
        activeTab = tab;
        
        $tabBtns.removeClass('active');
        $tabBtns.filter(`[data-tab="${tab}"]`).addClass('active');
        
        $tabPanes.removeClass('active');
        $(`#bes-tab-${tab}`).addClass('active');
        
        if (intelligenceData) {
            renderTab(tab);
        }
    }

    function loadIntelligenceData() {
        $.ajax({
            url: bes_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'bes_get_field_intelligence',
                nonce: bes_ajax.nonce
            },
            beforeSend: function() {
                $('.bes-tab-pane').html('<div class="bes-loading">Lade Analysen...</div>');
            },
            success: function(response) {
                if (response.success) {
                    intelligenceData = response.data;
                    renderTab(activeTab);
                } else {
                    $('.bes-tab-pane').html(`<div class="bes-error">${response.data.message || 'Fehler beim Laden'}</div>`);
                }
            },
            error: function() {
                $('.bes-tab-pane').html('<div class="bes-error">Fehler beim Laden der Daten</div>');
            }
        });
    }

    function renderTab(tab) {
        if (!intelligenceData) return;

        switch(tab) {
            case 'overview':
                renderOverview();
                break;
            case 'suggestions':
                renderSuggestions();
                break;
            case 'recommendations':
                renderRecommendations();
                break;
            case 'duplicates':
                renderDuplicates();
                break;
            case 'groupings':
                renderGroupings();
                break;
            case 'help':
                renderHelp();
                break;
        }
    }

    function renderOverview() {
        const stats = intelligenceData.stats || {};
        const html = `
            <div class="bes-intelligence-overview">
                <h3>📊 Statistiken</h3>
                
                <div class="bes-stats-grid">
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${intelligenceData.total_fields || 0}</div>
                        <div class="bes-stat-label">Gesamt Felder</div>
                    </div>
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${stats.by_status?.in_use || 0}</div>
                        <div class="bes-stat-label">In Verwendung</div>
                    </div>
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${stats.by_status?.configured || 0}</div>
                        <div class="bes-stat-label">Konfiguriert</div>
                    </div>
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${stats.by_status?.unconfigured || 0}</div>
                        <div class="bes-stat-label">Unkonfiguriert</div>
                    </div>
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${stats.without_label || 0}</div>
                        <div class="bes-stat-label">Ohne Label</div>
                    </div>
                    <div class="bes-stat-card">
                        <div class="bes-stat-value">${stats.custom_fields || 0}</div>
                        <div class="bes-stat-label">Custom Fields</div>
                    </div>
                </div>
                
                <h3 style="margin-top:30px;">Verteilung nach Typ</h3>
                <div class="bes-type-distribution">
                    ${renderTypeDistribution(stats.by_type || {})}
                </div>
                
                <h3 style="margin-top:30px;">Verteilung nach Bereich</h3>
                <div class="bes-area-distribution">
                    <div class="bes-dist-item">
                        <span class="bes-dist-label">🟩 Über dem Button:</span>
                        <span class="bes-dist-value">${stats.by_area?.above || 0}</span>
                    </div>
                    <div class="bes-dist-item">
                        <span class="bes-dist-label">🟦 Unter dem Button:</span>
                        <span class="bes-dist-value">${stats.by_area?.below || 0}</span>
                    </div>
                    <div class="bes-dist-item">
                        <span class="bes-dist-label">⬜ Nicht gebraucht:</span>
                        <span class="bes-dist-value">${stats.by_area?.unused || 0}</span>
                    </div>
                </div>
            </div>
        `;
        $('#bes-tab-overview').html(html);
    }

    function renderTypeDistribution(byType) {
        const items = Object.entries(byType)
            .sort((a, b) => b[1] - a[1])
            .map(([type, count]) => {
                const labels = {
                    'member': 'Mitglied',
                    'contact': 'Kontakt',
                    'cf': 'Mitglied Custom',
                    'cfraw': 'Mitglied Custom (roh)',
                    'contactcf': 'Kontakt Custom',
                    'contactcfraw': 'Kontakt Custom (roh)',
                    'consent': 'Einwilligungen'
                };
                return `
                    <div class="bes-dist-item">
                        <span class="bes-dist-label">${labels[type] || type}:</span>
                        <span class="bes-dist-value">${count}</span>
                    </div>
                `;
            }).join('');
        return items || '<p>Keine Daten</p>';
    }

    function renderSuggestions() {
        const suggestions = intelligenceData.suggestions || {};
        const html = `
            <div class="bes-intelligence-suggestions">
                <h3>🔍 Label-Vorschläge</h3>
                ${renderLabelSuggestions(suggestions.labels || [])}
                
                <h3 style="margin-top:30px;">📂 Kategorisierungs-Vorschläge</h3>
                ${renderCategorySuggestions(suggestions.categories || [])}
                
                <h3 style="margin-top:30px;">✨ Aktivierungs-Vorschläge</h3>
                ${renderActivationSuggestions(suggestions.activation || [])}
            </div>
        `;
        $('#bes-tab-suggestions').html(html);
    }

    function renderLabelSuggestions(suggestions) {
        if (suggestions.length === 0) {
            return '<p>Keine Label-Vorschläge verfügbar.</p>';
        }
        
        const items = suggestions.map(s => {
            const confidence = s.confidence || 0;
            const confidenceClass = confidence >= 70 ? 'high' : confidence >= 40 ? 'medium' : 'low';
            return `
                <div class="bes-suggestion-item">
                    <div class="bes-suggestion-header">
                        <strong>${escapeHtml(s.field_id)}</strong>
                        <span class="bes-confidence bes-confidence-${confidenceClass}">${confidence}%</span>
                    </div>
                    <div class="bes-suggestion-content">
                        <div class="bes-suggestion-current">
                            <span class="bes-label">Aktuell:</span> 
                            <code>${escapeHtml(s.current_label || s.field_id)}</code>
                        </div>
                        <div class="bes-suggestion-proposed">
                            <span class="bes-label">Vorschlag:</span> 
                            <strong>${escapeHtml(s.suggested_label)}</strong>
                        </div>
                        <div class="bes-suggestion-reason">
                            <small>💡 ${escapeHtml(s.reason || '')}</small>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        return `<div class="bes-suggestions-list">${items}</div>`;
    }

    function renderCategorySuggestions(suggestions) {
        if (suggestions.length === 0) {
            return '<p>Keine Kategorisierungs-Vorschläge verfügbar.</p>';
        }
        
        const items = suggestions.map(s => `
            <div class="bes-suggestion-item">
                <div class="bes-suggestion-header">
                    <strong>${escapeHtml(s.field_id)}</strong>
                    <span class="bes-category-badge">${escapeHtml(s.suggested_category)}</span>
                </div>
                <div class="bes-suggestion-reason">
                    <small>💡 ${escapeHtml(s.reason || '')}</small>
                </div>
            </div>
        `).join('');
        
        return `<div class="bes-suggestions-list">${items}</div>`;
    }

    function renderActivationSuggestions(suggestions) {
        if (suggestions.length === 0) {
            return '<p>Keine Aktivierungs-Vorschläge verfügbar.</p>';
        }
        
        const items = suggestions.map(s => `
            <div class="bes-suggestion-item">
                <div class="bes-suggestion-header">
                    <strong>${escapeHtml(s.field_id)}</strong>
                </div>
                <div class="bes-suggestion-content">
                    <p>${escapeHtml(s.reason || '')}</p>
                    ${s.example ? `<div class="bes-example"><code>${escapeHtml(String(s.example))}</code></div>` : ''}
                </div>
            </div>
        `).join('');
        
        return `<div class="bes-suggestions-list">${items}</div>`;
    }

    function renderRecommendations() {
        const recommendations = intelligenceData.recommendations || [];
        
        if (recommendations.length === 0) {
            $('#bes-tab-recommendations').html('<p>Keine Empfehlungen verfügbar.</p>');
            return;
        }
        
        const items = recommendations.map(r => {
            const typeIcon = r.type === 'activate' ? '✨' : r.type === 'label' ? '🏷️' : '💡';
            return `
                <div class="bes-recommendation-item">
                    <div class="bes-recommendation-icon">${typeIcon}</div>
                    <div class="bes-recommendation-content">
                        <div class="bes-recommendation-header">
                            <strong>${escapeHtml(r.field_label || r.field_id)}</strong>
                            <span class="bes-recommendation-type">${r.type === 'activate' ? 'Aktivierung' : 'Label'}</span>
                        </div>
                        <p>${escapeHtml(r.message || '')}</p>
                        ${r.example ? `<div class="bes-example"><code>${escapeHtml(String(r.example))}</code></div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
        $('#bes-tab-recommendations').html(`<div class="bes-recommendations-list">${items}</div>`);
    }

    function renderDuplicates() {
        const duplicates = intelligenceData.duplicates || [];
        
        if (duplicates.length === 0) {
            $('#bes-tab-duplicates').html('<p>Keine potenziellen Duplikate gefunden.</p>');
            return;
        }
        
        const items = duplicates.map(d => {
            const fields = d.fields || [];
            const fieldsList = fields.map(f => `
                <li>
                    <code>${escapeHtml(f.id)}</code>
                    ${f.label ? ` - ${escapeHtml(f.label)}` : ''}
                </li>
            `).join('');
            
            return `
                <div class="bes-duplicate-item">
                    <div class="bes-duplicate-header">
                        <strong>${fields.length} Felder mit ähnlichen Werten</strong>
                    </div>
                    <div class="bes-duplicate-content">
                        <p>${escapeHtml(d.message || '')}</p>
                        <div class="bes-example">
                            <strong>Beispielwert:</strong> <code>${escapeHtml(d.example || '')}</code>
                        </div>
                        <ul class="bes-fields-list">
                            ${fieldsList}
                        </ul>
                    </div>
                </div>
            `;
        }).join('');
        
        $('#bes-tab-duplicates').html(`<div class="bes-duplicates-list">${items}</div>`);
    }

    function renderGroupings() {
        const groupings = intelligenceData.groupings || [];
        
        if (groupings.length === 0) {
            $('#bes-tab-groupings').html('<p>Keine Gruppierungs-Vorschläge verfügbar.</p>');
            return;
        }
        
        const items = groupings.map(g => {
            const fields = g.fields || [];
            const fieldsList = fields.map(f => `
                <li>
                    <code>${escapeHtml(f.id)}</code>
                    ${f.label ? ` - ${escapeHtml(f.label)}` : ''}
                </li>
            `).join('');
            
            return `
                <div class="bes-grouping-item">
                    <div class="bes-grouping-header">
                        <strong>${g.suggested_group_name || 'Gruppe'}</strong>
                        <span class="bes-grouping-count">${fields.length} Felder</span>
                    </div>
                    <div class="bes-grouping-content">
                        <p>${escapeHtml(g.message || '')}</p>
                        <ul class="bes-fields-list">
                            ${fieldsList}
                        </ul>
                    </div>
                </div>
            `;
        }).join('');
        
        $('#bes-tab-groupings').html(`<div class="bes-groupings-list">${items}</div>`);
    }

    function renderHelp() {
        const html = `
            <div class="bes-help-content">
                <h3>📚 Field Intelligence Dashboard</h3>
                <p>Dieses Dashboard analysiert Ihre Felder und liefert intelligente Vorschläge und Empfehlungen.</p>
                
                <h4>Was wird analysiert?</h4>
                <ul>
                    <li><strong>Statistiken:</strong> Übersicht über alle Felder, Verteilung nach Typ und Status</li>
                    <li><strong>Label-Vorschläge:</strong> Intelligente Vorschläge für Felder ohne Label</li>
                    <li><strong>Kategorisierungen:</strong> Vorschläge für Feld-Kategorien</li>
                    <li><strong>Empfehlungen:</strong> Welche Felder sollten aktiviert werden?</li>
                    <li><strong>Duplikate:</strong> Felder mit ähnlichen Werten</li>
                    <li><strong>Gruppierungen:</strong> Felder die zusammengehören könnten</li>
                </ul>
                
                <h4>Wie funktioniert die Label-Erkennung?</h4>
                <p>Die Erkennung basiert auf mehreren Signalen:</p>
                <ul>
                    <li><strong>Feld-ID:</strong> Höchste Priorität - wenn die ID "street" enthält → "Straße"</li>
                    <li><strong>Pattern-Erkennung:</strong> E-Mail-Adressen, URLs, Telefonnummern, PLZ werden erkannt</li>
                    <li><strong>Kontext:</strong> Felder neben anderen Adress-Feldern werden als Adress-Felder erkannt</li>
                </ul>
                
                <h4>Grenzen der Erkennung</h4>
                <p>Nicht alle Felder können automatisch erkannt werden:</p>
                <ul>
                    <li>Straßennamen vs. Nachnamen sind schwer zu unterscheiden</li>
                    <li>Städtenamen vs. Nachnamen sind schwer zu unterscheiden</li>
                    <li>Freitext-Felder benötigen manuelle Labels</li>
                </ul>
                <p><strong>Daher:</strong> Die Vorschläge sind als Hilfe gedacht, nicht als vollautomatische Lösung.</p>
                
                <h4>Best Practices</h4>
                <ul>
                    <li>Wichtige Felder zuerst konfigurieren</li>
                    <li>Ähnliche Felder gruppieren (z.B. "Internet 1", "Internet 2")</li>
                    <li>Unbenutzte Felder ignorieren, um Übersicht zu behalten</li>
                    <li>Labels sollten kurz und präzise sein</li>
                </ul>
            </div>
        `;
        $('#bes-tab-help').html(html);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});




