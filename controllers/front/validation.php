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
        // Log requests from Privat API side in Debug mode.
        if (Configuration::get('PRIVAT24_DEBUG_MODE')) {
            $logger = new FileLogger();
            $logger->setFilename(_PS_ROOT_DIR_.'/log/'.$this->module->name.'_'.date('Ymd_His').'_response.log');
            $logger->logError($_POST);
        }
        
        $payment = array();
        parse_str(Tools::getValue('payment'), $payment);
        $hash = sha1(md5(Tools::getValue('payment') . $this->module->merchant_password));

        if ($payment && $hash === Tools::getValue('signature')) {
            if ($payment['state'] == 'ok') {
                $state = Configuration::get('PRIVAT24_WAITINGPAYMENT_OS');
                $cart_id = (int)$payment['order'];
                $order = new Order(Order::getOrderByCartId($cart_id));
                if (!Validate::isLoadedObject($order)) {
                    PrestaShopLogger::addLog('Privat24: cannot get order by cart id ' . $cart_id, 3);
                    die();
                }
                if ($order->getCurrentState() != $state) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'Privat24: order id %s current state %s !== expected state %s',
                            $order->id,
                            $order->getCurrentState(),
                            $state
                        ),
                        3
                    );
                    die();
                }
                
                // Check paid currency and paid amount.
                $id_currency = Currency::getIdByIsoCode($payment['ccy']);
                if (!$id_currency) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'Privat24: order id %s cannot get currency id by iso code: %s',
                            $order->id,
                            $payment['ccy']
                        ),
                        3
                    );
                    die();
                }
                if ($order->id_currency != $id_currency) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'Privat 24: order id %s, order currency id %s does not match with %s',
                            $order->id,
                            $order->id_currency,
                            $id_currency
                        ),
                        3
                    );
                    die();
                }
                if ((float)$order->total_paid != (float)$payment['amt']) {
                    PrestaShopLogger::addLog(
                        sprintf(
                            'Privat 24: order id %s order total paid %s does not match %s',
                            $order->id,
                            $order->total_paid,
                            $payment['amt']
                        ),
                        3
                    );
                    die();
                }
                
                $order_history = new OrderHistory();
                $order_history->id_order = $order->id;
                $order_history->changeIdOrderState(_PS_OS_PAYMENT_, $order->id);
                $order_history->addWithemail();
                $this->setPaymentTransaction($order, $payment);
                $this->module->paymentNotify($order, $payment);
                PrestaShopLogger::addLog(
                    sprintf(
                        'Privat24 payment accepted: order id: %s, amount: %s, ref: %s',
                        $order->id,
                        $payment['amt'],
                        $payment['ref']
                    ),
                    1
                );
            } else {
                PrestaShopLogger::addLog(
                    sprintf(
                        'Privat24 payment failed: state: %s, order: %s, ref: %s',
                        $payment['state'],
                        $payment['order'],
                        $payment['ref']
                    ),
                    3,
                    null,
                    null,
                    null,
                    true
                );
            }
        } else {
            PrestaShopLogger::addLog('Privat24: Payment callback bad signature.', 3, null, null, null, true);
        }
        
        die();
    }
    
    /**
     * Fill transaction_id in order payments.
     *
     * @param Order $order
     * @param array $payment payment data from gateway.
     */
    protected function setPaymentTransaction($order, array $payment)
    {
        Db::getInstance()->execute('
            UPDATE `'._DB_PREFIX_.'order_payment`
            SET transaction_id = "' . pSQL($payment['ref']) . '"
            WHERE order_reference = "' . pSQL($order->reference) . '"
                AND amount = ' . pSQL($payment['amt']) . '
                AND payment_method = "' . pSQL($this->module->displayName) . '"
                AND transaction_id = ""
        ');
    }
}
