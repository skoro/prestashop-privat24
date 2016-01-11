<?php
/**
 * @author Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
 * @copyright 2015 Soft-Industry
 * @version $Id$
 * @since 1.0.0
 */

if (!defined('_PS_VERSION_')) exit;

/**
 * Privat24
 * 
 * Provides payment gateway for Privat24 service.
 * 
 * @author skoro
 */
class Privat24 extends PaymentModule
{
    
    /**
     * @var string
     */
    public $merchant_id;
    
    /**
     * @var string
     */
    public $merchant_password;
    
    /**
     * API url for payment card.
     */
    const PAY_PB_URL = 'https://api.privatbank.ua/p24api/ishop';
    
    public function __construct()
    {
        $this->name = 'privat24';
        $this->version = '0.1';
        $this->author = 'Soft Industry';
        $this->tab = 'payments_gateways';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->need_instance = 0;
        $this->bootstrap = true;
        
        $this->merchant_id = Configuration::get('PRIVAT24_MERCHANT_ID');
        $this->merchant_password = Configuration::get('PRIVAT24_MERCHANT_PASSWORD');
        
        parent::__construct();
        
        $this->displayName = $this->l('Privat24');
        $this->description = $this->l('Provides Privat24 payment gateway.');
        
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall ?');
        
        if (empty($this->merchant_id) || empty($this->merchant_password)) {
            $this->warning = $this->l('Please setup your Privat24 merchant account.');
        }
    }
    
    
    public function install()
    {
        return parent::install()
                && $this->registerHook('payment')
                && $this->registerHook('paymentReturn')
                && $this->createOrderState();
    }
    
    
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Create module order status.
     * 
     * @return boolean
     */
    protected function createOrderState()
    {
        if (!Configuration::get('PRIVAT24_WAITINGPAYMENT_OS')) {
            $os = new OrderState();
            $os->name = array();
            foreach (Language::getLanguages(false) as $language) {
                switch (Tools::strtolower($language['iso_code'])) {
                    case 'ru' :
                        $status = 'Ожидание оплаты Приват24';
                        break;
                    case 'ua' :
                        $status = 'Очікування платежу Приват24';
                        break;
                    default :
                        $status = 'Waiting payment ' . $this->displayName;
                }
                $os->name[(int)$language['id_lang']] = $status;
            }
			$os->color = '#4169E1';
			$os->hidden = false;
			$os->send_email = false;
			$os->delivery = false;
			$os->logable = false;
			$os->invoice = false;
            if ($os->add()) {
                Configuration::updateValue('PRIVAT24_WAITINGPAYMENT_OS', $os->id);
                copy(dirname(__FILE__) . '/logo.gif', dirname(__FILE__) . '/../../img/os/' . (int)$os->id . '.gif');
            } else {
                return false;
            }
            return true;
        }
    }
    
    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $merchant_id = strval(Tools::getValue('PRIVAT24_MERCHANT_ID'));
            $merchant_password = strval(Tools::getValue('PRIVAT24_MERCHANT_PASSWORD'));
            // TODO: merchant_id is big integer ?
            if (!$merchant_id || !Validate::isGenericName($merchant_id) || !$merchant_password) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('PRIVAT24_MERCHANT_ID', $merchant_id);
                Configuration::updateValue('PRIVAT24_MERCHANT_PASSWORD', $merchant_password);
                $this->merchant_id = $merchant_id;
                $this->merchant_password = $merchant_password;
                $output .= $this->displayConfirmation($this->l('Merchant id has been updated.'));
            }
        }
        return $output . $this->displayForm();
    }
    
    public function displayForm()
    {
        $helper = new HelperForm();
        
        $fields_form = array();
        $fields_form['cfg']['form'] = array(
            'legend' => array(
                'title' => $this->l('Privat24 settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Merchant ID'),
                    'name' => 'PRIVAT24_MERCHANT_ID',
                    'required' => true,
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('Merchant password'),
                    'name' => 'PRIVAT24_MERCHANT_PASSWORD',
                    'size' => 60,
                    'required' => true,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
        );
        
        $helper->submit_action = 'submit' . $this->name;
        $helper->fields_value['PRIVAT24_MERCHANT_ID'] = Configuration::get('PRIVAT24_MERCHANT_ID');
        
        return $helper->generateForm($fields_form);
    }
    
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        
        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
            'id_cart' => (int) $params['cart']->id,
        ));

        return $this->display(__FILE__, 'payment.tpl');
    }
    
    public function hookPaymentReturn()
    {
        
    }
    
}