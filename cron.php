<?php
/**
 * Product Advanced Manager - Cron endpoint
 * 
 * Chiamare via cron del server:
 * curl -s "https://tuosito.it/modules/productadvancedmanager/cron.php?token=TUO_TOKEN"
 */

// Carica PrestaShop
$dir = dirname(__FILE__);
$configPath = $dir . '/../../config/config.inc.php';

if (!file_exists($configPath)) {
    die('Config not found');
}

require_once($configPath);

// Verifica token sicurezza
$token = Configuration::get('PAM_CRON_TOKEN');
if (empty($token)) {
    $token = Tools::passwdGen(32);
    Configuration::updateValue('PAM_CRON_TOKEN', $token);
}

if (!Tools::getValue('token') || !hash_equals($token, Tools::getValue('token'))) {
    header('HTTP/1.1 403 Forbidden');
    die('Invalid token');
}

// Esegui controllo stock
require_once($dir . '/productadvancedmanager.php');

$module = Module::getInstanceByName('productadvancedmanager');
if ($module && $module->active) {
    $result = $module->runStockAlert();
    echo $result['message'];
} else {
    echo 'Module not active';
}
