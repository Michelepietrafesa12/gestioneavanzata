<?php
/**
 * Controller per Gestione Avanzata Prodotti
 * @version 3.0.0 - Ottimizzato per PrestaShop 8.2
 */

class AdminProductAdvancedController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'product';
        $this->className = 'Product';
        $this->identifier = 'id_product';
        $this->lang = true;
        $this->_defaultOrderBy = 'id_product';
        $this->_defaultOrderWay = 'DESC';

        parent::__construct();

        $this->meta_title = $this->l('Gestione Avanzata Prodotti');
    }

    public function init()
    {
        parent::init();

        // Gestione AJAX
        if (Tools::getValue('ajax')) {
            // Verifica token CSRF per tutte le azioni AJAX
            if (!$this->isTokenValid()) {
                $this->ajaxResponse(['success' => false, 'message' => 'Invalid security token']);
            }

            $action = Tools::getValue('action');
            if ($action === 'updateProduct') {
                $this->ajaxProcessUpdateProduct();
            } elseif ($action === 'updateProductBatch') {
                $this->ajaxProcessUpdateProductBatch();
            } elseif ($action === 'getCombinations') {
                $this->ajaxProcessGetCombinations();
            } elseif ($action === 'sendStockAlert') {
                $this->ajaxProcessSendStockAlert();
            }
        }
    }

    /**
     * Verifica token CSRF di PrestaShop
     */
    private function isTokenValid()
    {
        $token = Tools::getValue('token');
        return !empty($token) && $token === Tools::getAdminTokenLite('AdminProductAdvanced');
    }

    /**
     * Risposta AJAX standardizzata (sostituisce die(json_encode(...)))
     */
    private function ajaxResponse(array $data)
    {
        header('Content-Type: application/json');
        if (ob_get_level()) {
            ob_end_clean();
        }
        echo json_encode($data);
        exit;
    }

    /**
     * Invia alert email via AJAX
     */
    public function ajaxProcessSendStockAlert()
    {
        $module = Module::getInstanceByName('productadvancedmanager');
        if ($module && $module->active) {
            $result = $module->runStockAlert(true);
            $this->ajaxResponse($result);
        } else {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('Modulo non attivo')]);
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        // Carica solo CSS - il JS è inline nel template per evitare problemi di configurazione
        $this->addCSS(_PS_MODULE_DIR_ . 'productadvancedmanager/views/css/admin.css');
    }

    public function initContent()
    {
        parent::initContent();

        // Parametri
        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Tools::getValue('per_page', 50);
        if (!in_array($perPage, [25, 50, 100, 200])) {
            $perPage = 50;
        }
        $search = Tools::getValue('search', '');
        $categoryFilter = (int) Tools::getValue('category', 0);
        $orderBy = Tools::getValue('order_by', 'id_product');
        $orderWay = Tools::getValue('order_way', 'DESC');

        // Nuovi filtri
        $stockFilter = Tools::getValue('stock_filter', ''); // '', '0', 'low'
        $activeFilter = Tools::getValue('active_filter', ''); // '', '1', '0'

        // Validazione
        $allowedOrderBy = ['id_product', 'name', 'reference', 'weight', 'width', 'height', 'depth', 'quantity'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'id_product';
        }
        $orderWay = strtoupper($orderWay) === 'ASC' ? 'ASC' : 'DESC';

        // Dati
        $products = $this->getProducts($page, $perPage, $search, $categoryFilter, $orderBy, $orderWay, $stockFilter, $activeFilter);
        $totalProducts = $this->getTotalProducts($search, $categoryFilter, $stockFilter, $activeFilter);
        $totalPages = max(1, ceil($totalProducts / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        // Categorie
        $categories = Category::getCategories($this->context->language->id, true, false);

        // Conta prodotti a stock 0 per badge (con cache)
        $outOfStockCount = $this->getOutOfStockCount();

        // Assegna al template
        $this->context->smarty->assign([
            'products' => $products,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $totalProducts,
            'per_page' => $perPage,
            'search' => $search,
            'category_filter' => $categoryFilter,
            'categories' => $categories,
            'order_by' => $orderBy,
            'order_way' => $orderWay,
            'stock_filter' => $stockFilter,
            'active_filter' => $activeFilter,
            'out_of_stock_count' => $outOfStockCount,
            'ajax_url' => $this->context->link->getAdminLink('AdminProductAdvanced'),
            'token' => Tools::getAdminTokenLite('AdminProductAdvanced'),
            'module_config_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=productadvancedmanager',
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'productadvancedmanager/views/templates/admin/product_advanced.tpl'
        );
        $this->context->smarty->assign('content', $this->content);
    }

    private function getProducts($page, $perPage, $search = '', $categoryId = 0, $orderBy = 'id_product', $orderWay = 'DESC', $stockFilter = '', $activeFilter = '')
    {
        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;
        $offset = ($page - 1) * $perPage;

        $sql = new DbQuery();
        $sql->select('p.id_product, p.reference, p.price, p.id_tax_rules_group, p.weight, p.width, p.height, p.depth, p.active, pl.name');
        $sql->select('IFNULL(sa.quantity, 0) as quantity, sa.id_stock_available');
        $sql->select('cl.name as category_name');
        // Fix: join image filtrata per shop
        $sql->select('IFNULL(ish.id_image, i.id_image) as id_image');
        // Data di scadenza
        $sql->select('ped.expiration_date');
        $sql->from('product', 'p');
        $sql->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . (int)$shopId);
        $sql->leftJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . (int)$langId . ' AND pl.id_shop = ' . (int)$shopId);
        $sql->leftJoin('stock_available', 'sa', 'sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . (int)$shopId);
        $sql->leftJoin('category_lang', 'cl', 'cl.id_category = p.id_category_default AND cl.id_lang = ' . (int)$langId . ' AND cl.id_shop = ' . (int)$shopId);
        // Image con filtro shop (image_shop ha priorità, fallback su image.cover)
        $sql->leftJoin('image_shop', 'ish', 'ish.id_product = p.id_product AND ish.cover = 1 AND ish.id_shop = ' . (int)$shopId);
        $sql->leftJoin('image', 'i', 'i.id_product = p.id_product AND i.cover = 1 AND ish.id_image IS NULL');
        // Join tabella scadenza
        $sql->leftJoin('product_expiration_date', 'ped', 'ped.id_product = p.id_product');

        if (!empty($search)) {
            $searchSafe = pSQL($search);
            $sql->where("(pl.name LIKE '%{$searchSafe}%' OR p.reference LIKE '%{$searchSafe}%' OR p.id_product = '{$searchSafe}')");
        }

        if ($categoryId > 0) {
            $sql->innerJoin('category_product', 'cp', 'cp.id_product = p.id_product AND cp.id_category = ' . (int)$categoryId);
        }

        // Filtro Stock - include prodotti con combinazioni a stock 0
        if ($stockFilter === '0') {
            $sql->leftJoin('stock_available', 'sa_comb', 'sa_comb.id_product = p.id_product AND sa_comb.id_product_attribute > 0 AND sa_comb.id_shop = ' . (int)$shopId . ' AND sa_comb.quantity = 0');
            $sql->where('(IFNULL(sa.quantity, 0) = 0 OR sa_comb.id_stock_available IS NOT NULL)');
            $sql->groupBy('p.id_product');
        } elseif ($stockFilter === 'low') {
            $sql->where('IFNULL(sa.quantity, 0) > 0 AND IFNULL(sa.quantity, 0) <= 5');
        }

        // Filtro Attivo
        if ($activeFilter === '1') {
            $sql->where('ps.active = 1');
        } elseif ($activeFilter === '0') {
            $sql->where('ps.active = 0');
        }

        // Ordinamento
        if ($orderBy === 'name') {
            $sql->orderBy('pl.name ' . $orderWay);
        } elseif ($orderBy === 'quantity') {
            $sql->orderBy('quantity ' . $orderWay);
        } else {
            $sql->orderBy('p.' . $orderBy . ' ' . $orderWay);
        }

        $sql->limit((int)$perPage, (int)$offset);

        $results = Db::getInstance()->executeS($sql);

        if ($results) {
            // Raccogli gli ID prodotto della pagina corrente
            $productIds = array_map(function ($r) {
                return (int)$r['id_product'];
            }, $results);
            $productIdsStr = implode(',', $productIds);

            // OTTIMIZZATO: conta combinazioni SOLO per i prodotti della pagina
            $combinationsCount = [];
            $countSql = 'SELECT pa.id_product, COUNT(*) as cnt
                         FROM ' . _DB_PREFIX_ . 'product_attribute pa
                         INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_shop pas
                             ON pa.id_product_attribute = pas.id_product_attribute AND pas.id_shop = ' . (int)$shopId . '
                         WHERE pa.id_product IN (' . $productIdsStr . ')
                         GROUP BY pa.id_product';
            $countResults = Db::getInstance()->executeS($countSql);
            if ($countResults) {
                foreach ($countResults as $row) {
                    $combinationsCount[(int)$row['id_product']] = (int)$row['cnt'];
                }
            }

            // OTTIMIZZATO: conta combinazioni OOS SOLO per i prodotti della pagina
            $combinationsOOS = [];
            $oosSql = 'SELECT pa.id_product, COUNT(*) as cnt
                       FROM ' . _DB_PREFIX_ . 'product_attribute pa
                       INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_shop pas
                           ON pa.id_product_attribute = pas.id_product_attribute AND pas.id_shop = ' . (int)$shopId . '
                       INNER JOIN ' . _DB_PREFIX_ . 'stock_available sa
                           ON pa.id_product_attribute = sa.id_product_attribute AND sa.id_shop = ' . (int)$shopId . '
                       WHERE sa.quantity = 0 AND pa.id_product IN (' . $productIdsStr . ')
                       GROUP BY pa.id_product';
            $oosResults = Db::getInstance()->executeS($oosSql);
            if ($oosResults) {
                foreach ($oosResults as $row) {
                    $combinationsOOS[(int)$row['id_product']] = (int)$row['cnt'];
                }
            }

            // Recupera tutte le aliquote IVA in una sola query
            $taxRatesCache = $this->getAllTaxRates();

            foreach ($results as &$row) {
                // Aggiungi conteggio combinazioni
                $row['combinations_count'] = isset($combinationsCount[(int)$row['id_product']])
                    ? $combinationsCount[(int)$row['id_product']]
                    : 0;

                // Aggiungi conteggio combinazioni OOS
                $row['combinations_oos'] = isset($combinationsOOS[(int)$row['id_product']])
                    ? $combinationsOOS[(int)$row['id_product']]
                    : 0;

                // Calcola prezzo con IVA (usando la cache)
                $taxRate = isset($taxRatesCache[(int)$row['id_tax_rules_group']])
                    ? $taxRatesCache[(int)$row['id_tax_rules_group']]
                    : 0;
                $row['price_tax_incl'] = (float)$row['price'] * (1 + $taxRate / 100);
                $row['tax_rate'] = $taxRate;

                if ($row['id_image']) {
                    $row['image_url'] = $this->context->link->getImageLink(
                        Tools::str2url($row['name']),
                        $row['id_image'],
                        'small_default'
                    );
                } else {
                    $row['image_url'] = '';
                }
            }
        }

        return $results ?: [];
    }

    /**
     * Ottiene l'aliquota IVA per un tax rules group (usato per singoli aggiornamenti AJAX)
     */
    private function getTaxRate($idTaxRulesGroup)
    {
        if (!$idTaxRulesGroup) {
            return 0;
        }

        $idCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        $sql = new DbQuery();
        $sql->select('t.rate');
        $sql->from('tax_rule', 'tr');
        $sql->innerJoin('tax', 't', 't.id_tax = tr.id_tax');
        $sql->where('tr.id_tax_rules_group = ' . (int)$idTaxRulesGroup);
        $sql->where('tr.id_country = ' . (int)$idCountry);
        $sql->orderBy('tr.id_tax_rule ASC');

        $rate = Db::getInstance()->getValue($sql);

        return $rate ? (float)$rate : 0;
    }

    /**
     * Sincronizza lo stock del prodotto principale con la somma delle varianti
     * Da chiamare dopo ogni modifica dello stock di una variante
     */
    private function syncProductStock($idProduct, $shopId)
    {
        // Verifica se il prodotto ha varianti
        $hasVariants = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product = ' . (int)$idProduct
        );

        if ($hasVariants > 0) {
            // Calcola la somma degli stock di tutte le varianti
            $totalStock = (int) Db::getInstance()->getValue(
                'SELECT SUM(sa.quantity)
                 FROM ' . _DB_PREFIX_ . 'stock_available sa
                 WHERE sa.id_product = ' . (int)$idProduct . '
                 AND sa.id_product_attribute > 0
                 AND sa.id_shop = ' . (int)$shopId
            );

            // Aggiorna lo stock del prodotto principale (id_product_attribute = 0)
            Db::getInstance()->update('stock_available', [
                'quantity' => $totalStock,
            ], 'id_product = ' . (int)$idProduct . ' AND id_product_attribute = 0 AND id_shop = ' . (int)$shopId);
        }
    }

    /**
     * Recupera TUTTE le aliquote IVA in una sola query
     * Restituisce array [id_tax_rules_group => rate]
     */
    private function getAllTaxRates()
    {
        $idCountry = (int) Configuration::get('PS_COUNTRY_DEFAULT');

        $sql = '
            SELECT tr.id_tax_rules_group, t.rate
            FROM ' . _DB_PREFIX_ . 'tax_rule tr
            INNER JOIN ' . _DB_PREFIX_ . 'tax t ON t.id_tax = tr.id_tax
            WHERE tr.id_country = ' . (int)$idCountry . '
            GROUP BY tr.id_tax_rules_group
        ';

        $results = Db::getInstance()->executeS($sql);

        $cache = [];
        if ($results) {
            foreach ($results as $row) {
                $cache[(int)$row['id_tax_rules_group']] = (float)$row['rate'];
            }
        }

        return $cache;
    }

    private function getTotalProducts($search = '', $categoryId = 0, $stockFilter = '', $activeFilter = '')
    {
        $shopId = (int) $this->context->shop->id;
        $langId = (int) $this->context->language->id;

        $sql = new DbQuery();
        $sql->select('COUNT(DISTINCT p.id_product)');
        $sql->from('product', 'p');
        $sql->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product AND ps.id_shop = ' . (int)$shopId);
        $sql->leftJoin('product_lang', 'pl', 'pl.id_product = p.id_product AND pl.id_lang = ' . (int)$langId . ' AND pl.id_shop = ' . (int)$shopId);
        $sql->leftJoin('stock_available', 'sa', 'sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . (int)$shopId);

        if (!empty($search)) {
            $searchSafe = pSQL($search);
            $sql->where("(pl.name LIKE '%{$searchSafe}%' OR p.reference LIKE '%{$searchSafe}%' OR p.id_product = '{$searchSafe}')");
        }

        if ($categoryId > 0) {
            $sql->innerJoin('category_product', 'cp', 'cp.id_product = p.id_product AND cp.id_category = ' . (int)$categoryId);
        }

        // Filtro Stock - include prodotti con combinazioni a stock 0
        if ($stockFilter === '0') {
            $sql->leftJoin('stock_available', 'sa_comb', 'sa_comb.id_product = p.id_product AND sa_comb.id_product_attribute > 0 AND sa_comb.id_shop = ' . (int)$shopId . ' AND sa_comb.quantity = 0');
            $sql->where('(IFNULL(sa.quantity, 0) = 0 OR sa_comb.id_stock_available IS NOT NULL)');
        } elseif ($stockFilter === 'low') {
            $sql->where('IFNULL(sa.quantity, 0) > 0 AND IFNULL(sa.quantity, 0) <= 5');
        }

        // Filtro Attivo
        if ($activeFilter === '1') {
            $sql->where('ps.active = 1');
        } elseif ($activeFilter === '0') {
            $sql->where('ps.active = 0');
        }

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Conta prodotti e varianti attivi a stock 0
     * Con cache per evitare ricalcoli ad ogni page load
     */
    private function getOutOfStockCount()
    {
        $shopId = (int) $this->context->shop->id;
        $cacheKey = 'pam_oos_count_' . $shopId;

        // Controlla cache (5 minuti)
        $cached = Configuration::get($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if ($data && isset($data['ts']) && (time() - $data['ts']) < 300) {
                return (int) $data['count'];
            }
        }

        // Conta prodotti semplici attivi a stock 0
        $sqlSimple = '
            SELECT COUNT(DISTINCT p.id_product)
            FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = ' . (int)$shopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'stock_available sa ON p.id_product = sa.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . (int)$shopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON p.id_product = pa.id_product
            WHERE ps.active = 1
                AND pa.id_product_attribute IS NULL
                AND IFNULL(sa.quantity, 0) = 0';

        $countSimple = (int) Db::getInstance()->getValue($sqlSimple);

        // Conta combinazioni attive a stock 0
        $sqlComb = '
            SELECT COUNT(DISTINCT pa.id_product_attribute)
            FROM ' . _DB_PREFIX_ . 'product_attribute pa
            INNER JOIN ' . _DB_PREFIX_ . 'product p ON pa.id_product = p.id_product
            INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON p.id_product = ps.id_product AND ps.id_shop = ' . (int)$shopId . '
            INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_shop pas ON pa.id_product_attribute = pas.id_product_attribute AND pas.id_shop = ' . (int)$shopId . '
            INNER JOIN ' . _DB_PREFIX_ . 'stock_available sa ON pa.id_product_attribute = sa.id_product_attribute AND sa.id_shop = ' . (int)$shopId . '
            WHERE ps.active = 1 AND sa.quantity = 0';

        $countComb = (int) Db::getInstance()->getValue($sqlComb);

        $total = $countSimple + $countComb;

        // Salva in cache
        Configuration::updateValue($cacheKey, json_encode(['ts' => time(), 'count' => $total]));

        return $total;
    }

    /**
     * Ottiene le combinazioni di un prodotto via AJAX
     * Usa GROUP_CONCAT per ottenere nomi attributi in una sola query
     */
    public function ajaxProcessGetCombinations()
    {
        $idProduct = (int) Tools::getValue('id_product');

        if (!$idProduct) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('ID prodotto mancante')]);
        }

        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;

        $sql = new DbQuery();
        $sql->select('pa.id_product_attribute, pa.reference, pa.price as price_impact');
        $sql->select('IFNULL(sa.quantity, 0) as quantity');
        $sql->select('GROUP_CONCAT(DISTINCT al.name ORDER BY agl.id_attribute_group ASC SEPARATOR " - ") as attribute_name');
        $sql->from('product_attribute', 'pa');
        // Filtra per shop
        $sql->innerJoin('product_attribute_shop', 'pas', 'pas.id_product_attribute = pa.id_product_attribute AND pas.id_shop = ' . (int)$shopId);
        $sql->leftJoin('stock_available', 'sa', 'sa.id_product = pa.id_product AND sa.id_product_attribute = pa.id_product_attribute AND sa.id_shop = ' . (int)$shopId);
        // JOIN per gli attributi
        $sql->leftJoin('product_attribute_combination', 'pac', 'pac.id_product_attribute = pa.id_product_attribute');
        $sql->leftJoin('attribute', 'a', 'a.id_attribute = pac.id_attribute');
        $sql->leftJoin('attribute_lang', 'al', 'al.id_attribute = a.id_attribute AND al.id_lang = ' . (int)$langId);
        $sql->leftJoin('attribute_group_lang', 'agl', 'agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = ' . (int)$langId);
        $sql->where('pa.id_product = ' . (int)$idProduct);
        $sql->groupBy('pa.id_product_attribute');
        $sql->orderBy('pa.id_product_attribute ASC');

        $combinations = Db::getInstance()->executeS($sql);

        // Fallback per combinazioni senza nome attributo
        if ($combinations) {
            foreach ($combinations as &$comb) {
                if (empty($comb['attribute_name'])) {
                    $comb['attribute_name'] = 'Variante #' . $comb['id_product_attribute'];
                }
            }
        }

        $this->ajaxResponse([
            'success' => true,
            'combinations' => $combinations ?: [],
            'id_product' => $idProduct
        ]);
    }

    /**
     * Aggiorna singolo prodotto via AJAX
     */
    public function ajaxProcessUpdateProduct()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $idProductAttribute = (int) Tools::getValue('id_product_attribute', 0);
        $field = Tools::getValue('field');
        $value = Tools::getValue('value');

        // Validazione ID
        if (!$idProduct) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('ID prodotto mancante')]);
        }

        // Campi consentiti
        $allowedFields = ['price', 'weight', 'width', 'height', 'depth', 'quantity', 'price_impact', 'expiration_date'];
        if (!in_array($field, $allowedFields)) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('Campo non valido')]);
        }

        // Validazione valore
        if ($field !== 'expiration_date' && !is_numeric($value)) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('Valore non valido')]);
        }

        if ($field === 'expiration_date') {
            // Accetta stringa vuota (rimozione data) o formato YYYY-MM-DD
            if (!empty($value) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $this->ajaxResponse(['success' => false, 'message' => $this->l('Formato data non valido')]);
            }
        }

        // Solo quantity e dimensioni devono essere >= 0, price e price_impact possono essere qualsiasi
        if (in_array($field, ['weight', 'width', 'height', 'depth', 'quantity']) && (float)$value < 0) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('Valore non valido')]);
        }

        try {
            $shopId = (int) $this->context->shop->id;
            $displayValue = null;

            if ($field === 'expiration_date') {
                $dateValue = empty($value) ? null : pSQL($value);

                // Controlla se esiste già un record
                $exists = (int) Db::getInstance()->getValue(
                    'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_expiration_date WHERE id_product = ' . $idProduct
                );

                if ($dateValue === null) {
                    $result = Db::getInstance()->delete('product_expiration_date', 'id_product = ' . $idProduct);
                } elseif ($exists) {
                    $result = Db::getInstance()->update('product_expiration_date', [
                        'expiration_date' => $dateValue,
                    ], 'id_product = ' . $idProduct);
                } else {
                    $result = Db::getInstance()->insert('product_expiration_date', [
                        'id_product' => $idProduct,
                        'expiration_date' => $dateValue,
                    ]);
                }

                $displayValue = $dateValue ?: '';
            } elseif ($field === 'quantity') {
                $quantity = (int) $value;

                $result = Db::getInstance()->update('stock_available', [
                    'quantity' => $quantity,
                ], 'id_product = ' . $idProduct . ' AND id_product_attribute = ' . $idProductAttribute . ' AND id_shop = ' . $shopId);

                if ($idProductAttribute > 0) {
                    $this->syncProductStock($idProduct, $shopId);
                }

                $displayValue = $quantity;
            } elseif ($field === 'price' && $idProductAttribute == 0) {
                $priceTaxIncl = round((float) $value, 6);

                $idTaxRulesGroup = (int) Db::getInstance()->getValue(
                    'SELECT id_tax_rules_group FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . $idProduct
                );
                $taxRate = $this->getTaxRate($idTaxRulesGroup);

                $priceNet = round($priceTaxIncl / (1 + $taxRate / 100), 6);

                Db::getInstance()->update('product', [
                    'price' => $priceNet,
                ], 'id_product = ' . $idProduct);

                $result = Db::getInstance()->update('product_shop', [
                    'price' => $priceNet,
                ], 'id_product = ' . $idProduct . ' AND id_shop = ' . $shopId);

                // Ricalcola il prezzo IVA inclusa dal netto salvato per evitare arrotondamenti
                $displayValue = number_format($priceNet * (1 + $taxRate / 100), 2, '.', '');
            } elseif ($field === 'price_impact') {
                $priceImpact = round((float) $value, 6);

                $result = Db::getInstance()->update('product_attribute', [
                    'price' => $priceImpact,
                ], 'id_product_attribute = ' . $idProductAttribute);

                $displayValue = number_format((float)$value, 2, '.', '');
            } else {
                $floatValue = round((float) $value, 6);

                if ($idProductAttribute > 0) {
                    $result = Db::getInstance()->update('product_attribute', [
                        $field => $floatValue,
                    ], 'id_product_attribute = ' . $idProductAttribute);
                } else {
                    $result = Db::getInstance()->update('product', [
                        $field => $floatValue,
                    ], 'id_product = ' . $idProduct);
                }

                $displayValue = number_format((float)$value, 2, '.', '');
            }

            if ($result !== false) {
                // Invalida cache OOS dopo modifica stock
                if ($field === 'quantity') {
                    $this->invalidateOosCache();
                }

                $this->ajaxResponse([
                    'success' => true,
                    'message' => $this->l('Aggiornato'),
                    'value' => $displayValue,
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idProductAttribute,
                    'field' => $field
                ]);
            } else {
                $this->ajaxResponse(['success' => false, 'message' => $this->l('Errore nel salvataggio')]);
            }
        } catch (Exception $e) {
            $this->ajaxResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Aggiorna più prodotti via AJAX (batch)
     */
    public function ajaxProcessUpdateProductBatch()
    {
        $changes = Tools::getValue('changes');

        if (!$changes || !is_array($changes)) {
            $this->ajaxResponse(['success' => false, 'message' => $this->l('Dati non validi')]);
        }

        $updated = 0;
        $shopId = (int) $this->context->shop->id;
        $productsToSync = [];
        $hasStockChanges = false;

        foreach ($changes as $key => $fields) {
            $parts = explode('_', $key);
            $idProduct = (int) $parts[0];
            $idProductAttribute = isset($parts[1]) ? (int) $parts[1] : 0;

            if (!$idProduct) continue;

            foreach ($fields as $field => $value) {
                if ($field === 'expiration_date') {
                    $dateValue = empty($value) ? null : pSQL($value);
                    if ($dateValue !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                        continue;
                    }
                    $exists = (int) Db::getInstance()->getValue(
                        'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_expiration_date WHERE id_product = ' . $idProduct
                    );
                    if ($dateValue === null) {
                        Db::getInstance()->delete('product_expiration_date', 'id_product = ' . $idProduct);
                    } elseif ($exists) {
                        Db::getInstance()->update('product_expiration_date', [
                            'expiration_date' => $dateValue,
                        ], 'id_product = ' . $idProduct);
                    } else {
                        Db::getInstance()->insert('product_expiration_date', [
                            'id_product' => $idProduct,
                            'expiration_date' => $dateValue,
                        ]);
                    }
                    $updated++;
                } elseif ($field === 'quantity') {
                    $quantity = (int) $value;
                    if ($quantity >= 0) {
                        Db::getInstance()->update('stock_available', [
                            'quantity' => $quantity,
                        ], 'id_product = ' . $idProduct . ' AND id_product_attribute = ' . $idProductAttribute . ' AND id_shop = ' . $shopId);
                        $updated++;
                        $hasStockChanges = true;

                        if ($idProductAttribute > 0) {
                            $productsToSync[$idProduct] = true;
                        }
                    }
                } elseif ($field === 'price' && $idProductAttribute == 0) {
                    $priceTaxIncl = round((float) $value, 6);
                    if ($priceTaxIncl >= 0) {
                        $idTaxRulesGroup = (int) Db::getInstance()->getValue(
                            'SELECT id_tax_rules_group FROM ' . _DB_PREFIX_ . 'product WHERE id_product = ' . $idProduct
                        );
                        $taxRate = $this->getTaxRate($idTaxRulesGroup);
                        $priceNet = round($priceTaxIncl / (1 + $taxRate / 100), 6);

                        Db::getInstance()->update('product', [
                            'price' => $priceNet,
                        ], 'id_product = ' . $idProduct);
                        Db::getInstance()->update('product_shop', [
                            'price' => $priceNet,
                        ], 'id_product = ' . $idProduct . ' AND id_shop = ' . $shopId);
                        $updated++;
                    }
                } elseif ($field === 'price_impact' && $idProductAttribute > 0) {
                    $priceImpact = round((float) $value, 6);
                    Db::getInstance()->update('product_attribute', [
                        'price' => $priceImpact,
                    ], 'id_product_attribute = ' . $idProductAttribute);
                    $updated++;
                } elseif (in_array($field, ['weight', 'width', 'height', 'depth'])) {
                    $floatValue = round((float) $value, 6);
                    if ($floatValue >= 0) {
                        if ($idProductAttribute > 0) {
                            Db::getInstance()->update('product_attribute', [
                                $field => $floatValue,
                            ], 'id_product_attribute = ' . $idProductAttribute);
                        } else {
                            Db::getInstance()->update('product', [
                                $field => $floatValue,
                            ], 'id_product = ' . $idProduct);
                        }
                        $updated++;
                    }
                }
            }
        }

        // Sincronizza lo stock dei prodotti principali per tutte le varianti modificate
        foreach ($productsToSync as $idProduct => $flag) {
            $this->syncProductStock($idProduct, $shopId);
        }

        // Invalida cache OOS dopo modifica stock
        if ($hasStockChanges) {
            $this->invalidateOosCache();
        }

        $this->ajaxResponse([
            'success' => true,
            'updated' => $updated,
            'message' => sprintf($this->l('%d modifiche salvate'), $updated)
        ]);
    }

    /**
     * Invalida la cache del conteggio OOS
     */
    private function invalidateOosCache()
    {
        $shopId = (int) $this->context->shop->id;
        Configuration::deleteByName('pam_oos_count_' . $shopId);
    }
}
