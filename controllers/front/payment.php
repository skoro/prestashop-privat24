<?php
/**
 * @author Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
 * @copyright 2015 Soft-Industry
 * @version $Id$
 * @since 1.0.0
 */

/**
 * Payment callback.
 * 
 * @author skoro
 */
class Privat24PaymentModuleFrontController extends ModuleFrontController
{
    
    /**
     * @var boolean disable left sidebar.
     */
	public $display_column_left = false;
    
	/**
	 * @see FrontController::initContent()
	 */
    public function initContent()
    {
        parent::initContent();
        
        /** @var $cart Cart */
        /** @var $this->module Privat24 */

        if (!$this->module->active) {
            return;
        }
        
        $cart = $this->context->cart;
        $currency = new Currency((int)$cart->id_currency);
        $amount = $cart->getOrderTotal();
        
        $details = array();
        foreach ($cart->getProducts() as $product) {
            $details[] = $product['name'];
        }
        
        $customer = new Customer((int)$this->context->cart->id_customer);        
        $this->module->validateOrder($cart->id, Configuration::get('PRIVAT24_WAITINGPAYMENT_OS'), $amount, $this->module->displayName, null, array(), null, false, $customer->secure_key);
        
        $this->context->smarty->assign(array(
            'payment_url' => Privat24::PAY_PB_URL,
            'currency' => $currency->iso_code,
            'details' => implode(', ', $details),
            'amount' => $amount,
            'merchant_id' => $this->module->merchant_id,
            'order_id' => $cart->id,
            'return_url' => $this->context->link->getPageLink('order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder),
        ));
        
        $this->setTemplate('payment_execution.tpl');
    }
    
}