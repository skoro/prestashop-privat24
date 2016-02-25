<?php
/**
 * @author Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
 * @copyright 2016 Soft-Industry
 * @version $Id$
 * @since 1.0.0
 */

/**
 * Validate callback.
 * 
 * @author skoro
 */
class Privat24ValidationModuleFrontController extends ModuleFrontController
{
    
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        // Check that this payment option is still available in case the 
        // customer changed his address just before the end of the checkout process.
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'privat24') {
                $authorized = true;
                break;
            }
        }
        
        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }
        
        // Log requests from Privat API side in Debug mode.
        if (Configuration::get('PRIVAT24_DEBUG_MODE')) {
            $logger = new FileLogger();
            $logger->setFilename(_PS_ROOT_DIR_.'/log/'.$this->module->name.'_'.date('Ymd_His').'_response.log');
            $logger->logError($_POST);
        }
        
        $payment = Tools::getValue('payment');
        $hash = sha1(md5($payment . $this->module->merchant_password));
        
        if ($payment && $hash === Tools::getValue('signature')) {
            $cart_id = (int)Tools::getValue('order');
            $cart = new Cart($cart_id);
            $amount = $cart->getOrderTotal();
            $this->module->validateOrder($cart->id, Configuration::get('PRIVAT24_WAITINGPAYMENT_OS'), $amount, $this->module->displayName);
        } else {
            PrestaShopLogger::addLog('Privat24: Payment callback bad signature.', 3, null, null, null, true);
        }
        
        die();
    }
    
}