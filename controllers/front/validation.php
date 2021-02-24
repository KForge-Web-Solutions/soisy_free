<?php
/**
 * 2007-2020 KForge
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@kforge.it so we can send you a copy immediately.
 *
 * @author    KForge snc <info@kforge.it>
 * @copyright 2007-2021 KForge snc
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
class Soisy_freeValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        /*
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            die;
        }

        $orderReference = Tools::getValue('ref');
        $result = Tools::getValue('result');
        $transaction = $this->module->getTransaction($orderReference);
        if (empty($transaction['id_cart'])) {
            $errorMessage = $this->module->l('Order not found');

            return $this->module->displayControllerError($this, $errorMessage);
        }
        if ($result == md5($transaction['order_reference']. $transaction['id_customer'].'ok')) {
            $order_id = Order::getOrderByCartId((int) $transaction['id_cart']);
            $customer = $this->context->customer;

            if ($order_id) {
                /**
                 * The order has been placed so we redirect the customer on the confirmation page.
                 */
                $module_id = $this->module->id;
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $transaction['id_cart'] . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $customer->secure_key);
            } else {
                //è praticamente impossibile arrivare qui...
                $errorMessage = $this->module->l('Order not found');
                return $this->module->displayControllerError($this, $errorMessage);
            }
        } else {
            $errorMessage = $this->module->l('An error occured or you left the payment process. Please contact the merchant to have more informations');
            //annullo l'ordine
            $order_id = Order::getOrderByCartId((int) $transaction['id_cart']);
            $order = new Order($order_id);
            if (Validate::isLoadedObject($order)) {
                $current_order_state = $order->getCurrentOrderState();
                if ($current_order_state->id != Configuration::get('PS_OS_CANCELED') ) {
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int)$order_id;
                    $new_history->changeIdOrderState(Configuration::get('PS_OS_CANCELED'), $order, true);
                    //$new_history->addWithemail(true);
                    $new_history->add(true);
                }
            }
            return $this->module->displayControllerError($this, $errorMessage);
        }
    }
}
