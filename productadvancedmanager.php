<?php
/**
 * Product Advanced Manager
 * Gestione avanzata prodotti: peso, dimensioni e stock
 *
 * @author CompraloSubito24
 * @version 3.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductAdvancedManager extends Module
{
    public function __construct()
    {
        $this->name = 'productadvancedmanager';
        $this->tab = 'administration';
        $this->version = '3.0.0';
        $this->author = 'CompraloSubito24';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Product Advanced Manager');
        $this->description = $this->l('Gestione avanzata prodotti: modifica inline di peso, dimensioni e stock + alert email stock esaurito.');
        $this->confirmUninstall = $this->l('Sei sicuro di voler disinstallare questo modulo?');
    }

    public function install()
    {
        return parent::install()
            && $this->installTab()
            && Configuration::updateValue('PAM_ALERT_EMAIL', Configuration::get('PS_SHOP_EMAIL'))
            && Configuration::updateValue('PAM_ALERT_ENABLED', 1)
            && Configuration::updateValue('PAM_ALERT_LAST_RUN', '')
            && Configuration::updateValue('PAM_CRON_TOKEN', Tools::passwdGen(32));
    }

    public function uninstall()
    {
        return $this->uninstallTab() 
            && Configuration::deleteByName('PAM_ALERT_EMAIL')
            && Configuration::deleteByName('PAM_ALERT_ENABLED')
            && Configuration::deleteByName('PAM_ALERT_LAST_RUN')
            && Configuration::deleteByName('PAM_CRON_TOKEN')
            && parent::uninstall();
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminProductAdvanced';
        $tab->name = [];
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Gestione Avanzata';
        }
        
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminCatalog');
        $tab->module = $this->name;
        $tab->icon = 'settings';

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminProductAdvanced');
        
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        
        return true;
    }

    /**
     * Pagina configurazione modulo
     */
    public function getContent()
    {
        $output = '';

        // Salvataggio configurazione
        if (Tools::isSubmit('submitPamConfig')) {
            $email = Tools::getValue('PAM_ALERT_EMAIL');
            $enabled = (int) Tools::getValue('PAM_ALERT_ENABLED');

            // Valida email (supporta multiple separate da virgola)
            $emails = array_map('trim', explode(',', $email));
            $valid = true;
            foreach ($emails as $e) {
                if (!empty($e) && !Validate::isEmail($e)) {
                    $valid = false;
                    break;
                }
            }

            if (!$valid) {
                $output .= $this->displayError($this->l('Una o più email non sono valide'));
            } else {
                Configuration::updateValue('PAM_ALERT_EMAIL', $email);
                Configuration::updateValue('PAM_ALERT_ENABLED', $enabled);
                $output .= $this->displayConfirmation($this->l('Impostazioni salvate'));
            }
        }

        // Test manuale
        if (Tools::getValue('submitPamTest')) {
            $result = $this->runStockAlert(true);
            if ($result['success']) {
                $output .= $this->displayConfirmation($result['message']);
            } else {
                $output .= $this->displayError($result['message']);
            }
        }

        return $output . $this->renderConfigForm();
    }

    /**
     * Form configurazione
     */
    protected function renderConfigForm()
    {
        $lastRun = Configuration::get('PAM_ALERT_LAST_RUN');
        $lastRunText = $lastRun ? $lastRun : $this->l('Mai eseguito');
        
        $testUrl = AdminController::$currentIndex . '&configure=' . $this->name 
            . '&token=' . Tools::getAdminTokenLite('AdminModules') . '&submitPamTest=1';

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configurazione Alert Stock Esaurito'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Alert Email Abilitato'),
                        'name' => 'PAM_ALERT_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Sì')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Email destinatario'),
                        'name' => 'PAM_ALERT_EMAIL',
                        'size' => 50,
                        'required' => true,
                        'desc' => $this->l('Email dove ricevere gli alert. Per più destinatari separa con virgola.'),
                    ],
                    [
                        'type' => 'html',
                        'name' => 'last_run_info',
                        'html_content' => '<div class="alert alert-info">'
                            . '<strong>' . $this->l('Ultimo controllo:') . '</strong> ' . $lastRunText
                            . '</div>',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Salva'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitPamConfig';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value['PAM_ALERT_ENABLED'] = Configuration::get('PAM_ALERT_ENABLED');
        $helper->fields_value['PAM_ALERT_EMAIL'] = Configuration::get('PAM_ALERT_EMAIL');

        $form = $helper->generateForm([$fields_form]);
        
        // Pannello Test
        $testPanel = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-envelope"></i> ' . $this->l('Test Invio Email') . '
            </div>
            <div class="panel-body">
                <p>' . $this->l('Clicca il pulsante per eseguire un controllo immediato e inviare l\'email di test.') . '</p>
                <a href="' . $testUrl . '" class="btn btn-primary">
                    <i class="icon-paper-plane"></i> ' . $this->l('Esegui Test Ora') . '
                </a>
            </div>
        </div>';
        
        // Pannello Cron
        $cronToken = Configuration::get('PAM_CRON_TOKEN');
        if (empty($cronToken)) {
            $cronToken = Tools::passwdGen(32);
            Configuration::updateValue('PAM_CRON_TOKEN', $cronToken);
        }
        $cronUrl = $this->context->shop->getBaseURL(true) . 'modules/productadvancedmanager/cron.php?token=' . $cronToken;
        
        $cronPanel = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-time"></i> ' . $this->l('Configurazione Cron') . '
            </div>
            <div class="panel-body">
                <p>' . $this->l('Per eseguire il controllo automatico, configura un cron job sul tuo server con questo URL:') . '</p>
                <div class="input-group">
                    <input type="text" class="form-control" value="' . $cronUrl . '" readonly onclick="this.select();">
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(\'' . $cronUrl . '\'); alert(\'URL copiato!\');">
                            <i class="icon-copy"></i> ' . $this->l('Copia') . '
                        </button>
                    </span>
                </div>
                <br>
                <p><strong>' . $this->l('Esempio cron (ogni 2 ore):') . '</strong></p>
                <pre style="background: #f5f5f5; padding: 10px;">0 */2 * * * curl -s "' . $cronUrl . '" > /dev/null</pre>
            </div>
        </div>';
        
        // Pannello Link
        $adminLink = $this->context->link->getAdminLink('AdminProductAdvanced');
        $linkPanel = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-list"></i> ' . $this->l('Gestione Prodotti') . '
            </div>
            <div class="panel-body">
                <p>' . $this->l('Vai alla pagina di gestione avanzata prodotti per vedere e filtrare i prodotti a stock 0.') . '</p>
                <a href="' . $adminLink . '&stock_filter=0" class="btn btn-default">
                    <i class="icon-warning"></i> ' . $this->l('Vedi Prodotti Stock 0') . '
                </a>
                <a href="' . $adminLink . '" class="btn btn-default">
                    <i class="icon-list"></i> ' . $this->l('Gestione Avanzata') . '
                </a>
            </div>
        </div>';

        return $form . $testPanel . $cronPanel . $linkPanel;
    }

    /**
     * Esegue il controllo stock e invia email
     */
    public function runStockAlert($isTest = false)
    {
        if (!$isTest && !Configuration::get('PAM_ALERT_ENABLED')) {
            return ['success' => false, 'message' => $this->l('Alert disabilitato')];
        }

        $email = Configuration::get('PAM_ALERT_EMAIL');
        if (empty($email)) {
            return ['success' => false, 'message' => $this->l('Email non configurata')];
        }

        // Query: varianti a stock 0 di prodotti attivi
        $results = $this->getOutOfStockProducts();

        // Query: prodotti in scadenza entro 3 mesi
        $expiringProducts = $this->getExpiringProducts();

        if (empty($results) && empty($expiringProducts)) {
            $message = $this->l('Nessun prodotto a stock 0 o in scadenza trovato');
            return ['success' => true, 'message' => $message];
        }

        // Costruisci email HTML
        $html = $this->buildAlertEmailHtml($results, $expiringProducts);
        $totalIssues = count($results) + count($expiringProducts);
        $subject = sprintf('[%s] Alert: %d Stock 0 / %d In Scadenza', Configuration::get('PS_SHOP_NAME'), count($results), count($expiringProducts));

        // Invia email
        $emails = array_map('trim', explode(',', $email));
        $sent = false;

        foreach ($emails as $toEmail) {
            if (Validate::isEmail($toEmail)) {
                $sent = Mail::send(
                    (int) Configuration::get('PS_LANG_DEFAULT'),
                    'pam_stock_alert',
                    $subject,
                    [
                        '{stock_alert_content}' => $html,
                        '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
                        '{shop_url}' => $this->context->shop->getBaseURL(true),
                        '{total_count}' => $totalIssues,
                    ],
                    $toEmail,
                    null,
                    null,
                    null,
                    null,
                    null,
                    dirname(__FILE__) . '/mails/'
                );
            }
        }

        Configuration::updateValue('PAM_ALERT_LAST_RUN', date('d/m/Y H:i:s'));

        if ($sent) {
            $message = sprintf($this->l('Email inviata! %d stock 0, %d in scadenza.'), count($results), count($expiringProducts));
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => $this->l('Errore invio email. Verifica configurazione SMTP.')];
        }
    }

    /**
     * Recupera prodotti e varianti a stock 0
     */
    public function getOutOfStockProducts()
    {
        $langId = (int) Configuration::get('PS_LANG_DEFAULT');
        $shopId = (int) Context::getContext()->shop->id;

        // Query unificata: prodotti semplici e varianti a stock 0
        $sql = '
            SELECT 
                p.id_product,
                pl.name AS product_name,
                p.reference AS product_reference,
                IFNULL(pa.id_product_attribute, 0) AS id_product_attribute,
                IFNULL(pa.reference, "") AS combination_reference,
                sa.quantity,
                IF(pa.id_product_attribute IS NULL, "simple", "combination") AS type,
                GROUP_CONCAT(DISTINCT CONCAT(agl.name, ": ", al.name) SEPARATOR ", ") AS attributes
            FROM ' . _DB_PREFIX_ . 'product p
            INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps 
                ON p.id_product = ps.id_product AND ps.id_shop = ' . $shopId . '
            INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl 
                ON p.id_product = pl.id_product 
                AND pl.id_lang = ' . $langId . '
                AND pl.id_shop = ' . $shopId . '
            INNER JOIN ' . _DB_PREFIX_ . 'stock_available sa 
                ON p.id_product = sa.id_product AND sa.id_shop = ' . $shopId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa 
                ON sa.id_product_attribute = pa.id_product_attribute AND pa.id_product = p.id_product
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac 
                ON pa.id_product_attribute = pac.id_product_attribute
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute a 
                ON pac.id_attribute = a.id_attribute
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_lang al 
                ON a.id_attribute = al.id_attribute AND al.id_lang = ' . $langId . '
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl 
                ON a.id_attribute_group = agl.id_attribute_group AND agl.id_lang = ' . $langId . '
            WHERE ps.active = 1
                AND sa.quantity = 0
            GROUP BY p.id_product, sa.id_product_attribute
            ORDER BY p.id_product ASC, pa.id_product_attribute ASC
        ';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Recupera prodotti attivi con data di scadenza entro 3 mesi
     */
    public function getExpiringProducts()
    {
        $langId = (int) Configuration::get('PS_LANG_DEFAULT');
        $shopId = (int) Context::getContext()->shop->id;

        $sql = '
            SELECT
                p.id_product,
                pl.name AS product_name,
                p.reference AS product_reference,
                ped.expiration_date,
                DATEDIFF(ped.expiration_date, CURDATE()) AS days_left
            FROM ' . _DB_PREFIX_ . 'product_expiration_date ped
            INNER JOIN ' . _DB_PREFIX_ . 'product p
                ON ped.id_product = p.id_product
            INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps
                ON p.id_product = ps.id_product AND ps.id_shop = ' . $shopId . '
            INNER JOIN ' . _DB_PREFIX_ . 'product_lang pl
                ON p.id_product = pl.id_product
                AND pl.id_lang = ' . $langId . '
                AND pl.id_shop = ' . $shopId . '
            WHERE ps.active = 1
                AND ped.expiration_date IS NOT NULL
                AND ped.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
                AND ped.expiration_date >= CURDATE()
            ORDER BY ped.expiration_date ASC
        ';

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Costruisce HTML email
     */
    protected function buildAlertEmailHtml($results, $expiringProducts = [])
    {
        $adminUrl = $this->context->shop->getBaseURL(true) . basename(_PS_ADMIN_DIR_);
        
        $html = '
        <table style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 13px;">
            <thead>
                <tr style="background-color: #1976d2; color: white;">
                    <th style="padding: 10px; text-align: left;">ID</th>
                    <th style="padding: 10px; text-align: left;">Prodotto</th>
                    <th style="padding: 10px; text-align: left;">Tipo</th>
                    <th style="padding: 10px; text-align: left;">Reference</th>
                    <th style="padding: 10px; text-align: left;">Attributi</th>
                    <th style="padding: 10px; text-align: center;">Azione</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($results as $row) {
            $reference = !empty($row['combination_reference']) 
                ? $row['combination_reference'] 
                : $row['product_reference'];
            
            $type = $row['type'] === 'combination' ? 'Variante' : 'Semplice';
            $typeColor = $row['type'] === 'combination' ? '#ff9800' : '#4caf50';
            
            $editUrl = $adminUrl . '/index.php?controller=AdminProducts&id_product=' 
                . $row['id_product'] . '&updateproduct';

            $html .= '
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 8px;">' . $row['id_product'] . '</td>
                    <td style="padding: 8px;">' . htmlspecialchars($row['product_name']) . '</td>
                    <td style="padding: 8px;"><span style="background:' . $typeColor . ';color:white;padding:2px 6px;border-radius:3px;font-size:11px;">' . $type . '</span></td>
                    <td style="padding: 8px;"><code>' . htmlspecialchars($reference) . '</code></td>
                    <td style="padding: 8px;">' . htmlspecialchars($row['attributes'] ?: '-') . '</td>
                    <td style="padding: 8px; text-align: center;"><a href="' . $editUrl . '" style="color:#1976d2;">Modifica</a></td>
                </tr>';
        }

        $html .= '</tbody></table>';

        // Sezione prodotti in scadenza
        if (!empty($expiringProducts)) {
            $html .= '
            <br><br>
            <h3 style="color:#e65100;font-family:Arial,sans-serif;">Prodotti in Scadenza (entro 3 mesi): ' . count($expiringProducts) . '</h3>
            <table style="border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 13px;">
                <thead>
                    <tr style="background-color: #e65100; color: white;">
                        <th style="padding: 10px; text-align: left;">ID</th>
                        <th style="padding: 10px; text-align: left;">Prodotto</th>
                        <th style="padding: 10px; text-align: left;">Reference</th>
                        <th style="padding: 10px; text-align: center;">Scadenza</th>
                        <th style="padding: 10px; text-align: center;">Giorni Rimasti</th>
                        <th style="padding: 10px; text-align: center;">Azione</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($expiringProducts as $row) {
                $daysLeft = (int) $row['days_left'];
                $urgencyColor = $daysLeft <= 30 ? '#d32f2f' : ($daysLeft <= 60 ? '#ff9800' : '#4caf50');

                $editUrl = $adminUrl . '/index.php?controller=AdminProducts&id_product='
                    . $row['id_product'] . '&updateproduct';

                $html .= '
                    <tr style="border-bottom: 1px solid #ddd;">
                        <td style="padding: 8px;">' . $row['id_product'] . '</td>
                        <td style="padding: 8px;">' . htmlspecialchars($row['product_name']) . '</td>
                        <td style="padding: 8px;"><code>' . htmlspecialchars($row['product_reference']) . '</code></td>
                        <td style="padding: 8px; text-align: center;">' . date('d/m/Y', strtotime($row['expiration_date'])) . '</td>
                        <td style="padding: 8px; text-align: center;"><span style="background:' . $urgencyColor . ';color:white;padding:2px 8px;border-radius:3px;font-weight:bold;">' . $daysLeft . 'gg</span></td>
                        <td style="padding: 8px; text-align: center;"><a href="' . $editUrl . '" style="color:#1976d2;">Modifica</a></td>
                    </tr>';
            }

            $html .= '</tbody></table>';
        }

        return $html;
    }
}
