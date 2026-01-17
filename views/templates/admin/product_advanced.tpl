{**
 * Template Gestione Avanzata Prodotti
 * @version 2.2.0
 *}

<div class="panel" id="product-advanced-manager">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Gestione Avanzata Prodotti' mod='productadvancedmanager'}
        <span class="badge">{$total_products}</span>
        {if $out_of_stock_count > 0}
            <span class="badge badge-danger" style="background:#d32f2f;margin-left:5px;" title="{l s='Prodotti attivi a stock 0' mod='productadvancedmanager'}">
                <i class="icon-warning"></i> {$out_of_stock_count} OOS
            </span>
        {/if}
        <div class="panel-heading-action pull-right" style="margin-top:-5px;">
            <a href="{$module_config_url}" class="btn btn-default btn-sm" title="{l s='Configura Alert Email' mod='productadvancedmanager'}">
                <i class="icon-cog"></i>
            </a>
            <button type="button" class="btn btn-warning btn-sm" id="pam-send-alert" title="{l s='Invia Alert Email' mod='productadvancedmanager'}">
                <i class="icon-envelope"></i> {l s='Invia Alert' mod='productadvancedmanager'}
            </button>
        </div>
    </div>
    
    <div class="panel-body">
        <!-- Filtri -->
        <form method="get" action="index.php" id="pam-filter-form">
            <input type="hidden" name="controller" value="AdminProductAdvanced">
            <input type="hidden" name="token" value="{$token|escape:'html':'UTF-8'}">
            
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="{l s='Cerca per nome, riferimento o ID...' mod='productadvancedmanager'}" 
                               value="{$search|escape:'html':'UTF-8'}">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="submit">
                                <i class="icon-search"></i>
                            </button>
                        </span>
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="0">{l s='Tutte le categorie' mod='productadvancedmanager'}</option>
                        {foreach from=$categories item=category}
                            <option value="{$category.id_category|intval}"{if $category.id_category == $category_filter} selected{/if}>
                                {$category.name|escape:'html':'UTF-8'}
                            </option>
                        {/foreach}
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="stock_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">{l s='Tutto lo stock' mod='productadvancedmanager'}</option>
                        <option value="0"{if $stock_filter == '0'} selected{/if}>{l s='Stock = 0' mod='productadvancedmanager'}</option>
                        <option value="low"{if $stock_filter == 'low'} selected{/if}>{l s='Stock basso (1-5)' mod='productadvancedmanager'}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="active_filter" class="form-control" onchange="this.form.submit()">
                        <option value="">{l s='Tutti i prodotti' mod='productadvancedmanager'}</option>
                        <option value="1"{if $active_filter == '1'} selected{/if}>{l s='Solo Attivi' mod='productadvancedmanager'}</option>
                        <option value="0"{if $active_filter == '0'} selected{/if}>{l s='Solo Disattivi' mod='productadvancedmanager'}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="per_page" class="form-control" onchange="this.form.submit()">
                        <option value="25"{if $per_page == 25} selected{/if}>25</option>
                        <option value="50"{if $per_page == 50} selected{/if}>50</option>
                        <option value="100"{if $per_page == 100} selected{/if}>100</option>
                        <option value="200"{if $per_page == 200} selected{/if}>200</option>
                    </select>
                </div>
                <div class="col-md-2 text-right">
                    <button type="button" class="btn btn-success" id="pam-save-all" disabled>
                        <i class="icon-save"></i> {l s='Salva Tutto' mod='productadvancedmanager'}
                        <span class="badge pam-changes-count" style="display:none;">0</span>
                    </button>
                </div>
            </div>
            
            {* Filtro rapido per stock 0 attivi *}
            {if $stock_filter != '0' || $active_filter != '1'}
            <div class="row" style="margin-bottom:10px;">
                <div class="col-md-12">
                    <a href="index.php?controller=AdminProductAdvanced&token={$token|escape:'html':'UTF-8'}&stock_filter=0&active_filter=1" class="btn btn-xs btn-danger">
                        <i class="icon-warning"></i> {l s='Mostra prodotti/varianti ATTIVI a Stock 0' mod='productadvancedmanager'}
                        {if $out_of_stock_count > 0}<span class="badge">{$out_of_stock_count}</span>{/if}
                    </a>
                    {if $stock_filter != '' || $active_filter != '' || $search != '' || $category_filter > 0}
                    <a href="index.php?controller=AdminProductAdvanced&token={$token|escape:'html':'UTF-8'}" class="btn btn-xs btn-default">
                        <i class="icon-remove"></i> {l s='Reset filtri' mod='productadvancedmanager'}
                    </a>
                    {/if}
                </div>
            </div>
            {/if}
        </form>

        <!-- Tabella -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-condensed" id="pam-table">
                <thead>
                    <tr>
                        <th class="pam-col-id">ID</th>
                        <th class="pam-col-img">{l s='Img' mod='productadvancedmanager'}</th>
                        <th class="pam-col-name">{l s='Prodotto' mod='productadvancedmanager'}</th>
                        <th class="pam-col-ref">{l s='Rif.' mod='productadvancedmanager'}</th>
                        <th class="pam-col-price text-center">{l s='Prezzo' mod='productadvancedmanager'} <small>(€)</small></th>
                        <th class="pam-col-stock text-center">{l s='Stock' mod='productadvancedmanager'}</th>
                        <th class="pam-col-dim text-center">{l s='Peso' mod='productadvancedmanager'} <small>(kg)</small></th>
                        <th class="pam-col-dim text-center">{l s='Larg.' mod='productadvancedmanager'} <small>(cm)</small></th>
                        <th class="pam-col-dim text-center">{l s='Alt.' mod='productadvancedmanager'} <small>(cm)</small></th>
                        <th class="pam-col-dim text-center">{l s='Prof.' mod='productadvancedmanager'} <small>(cm)</small></th>
                        <th class="pam-col-actions">{l s='Azioni' mod='productadvancedmanager'}</th>
                    </tr>
                </thead>
                <tbody id="pam-tbody">
                    {if $products}
                        {foreach from=$products item=product}
                            <tr data-id="{$product.id_product}" class="pam-product-row{if $product.quantity == 0 && $product.combinations_count == 0} pam-row-oos{/if}{if $product.combinations_oos > 0} pam-has-oos{/if}{if $product.active == 0} pam-row-inactive{/if}">
                                <td class="pam-col-id">
                                    {$product.id_product}
                                    {if $product.combinations_count > 0}
                                        <button type="button" class="btn btn-default btn-xs pam-expand-btn" data-id="{$product.id_product}" title="{l s='Mostra varianti' mod='productadvancedmanager'}">
                                            <i class="icon-plus"></i> <small>{$product.combinations_count}</small>
                                        </button>
                                        {if $product.combinations_oos > 0}
                                            <span class="label label-danger" title="{$product.combinations_oos} {l s='varianti a stock 0' mod='productadvancedmanager'}">{$product.combinations_oos} OOS</span>
                                        {/if}
                                    {/if}
                                    {if $product.active == 0}
                                        <span class="label label-default" title="{l s='Disattivo' mod='productadvancedmanager'}">OFF</span>
                                    {/if}
                                </td>
                                <td class="pam-col-img">
                                    {if $product.image_url}
                                        <img src="{$product.image_url}" alt="" class="img-thumbnail" style="max-width:40px;max-height:40px;">
                                    {else}
                                        <i class="icon-picture-o text-muted"></i>
                                    {/if}
                                </td>
                                <td class="pam-col-name" title="{$product.name|escape:'html':'UTF-8'}">
                                    {$product.name|escape:'html':'UTF-8'}
                                </td>
                                <td class="pam-col-ref">
                                    <code>{$product.reference|escape:'html':'UTF-8'}</code>
                                </td>
                                <td class="pam-col-price text-center">
                                    <input type="number" class="form-control input-sm pam-input" 
                                           value="{$product.price_tax_incl|string_format:"%.2f"}"
                                           data-original="{$product.price_tax_incl|string_format:"%.2f"}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="price"
                                           step="0.01" min="0">
                                </td>
                                <td class="pam-col-stock text-center">
                                    <input type="number" class="form-control input-sm pam-input{if $product.quantity == 0} pam-stock-zero{/if}" 
                                           value="{$product.quantity|intval}"
                                           data-original="{$product.quantity|intval}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="quantity"
                                           step="1" min="0">
                                </td>
                                <td class="pam-col-dim text-center">
                                    <input type="number" class="form-control input-sm pam-input" 
                                           value="{$product.weight|string_format:"%.2f"}"
                                           data-original="{$product.weight|string_format:"%.2f"}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="weight"
                                           step="0.01" min="0">
                                </td>
                                <td class="pam-col-dim text-center">
                                    <input type="number" class="form-control input-sm pam-input" 
                                           value="{$product.width|string_format:"%.2f"}"
                                           data-original="{$product.width|string_format:"%.2f"}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="width"
                                           step="0.01" min="0">
                                </td>
                                <td class="pam-col-dim text-center">
                                    <input type="number" class="form-control input-sm pam-input" 
                                           value="{$product.height|string_format:"%.2f"}"
                                           data-original="{$product.height|string_format:"%.2f"}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="height"
                                           step="0.01" min="0">
                                </td>
                                <td class="pam-col-dim text-center">
                                    <input type="number" class="form-control input-sm pam-input" 
                                           value="{$product.depth|string_format:"%.2f"}"
                                           data-original="{$product.depth|string_format:"%.2f"}"
                                           data-id="{$product.id_product}" data-attr="0" data-field="depth"
                                           step="0.01" min="0">
                                </td>
                                <td class="pam-col-actions text-center">
                                    <a href="{$link->getAdminLink('AdminProducts')}&id_product={$product.id_product}&updateproduct" 
                                       class="btn btn-default btn-xs" target="_blank" title="{l s='Modifica' mod='productadvancedmanager'}">
                                        <i class="icon-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        {/foreach}
                    {else}
                        <tr>
                            <td colspan="11" class="text-center text-muted">
                                <p style="padding: 30px 0;">
                                    <i class="icon-inbox" style="font-size: 36px;"></i><br><br>
                                    {l s='Nessun prodotto trovato' mod='productadvancedmanager'}
                                </p>
                            </td>
                        </tr>
                    {/if}
                </tbody>
            </table>
        </div>

        <!-- Paginazione -->
        {if $total_pages > 1}
            <div class="row" style="margin-top: 15px;">
                <div class="col-md-6">
                    <p class="text-muted" style="margin-top:8px;">
                        {l s='Pagina' mod='productadvancedmanager'} <strong>{$current_page}</strong> / <strong>{$total_pages}</strong>
                        &nbsp;—&nbsp; {$total_products} {l s='prodotti' mod='productadvancedmanager'}
                    </p>
                </div>
                <div class="col-md-6 text-right">
                    <ul class="pagination" style="margin: 0;">
                        {if $current_page > 1}
                            <li><a href="{$ajax_url}&page=1{if $search}&search={$search|escape:'url'}{/if}{if $category_filter > 0}&category={$category_filter}{/if}{if $per_page != 50}&per_page={$per_page}{/if}{if $order_by != 'id_product'}&order_by={$order_by}{/if}{if $order_way != 'DESC'}&order_way={$order_way}{/if}"><i class="icon-angle-double-left"></i></a></li>
                            <li><a href="{$ajax_url}&page={$current_page - 1}{if $search}&search={$search|escape:'url'}{/if}{if $category_filter > 0}&category={$category_filter}{/if}{if $per_page != 50}&per_page={$per_page}{/if}{if $order_by != 'id_product'}&order_by={$order_by}{/if}{if $order_way != 'DESC'}&order_way={$order_way}{/if}"><i class="icon-angle-left"></i></a></li>
                        {/if}
                        {assign var="start" value=max(1, $current_page - 2)}
                        {assign var="end" value=min($total_pages, $current_page + 2)}
                        {for $p=$start to $end}
                            <li{if $p == $current_page} class="active"{/if}><a href="{$ajax_url}&page={$p}{if $search}&search={$search|escape:'url'}{/if}{if $category_filter > 0}&category={$category_filter}{/if}{if $per_page != 50}&per_page={$per_page}{/if}{if $order_by != 'id_product'}&order_by={$order_by}{/if}{if $order_way != 'DESC'}&order_way={$order_way}{/if}">{$p}</a></li>
                        {/for}
                        {if $current_page < $total_pages}
                            <li><a href="{$ajax_url}&page={$current_page + 1}{if $search}&search={$search|escape:'url'}{/if}{if $category_filter > 0}&category={$category_filter}{/if}{if $per_page != 50}&per_page={$per_page}{/if}{if $order_by != 'id_product'}&order_by={$order_by}{/if}{if $order_way != 'DESC'}&order_way={$order_way}{/if}"><i class="icon-angle-right"></i></a></li>
                            <li><a href="{$ajax_url}&page={$total_pages}{if $search}&search={$search|escape:'url'}{/if}{if $category_filter > 0}&category={$category_filter}{/if}{if $per_page != 50}&per_page={$per_page}{/if}{if $order_by != 'id_product'}&order_by={$order_by}{/if}{if $order_way != 'DESC'}&order_way={$order_way}{/if}"><i class="icon-angle-double-right"></i></a></li>
                        {/if}
                    </ul>
                </div>
            </div>
        {/if}
    </div>
