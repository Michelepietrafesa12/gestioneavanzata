/**
 * Product Advanced Manager - Admin JS
 * @version 2.1.0
 */
(function($) {
    'use strict';

    // Aspetta che il DOM sia pronto
    $(document).ready(function() {
        console.log('PAM JS loaded');
        PAM.init();
    });

    var PAM = window.PAM = {
        changes: {},
        expandedProducts: {}
    };

    PAM.init = function() {
        console.log('PAM init, config:', window.pamConfig);
        
        if (typeof window.pamConfig === 'undefined') {
            console.error('PAM: pamConfig non definito!');
            return;
        }
        
        this.config = window.pamConfig;
        this.bindEvents();
    };

    PAM.bindEvents = function() {
        var self = this;

        // Input change
        $('#pam-table').on('change', '.pam-input', function() {
            self.onInputChange($(this));
        });

        // Enter per salvare
        $('#pam-table').on('keypress', '.pam-input', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).blur();
            }
        });

        // Blur per salvare
        $('#pam-table').on('blur', '.pam-input', function() {
            var $input = $(this);
            if ($input.hasClass('pam-changed')) {
                self.saveSingle($input);
            }
        });

        // Salva tutto
        $('#pam-save-all').on('click', function() {
            self.saveAll();
        });

        // EXPAND BUTTON - usando delegazione eventi su document
        $(document).on('click', '.pam-expand-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $btn = $(this);
            var idProduct = $btn.data('id');
            var isExpanded = $btn.data('expanded') == 1;
            
            console.log('Expand click, id:', idProduct, 'expanded:', isExpanded);
            
            if (isExpanded) {
                self.collapseCombinations(idProduct, $btn);
            } else {
                self.expandCombinations(idProduct, $btn);
            }
            
            return false;
        });
    };

    PAM.expandCombinations = function(idProduct, $btn) {
        var self = this;
        var $icon = $btn.find('i');
        var $row = $btn.closest('tr');
        
        console.log('Expanding combinations for product:', idProduct);
        
        // Loading state
        $icon.removeClass('icon-plus icon-minus').addClass('icon-spinner icon-spin');
        $btn.prop('disabled', true);
        
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'getCombinations',
                id_product: idProduct
            },
            success: function(response) {
                console.log('getCombinations response:', response);
                
                $btn.prop('disabled', false);
                $icon.removeClass('icon-spinner icon-spin');
                
                if (response && response.success && response.combinations && response.combinations.length > 0) {
                    $icon.addClass('icon-minus');
                    $btn.data('expanded', 1);
                    
                    // Genera HTML per le combinazioni
                    var html = '';
                    $.each(response.combinations, function(i, comb) {
                        html += self.renderCombinationRow(idProduct, comb);
                    });
                    
                    // Inserisci dopo la riga corrente
                    $row.after(html);
                    self.expandedProducts[idProduct] = true;
                    
                } else {
                    $icon.addClass('icon-plus');
                    self.notify('Nessuna variante trovata', 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                $btn.prop('disabled', false);
                $icon.removeClass('icon-spinner icon-spin').addClass('icon-plus');
                self.notify('Errore nel caricamento', 'danger');
            }
        });
    };

    PAM.collapseCombinations = function(idProduct, $btn) {
        var $icon = $btn.find('i');
        
        // Rimuovi le righe delle combinazioni
        $('.pam-comb-row[data-parent="' + idProduct + '"]').remove();
        
        $icon.removeClass('icon-minus').addClass('icon-plus');
        $btn.data('expanded', 0);
        delete this.expandedProducts[idProduct];
    };

    PAM.renderCombinationRow = function(idProduct, comb) {
        var idAttr = comb.id_product_attribute;
        var attrName = comb.attribute_name || ('Variante #' + idAttr);
        var ref = comb.reference || '';
        
        return '<tr class="pam-comb-row" data-parent="' + idProduct + '">' +
            '<td class="pam-col-id text-muted"><small>↳</small></td>' +
            '<td class="pam-col-img"></td>' +
            '<td class="pam-col-name pam-comb-name">' + this.escapeHtml(attrName) + '</td>' +
            '<td class="pam-col-ref"><code>' + this.escapeHtml(ref) + '</code></td>' +
            '<td class="pam-col-stock text-center">' +
                '<input type="number" class="form-control input-sm pam-input pam-input-stock" ' +
                'value="' + parseInt(comb.quantity || 0) + '" ' +
                'data-original="' + parseInt(comb.quantity || 0) + '" ' +
                'data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="quantity" ' +
                'step="1" min="0">' +
            '</td>' +
            '<td class="pam-col-dim text-center">' +
                '<input type="number" class="form-control input-sm pam-input" ' +
                'value="' + parseFloat(comb.weight || 0).toFixed(2) + '" ' +
                'data-original="' + parseFloat(comb.weight || 0).toFixed(2) + '" ' +
                'data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="weight" ' +
                'step="0.01" min="0">' +
            '</td>' +
            '<td class="pam-col-dim text-center">' +
                '<input type="number" class="form-control input-sm pam-input" ' +
                'value="' + parseFloat(comb.width || 0).toFixed(2) + '" ' +
                'data-original="' + parseFloat(comb.width || 0).toFixed(2) + '" ' +
                'data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="width" ' +
                'step="0.01" min="0">' +
            '</td>' +
            '<td class="pam-col-dim text-center">' +
                '<input type="number" class="form-control input-sm pam-input" ' +
                'value="' + parseFloat(comb.height || 0).toFixed(2) + '" ' +
                'data-original="' + parseFloat(comb.height || 0).toFixed(2) + '" ' +
                'data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="height" ' +
                'step="0.01" min="0">' +
            '</td>' +
            '<td class="pam-col-dim text-center">' +
                '<input type="number" class="form-control input-sm pam-input" ' +
                'value="' + parseFloat(comb.depth || 0).toFixed(2) + '" ' +
                'data-original="' + parseFloat(comb.depth || 0).toFixed(2) + '" ' +
                'data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="depth" ' +
                'step="0.01" min="0">' +
            '</td>' +
            '<td class="pam-col-actions"></td>' +
        '</tr>';
    };

    PAM.escapeHtml = function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    PAM.onInputChange = function($input) {
        var original = $input.data('original') + '';
        var current = $input.val();
        var id = $input.data('id');
        var attr = $input.data('attr') || 0;
        var field = $input.data('field');
        var key = attr > 0 ? id + '_' + attr : id + '';

        if (current !== original) {
            $input.addClass('pam-changed');
            if (!this.changes[key]) this.changes[key] = {};
            this.changes[key][field] = current;
        } else {
            $input.removeClass('pam-changed');
            if (this.changes[key]) {
                delete this.changes[key][field];
                if (Object.keys(this.changes[key]).length === 0) {
                    delete this.changes[key];
                }
            }
        }
        this.updateSaveBtn();
    };

    PAM.updateSaveBtn = function() {
        var count = 0;
        for (var k in this.changes) {
            count += Object.keys(this.changes[k]).length;
        }
        var $btn = $('#pam-save-all');
        var $badge = $btn.find('.pam-changes-count');
        if (count > 0) {
            $btn.prop('disabled', false);
            $badge.text(count).show();
        } else {
            $btn.prop('disabled', true);
            $badge.hide();
        }
    };

    PAM.saveSingle = function($input) {
        var self = this;
        var id = $input.data('id');
        var attr = $input.data('attr') || 0;
        var field = $input.data('field');
        var value = $input.val();
        var key = attr > 0 ? id + '_' + attr : id + '';

        $input.prop('disabled', true).addClass('pam-saving');

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'updateProduct',
                id_product: id,
                id_product_attribute: attr,
                field: field,
                value: value
            },
            success: function(r) {
                $input.prop('disabled', false).removeClass('pam-saving');
                if (r && r.success) {
                    $input.data('original', r.value).val(r.value).removeClass('pam-changed').addClass('pam-success');
                    if (self.changes[key]) {
                        delete self.changes[key][field];
                        if (Object.keys(self.changes[key]).length === 0) delete self.changes[key];
                    }
                    self.updateSaveBtn();
                    setTimeout(function() { $input.removeClass('pam-success'); }, 1500);
                } else {
                    $input.addClass('pam-error');
                    self.notify(r && r.message ? r.message : 'Errore', 'danger');
                    setTimeout(function() { $input.removeClass('pam-error'); }, 2000);
                }
            },
            error: function() {
                $input.prop('disabled', false).removeClass('pam-saving').addClass('pam-error');
                self.notify('Errore di connessione', 'danger');
                setTimeout(function() { $input.removeClass('pam-error'); }, 2000);
            }
        });
    };

    PAM.saveAll = function() {
        var self = this;
        var total = 0;
        for (var k in this.changes) total += Object.keys(this.changes[k]).length;
        if (total === 0) return;

        var $btn = $('#pam-save-all').prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Salvataggio...');

        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'updateProductBatch',
                changes: this.changes
            },
            success: function(r) {
                if (r && r.success) {
                    for (var key in self.changes) {
                        for (var field in self.changes[key]) {
                            var parts = key.split('_');
                            var id = parts[0];
                            var attr = parts[1] || '0';
                            var $inp = $('.pam-input[data-id="' + id + '"][data-attr="' + attr + '"][data-field="' + field + '"]');
                            var val = self.changes[key][field];
                            $inp.data('original', val).val(val).removeClass('pam-changed').addClass('pam-success');
                        }
                    }
                    self.changes = {};
                    self.updateSaveBtn();
                    setTimeout(function() { $('.pam-input').removeClass('pam-success'); }, 1500);
                    self.notify(r.message || 'Salvato', 'success');
                } else {
                    self.notify(r && r.message ? r.message : 'Errore', 'danger');
                }
                $btn.html('<i class="icon-save"></i> Salva Tutto');
                self.updateSaveBtn();
            },
            error: function() {
                self.notify('Errore di connessione', 'danger');
                $btn.html('<i class="icon-save"></i> Salva Tutto');
                self.updateSaveBtn();
            }
        });
    };

    PAM.notify = function(msg, type) {
        var $n = $('#pam-notification');
        $n.removeClass('alert-success alert-danger alert-warning alert-info')
          .addClass('alert-' + type).html(msg).fadeIn(200);
        setTimeout(function() { $n.fadeOut(300); }, 2500);
    };

})(jQuery);
