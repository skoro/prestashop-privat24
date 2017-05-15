<?php
/**
* 2016 Soft Industry
*
*   @author    Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
*   @copyright 2016 Soft-Industry
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
        $this->version = '0.2.1';
        $this->author = 'Soft Industry';
        $this->tab = 'payments_gateways';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99.99');
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '98300a9bd7f3e615cc475c9e56cfef25';
        
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
     *
     * @param string $data
     * @return array
     */
    protected function validateEmails($data)
    {
        $lines = explode("\n", $data);
        $lines = array_filter($lines, 'trim');
        $data = implode(',', $lines);
        $data = explode(',', trim($data));
        $data = array_filter($data);
        $emails = array();
        $errors = array();

        foreach ($data as $email) {
            $email = trim($email);
            if (Validate::isEmail($email)) {
                $emails[] = $email;
            } else {
                $errors[] = $email;
            }
        }

        return array(
            'emails' => $emails,
            'errors' => $errors,
        );
    }
    
    /**
     * Validate settings form POST data.
     *
     * @return array
     * @since 0.2.1
     */
    protected function postValidate()
    {
        $errors = array();
        
        if (Tools::isSubmit('submit' . $this->name)) {
            $merchant_id = Tools::getValue('PRIVAT24_MERCHANT_ID');
            if (!$merchant_id) {
                $errors[] = $this->l('Merchant ID is required.');
            }
            if (!Validate::isUnsignedInt($merchant_id)) {
                $errors[] = $this->l('Merchant ID must be a number.');
            }

            if (!Tools::getValue('PRIVAT24_MERCHANT_PASSWORD')) {
                $errors[] = $this->l('Merchant password is required.');
            }

            $emails = $this->validateEmails(Tools::getValue('PRIVAT24_NOTIFY_EMAILS'));
            if ($emails['errors']) {
                $errors[] = $this->l('Please check these emails are correct: ') .
                    implode(', ', $emails['errors']);
            }
            
            $notify = (bool) Tools::getValue('PRIVAT24_PAYMENT_NOTIFY');
            if ($notify && empty($emails['emails']) && empty($emails['errors'])) {
                $errors[] = $this->l('Email(s) needed when notify is enabled.');
            }
        }
        
        return $errors;
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
            $errors = $this->postValidate();

            if (empty($errors)) {
                $merchant_id = Tools::getValue('PRIVAT24_MERCHANT_ID');
                $merchant_password = Tools::getValue('PRIVAT24_MERCHANT_PASSWORD');
                $notify = (bool) Tools::getValue('PRIVAT24_PAYMENT_NOTIFY');
                $emails = Tools::getValue('PRIVAT24_NOTIFY_EMAILS');

                $backend_uri = str_replace('index.php', '', $_SERVER['PHP_SELF']);

                Configuration::updateValue('PRIVAT24_BACKEND_URI', $backend_uri);
                Configuration::updateValue('PRIVAT24_MERCHANT_ID', $merchant_id);
                Configuration::updateValue('PRIVAT24_MERCHANT_PASSWORD', $merchant_password);
                Configuration::updateValue('PRIVAT24_DEBUG_MODE', (bool)Tools::getValue('PRIVAT24_DEBUG_MODE'));
                Configuration::updateValue('PRIVAT24_PAYMENT_NOTIFY', $notify);
                Configuration::updateValue('PRIVAT24_NOTIFY_EMAILS', $emails);

                $this->merchant_id = $merchant_id;
                $this->merchant_password = $merchant_password;
                
                $status .= $this->displayConfirmation($this->l('Settings have been updated.'));
            } else {
                foreach ($errors as $error) {
                    $status .= $this->displayError($error);
                }
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
                    'desc' => $this->l('Log all Privat24 API responses in ')
                                . _PS_ROOT_DIR_.'/log/'
                                . $this->l(' directory.'),
                    'is_bool' => true,
                    'default_value' => 0,
                    'values' => array(
                        array('value' => 1),
                        array('value' => 0),
                    ),
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Email notify when payment accepted'),
                    'name' => 'PRIVAT24_PAYMENT_NOTIFY',
                    'is_bool' => true,
                    'default_value' => 0,
                    'values' => array(
                        array('value' => 1),
                        array('value' => 0),
                    ),
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Emails'),
                    'name' => 'PRIVAT24_NOTIFY_EMAILS',
                    'desc' => $this->l('Emails can be separated by comma or/and newlines.'),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
        );
        
        $helper->submit_action = 'submit' . $this->name;
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );
        
        return $helper->generateForm($fields_form);
    }
    
    /**
     * Get form fields configuration values.
     *
     * @return array
     * @since 0.2.1
     */
    public function getConfigFieldsValues()
    {
        return array(
            'PRIVAT24_MERCHANT_ID' => Tools::getValue('PRIVAT24_MERCHANT_ID', Configuration::get('PRIVAT24_MERCHANT_ID')),
            'PRIVAT24_MERCHANT_PASSWORD' => Tools::getValue('PRIVAT24_MERCHANT_PASSWORD', Configuration::get('PRIVAT24_MERCHANT_PASSWORD')),
            'PRIVAT24_DEBUG_MODE' => Tools::getValue('PRIVAT24_DEBUG_MODE', Configuration::get('PRIVAT24_DEBUG_MODE')),
            'PRIVAT24_PAYMENT_NOTIFY' => Tools::getValue('PRIVAT24_PAYMENT_NOTIFY', Configuration::get('PRIVAT24_PAYMENT_NOTIFY')),
            'PRIVAT24_NOTIFY_EMAILS' => Tools::getValue('PRIVAT24_NOTIFY_EMAILS', Configuration::get('PRIVAT24_NOTIFY_EMAILS')),
        );
    }
    
    /**
     * Implements hookPayment.
     */
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        
        if (isset($this->context->controller)) {
            $this->context->controller->addCSS($this->_path . 'views/css/privat24.css');
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
        if (!empty($emails['emails'])) {
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
            foreach ($emails['emails'] as $email) {
                Mail::Send((int)$order->id_lang, $template, $subject, $data, $email);
            }
        }
        return true;
    }
}
