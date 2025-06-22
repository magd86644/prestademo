<?php 
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/brandloyaltypoints.php';

$module = Module::getInstanceByName('brandloyaltypoints');

if (Validate::isLoadedObject($module)) {
    $module->sendLoyaltyExpiryReminders();
}