</div>

<!-- Notifica -->
<div id="pam-notification" class="alert" style="display:none; position:fixed; top:70px; right:20px; z-index:9999; min-width:250px;"></div>

{literal}
<script type="text/javascript">
(function() {
    var ajaxUrl = '{/literal}{$ajax_url|escape:'javascript':'UTF-8'}{literal}';
    var changes = {};
    var expandedProducts = {};

    function init() {
        if (typeof jQuery === 'undefined') {
            setTimeout(init, 100);
            return;
        }
        
        var $ = jQuery;
        console.log('PAM: Inizializzato');

        // Click su pulsante espandi
        $(document).on('click', '.pam-expand-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var btn = $(this);
            var idProduct = btn.data('id');
            var icon = btn.find('i');
            var row = btn.closest('tr');
            var isExpanded = btn.hasClass('pam-expanded');
            
            if (isExpanded) {
                $('.pam-comb-row[data-parent="' + idProduct + '"]').remove();
                btn.removeClass('pam-expanded');
                icon.removeClass('icon-minus').addClass('icon-plus');
                delete expandedProducts[idProduct];
            } else {
                icon.removeClass('icon-plus').addClass('icon-refresh icon-spin');
                
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        ajax: 1,
                        action: 'getCombinations',
                        id_product: idProduct
                    },
                    success: function(r) {
                        icon.removeClass('icon-refresh icon-spin');
                        
                        if (r && r.success && r.combinations && r.combinations.length > 0) {
                            icon.addClass('icon-minus');
                            btn.addClass('pam-expanded');
                            expandedProducts[idProduct] = true;
                            
                            var html = '';
                            for (var i = 0; i < r.combinations.length; i++) {
                                html += buildCombRow(idProduct, r.combinations[i]);
                            }
                            row.after(html);
                        } else {
                            icon.addClass('icon-plus');
                            notify('Nessuna variante trovata', 'warning');
                        }
                    },
                    error: function() {
                        icon.removeClass('icon-refresh icon-spin').addClass('icon-plus');
                        notify('Errore caricamento', 'danger');
                    }
                });
            }
            
            return false;
        });

        // Input change
        $(document).on('change', '.pam-input', function() {
            var inp = $(this);
            var orig = String(inp.data('original'));
            var curr = inp.val();
            var id = inp.data('id');
            var attr = inp.data('attr') || 0;
            var field = inp.data('field');
            var key = attr > 0 ? id + '_' + attr : String(id);

            if (curr !== orig) {
                inp.addClass('pam-changed');
                if (!changes[key]) changes[key] = {};
                changes[key][field] = curr;
            } else {
                inp.removeClass('pam-changed');
                if (changes[key]) {
                    delete changes[key][field];
                    if (Object.keys(changes[key]).length === 0) delete changes[key];
                }
            }
            updateSaveBtn();
        });

        // Blur - salva singolo
        $(document).on('blur', '.pam-input', function() {
            var inp = $(this);
            if (inp.hasClass('pam-changed')) {
                saveSingle(inp);
            }
        });

        // Enter
        $(document).on('keypress', '.pam-input', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).blur();
            }
        });

        // Salva tutto
        $('#pam-save-all').on('click', function() {
            saveAll();
        });
    }

    function buildCombRow(idProduct, c) {
        var idAttr = c.id_product_attribute;
        var name = c.attribute_name || ('Variante #' + idAttr);
        var ref = c.reference || '';
        var qty = parseInt(c.quantity) || 0;
        var rowClass = 'pam-comb-row' + (qty === 0 ? ' pam-comb-oos' : '');
        var inputClass = 'form-control input-sm pam-input' + (qty === 0 ? ' pam-stock-zero' : '');
        
        // Per le varianti: solo Quantità è editabile
        return '<tr class="' + rowClass + '" data-parent="' + idProduct + '">' +
            '<td class="pam-col-id"><small class="text-muted">↳</small></td>' +
            '<td class="pam-col-img"></td>' +
            '<td class="pam-col-name" style="padding-left:20px;font-style:italic;color:#666;">' + escHtml(name) + '</td>' +
            '<td class="pam-col-ref"><code>' + escHtml(ref) + '</code></td>' +
            '<td class="pam-col-price text-center text-muted">-</td>' +
            '<td class="pam-col-stock text-center"><input type="number" class="' + inputClass + '" value="' + qty + '" data-original="' + qty + '" data-id="' + idProduct + '" data-attr="' + idAttr + '" data-field="quantity" step="1" min="0"></td>' +
            '<td class="pam-col-dim text-center text-muted">-</td>' +
            '<td class="pam-col-dim text-center text-muted">-</td>' +
            '<td class="pam-col-dim text-center text-muted">-</td>' +
            '<td class="pam-col-dim text-center text-muted">-</td>' +
            '<td class="pam-col-actions"></td>' +
        '</tr>';
    }

    function escHtml(t) {
        if (!t) return '';
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function updateSaveBtn() {
        var count = 0;
        for (var k in changes) count += Object.keys(changes[k]).length;
        var btn = jQuery('#pam-save-all');
        var badge = btn.find('.pam-changes-count');
        if (count > 0) {
            btn.prop('disabled', false);
            badge.text(count).show();
        } else {
            btn.prop('disabled', true);
            badge.hide();
        }
    }

    function saveSingle(inp) {
        var $ = jQuery;
        var id = inp.data('id');
        var attr = inp.data('attr') || 0;
        var field = inp.data('field');
        var value = inp.val();
        var key = attr > 0 ? id + '_' + attr : String(id);

        inp.prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
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
                inp.prop('disabled', false);
                if (r && r.success) {
                    inp.data('original', r.value).val(r.value).removeClass('pam-changed').addClass('pam-success');
                    if (changes[key]) {
                        delete changes[key][field];
                        if (Object.keys(changes[key]).length === 0) delete changes[key];
                    }
                    updateSaveBtn();
                    setTimeout(function() { inp.removeClass('pam-success'); }, 1500);
                } else {
                    inp.addClass('pam-error');
                    notify(r && r.message ? r.message : 'Errore', 'danger');
                    setTimeout(function() { inp.removeClass('pam-error'); }, 2000);
                }
            },
            error: function() {
                inp.prop('disabled', false).addClass('pam-error');
                notify('Errore connessione', 'danger');
                setTimeout(function() { inp.removeClass('pam-error'); }, 2000);
            }
        });
    }

    function saveAll() {
        var $ = jQuery;
        var total = 0;
        for (var k in changes) total += Object.keys(changes[k]).length;
        if (total === 0) return;

        var btn = $('#pam-save-all').prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Salvataggio...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'updateProductBatch',
                changes: changes
            },
            success: function(r) {
                if (r && r.success) {
                    for (var key in changes) {
                        for (var field in changes[key]) {
                            var parts = key.split('_');
                            var id = parts[0];
                            var attr = parts[1] || '0';
                            var inp = $('.pam-input[data-id="' + id + '"][data-attr="' + attr + '"][data-field="' + field + '"]');
                            var val = changes[key][field];
                            inp.data('original', val).val(val).removeClass('pam-changed').addClass('pam-success');
                        }
                    }
                    changes = {};
                    updateSaveBtn();
                    setTimeout(function() { $('.pam-input').removeClass('pam-success'); }, 1500);
                    notify(r.message || 'Salvato!', 'success');
                } else {
                    notify(r && r.message ? r.message : 'Errore', 'danger');
                }
                btn.html('<i class="icon-save"></i> Salva Tutto');
                updateSaveBtn();
            },
            error: function() {
                notify('Errore connessione', 'danger');
                btn.html('<i class="icon-save"></i> Salva Tutto');
                updateSaveBtn();
            }
        });
    }

    function notify(msg, type) {
        var n = jQuery('#pam-notification');
        n.removeClass('alert-success alert-danger alert-warning alert-info')
         .addClass('alert-' + type).html(msg).fadeIn(200);
        setTimeout(function() { n.fadeOut(300); }, 2500);
    }
    
    function sendStockAlert() {
        var $ = jQuery;
        var btn = $('#pam-send-alert');
        var originalHtml = btn.html();
        
        btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Invio...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                ajax: 1,
                action: 'sendStockAlert'
            },
            success: function(r) {
                btn.prop('disabled', false).html(originalHtml);
                if (r && r.success) {
                    notify(r.message || 'Email inviata!', 'success');
                } else {
                    notify(r && r.message ? r.message : 'Errore invio', 'danger');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalHtml);
                notify('Errore connessione', 'danger');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Bind pulsante alert
    jQuery(document).ready(function() {
        jQuery('#pam-send-alert').on('click', sendStockAlert);
    });
})();
</script>
{/literal}
