<?php
/**
* 2016 Soft Industry
*
*   @author    Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
*   @copyright 2016 Soft-Industry
*   @version   $Id$
*   @license   http://opensource.org/licenses/afl-3.0.php
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

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
    
    /**
     * Contructor.
     */
    public function __construct()
    {
        $this->name = 'privat24';
        $this->version = '0.1.1';
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
        
        $this->installMails();
    }
    
    /**
     * Module installation.
     */
    public function install()
    {
        return parent::install()
                && $this->registerHook('payment')
                && $this->registerHook('paymentReturn')
                && $this->createOrderState()
                && $this->installMails();
    }
    
    /**
     * Module uninstallation.
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }
        
        Configuration::deleteByName('PRIVAT24_WAITINGPAYMENT_OS');
        Configuration::deleteByName('PRIVAT24_MERCHANT_ID');
        Configuration::deleteByName('PRIVAT24_MERCHANT_PASSWORD');
        Configuration::deleteByName('PRIVAT24_DEBUG_MODE');
        Configuration::deleteByName('PRIVAT24_PAYMENT_NOTIFY');
        Configuration::deleteByName('PRIVAT24_NOTIFY_EMAILS');
        
        $this->uninstallMails();
        
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
                    case 'ru':
                        $status = 'Ожидание оплаты Приват24';
                        break;
                    case 'ua':
                        $status = 'Очікування платежу Приват24';
                        break;
                    default:
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
    
    /**
     * Install mail templates.
     */
    protected function installMails()
    {
        $text = 'privat24_payment.txt';
        $html = 'privat24_payment.html';
        foreach (Language::getLanguages() as $language) {
            $dir = $this->local_path . 'mails/' . $language['iso_code'];
            if (file_exists($dir)) {
                $this->copyFile($dir . '/' . $text, _PS_MAIL_DIR_ . $language['iso_code'] . '/' . $text);
                $this->copyFile($dir . '/' . $html, _PS_MAIL_DIR_ . $language['iso_code'] . '/' . $html);
            }
        }
        
        return true;
    }
    
    /**
     * Uninstall mail templates.
     */
    protected function uninstallMails()
    {
        foreach (Language::getLanguages() as $language) {
            $dir = _PS_MAIL_DIR_ . $language['iso_code'];
            foreach (array('txt', 'html') as $ext) {
                $file = $dir . '/privat24_payment.' . $ext;
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
        return true;
    }
    
    /**
     * Copy file to destination.
     * @param string $source
     * @param string $dest
     */
    protected function copyFile($source, $dest)
    {
        if (@copy($source, $dest) == false) {
            $this->_errors[] = sprintf('Copy "%s" to "%s" failed.', $source, $dest);
        }
    }
    
    /**
     * Parse emails from a string data.
     * @param string $data
     * @return array
     */
    protected function validateEmails($data)
    {
        $lines = explode("\n", $data);
        $lines = array_filter($lines, 'trim');
        $data = implode(',', $lines);
        $data = explode(',', trim($data));
        $emails = array();
        foreach ($data as $email) {
            $email = trim($email);
            if (Validate::isEmail($email)) {
                $emails[] = $email;
            }
        }
        return $emails;
    }
    
    /**
     * Process module configuration form.
     *
     * @return string
     */
    public function getContent()
    {
        $output = $status = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $merchant_id = (string) Tools::getValue('PRIVAT24_MERCHANT_ID');
            $merchant_password = (string) Tools::getValue('PRIVAT24_MERCHANT_PASSWORD');
            $notify = (bool)Tools::getValue('PRIVAT24_PAYMENT_NOTIFY');
            $emails = Tools::getValue('PRIVAT24_NOTIFY_EMAILS');
            $status = '';
            // TODO: merchant_id is big integer ?
            if ($merchant_id && Validate::isGenericName($merchant_id) && $merchant_password) {
                $backend_uri = str_replace('index.php', '', $_SERVER['PHP_SELF']);
                Configuration::updateValue('PRIVAT24_BACKEND_URI', $backend_uri);
                Configuration::updateValue('PRIVAT24_MERCHANT_ID', $merchant_id);
                Configuration::updateValue('PRIVAT24_MERCHANT_PASSWORD', $merchant_password);
                Configuration::updateValue('PRIVAT24_DEBUG_MODE', (bool)Tools::getValue('PRIVAT24_DEBUG_MODE'));
                $this->merchant_id = $merchant_id;
                $this->merchant_password = $merchant_password;
                $validateEmails = $this->validateEmails($emails);
                if ($notify && !$validateEmails) {
                    $status = $this->displayError($this->l('Please enter emails separated by comma.'));
                } else {
                    Configuration::updateValue('PRIVAT24_PAYMENT_NOTIFY', $notify);
                    Configuration::updateValue('PRIVAT24_NOTIFY_EMAILS', $emails);
                    $status = $this->displayConfirmation($this->l('Merchant id has been updated.'));
                }
            } else {
                $status = $this->displayError($this->l('Invalid Configuration value'));
            }
        }
        return $output . $status . $this->displayForm();
    }
    
    /**
     * Module configuration form.
     *
     * @return string
     */
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
                    'type' => 'text',
                    'label' => $this->l('Merchant password'),
                    'name' => 'PRIVAT24_MERCHANT_PASSWORD',
                    'size' => 60,
                    'required' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable debug mode'),
                    'name' => 'PRIVAT24_DEBUG_MODE',
                    'is_bool' => true,
                    'values' => array(
                        array('value' => 0),
                        array('value' => 1),
                    ),
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Email notify when payment accepted'),
                    'name' => 'PRIVAT24_PAYMENT_NOTIFY',
                    'is_bool' => true,
                    'values' => array(
                        array('value' => 1),
                        array('value' => 0),
                    ),
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Emails'),
                    'name' => 'PRIVAT24_NOTIFY_EMAILS',
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
        );
        
        $helper->submit_action = 'submit' . $this->name;
        $helper->fields_value['PRIVAT24_MERCHANT_ID'] = Configuration::get('PRIVAT24_MERCHANT_ID');
        $helper->fields_value['PRIVAT24_MERCHANT_PASSWORD'] = Configuration::get('PRIVAT24_MERCHANT_PASSWORD');
        $helper->fields_value['PRIVAT24_DEBUG_MODE'] = Configuration::get('PRIVAT24_DEBUG_MODE');
        $helper->fields_value['PRIVAT24_PAYMENT_NOTIFY'] = Configuration::get('PRIVAT24_PAYMENT_NOTIFY');
        $helper->fields_value['PRIVAT24_NOTIFY_EMAILS'] = Tools::getValue(
            'PRIVAT24_NOTIFY_EMAILS',
            Configuration::get('PRIVAT24_NOTIFY_EMAILS')
        );
        
        return $helper->generateForm($fields_form);
    }
    
    /**
     * Implements hookPayment.
     */
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
    
    /**
     * Not used in this module.
     */
    public function hookPaymentReturn()
    {
        
    }
    
    /**
     * Notify emails about accepted payment.
     *
     * @param Order $order order instance
     * @param array $payment data from payment gateway
     * @return bool
     */
    public function paymentNotify(Order $order, array $payment)
    {
        if (!Configuration::get('PRIVAT24_PAYMENT_NOTIFY')) {
            return true;
        }
        if (!Validate::isLoadedObject($order)) {
            return false;
        }
        $emails = $this->validateEmails(Configuration::get('PRIVAT24_NOTIFY_EMAILS'));
        if ($emails) {
            $template = 'privat24_payment';
            $subject = $this->l('Payment accepted via Privat24');
            $context = Context::getContext();
            $data = array(
                '{order_name}' => $order->getUniqReference(),
                '{order_link}' =>
                        _PS_BASE_URL_ .
                        Configuration::get('PRIVAT24_BACKEND_URI') .
                        $context->link->getAdminLink('AdminOrders', false) . '&vieworder&id_order=' . (int)$order->id,
                '{amount}' => $payment['amt'] . ' ' . $context->currency->iso_code,
                '{payment_transaction}' => $payment['ref'],
            );
            foreach ($emails as $email) {
                Mail::Send((int)$order->id_lang, $template, $subject, $data, $email);
            }
        }
        return true;
    }
}
