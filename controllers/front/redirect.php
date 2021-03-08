<?php
/**
 * 2007-2021 KForge
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
class Soisy_freeRedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Do whatever you have to before redirecting the customer on the website of your payment processor.
     */
    public function postProcess()
    {
        /*
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            die;
        }

        if (!$this->context->customer->isLogged(true)) {
            Tools::redirect('index.php?controller=order');
        }

        /*
         * Oops, an error occured.
         */
        if (Tools::getValue('action') == 'error') {
            /*$this->errors[] = $this->module->l('An error occurred during customer redirect.');
            return $this->redirectWithNotifications('index.php?controller=order');*/
            //ps 1.6 compat
            $errorMessage = $this->module->l('An error occurred during customer redirect.');
            return $this->module->displayControllerError($this, $errorMessage);
        } else {
            $cart = $this->context->cart;
            $this->module->logCall('Creating order');
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('SOISY_FREE_ORDER_STATE_WAITING'),
                $cart->getOrderTotal(),
                $this->module->displayName,
                null,
                null,
                (int) Context::getContext()->currency->id,
                false,
                $this->context->customer->secure_key
            );
            $order = new Order($this->module->currentOrder);
            if (!Validate::isLoadedObject($order)) {
                $this->module->logCall('Order creation failed');
                /*$this->errors[] = $this->module->l('An error occurred during order creation');
                return $this->redirectWithNotifications('index.php?controller=order');*/
                //ps 1.6 compat
                $errorMessage = $this->module->l('An error occurred during order creation');
                return $this->module->displayControllerError($this, $errorMessage);
            }
            $orderReference = $order->reference;
            $this->module->logCall('Order created, reference: '.$orderReference);
            $amount = $this->module->getAmountInCents($cart->getOrderTotal());

            $productIds = [];
            foreach ($cart->getProducts() as $product) {
                $productIds[] = $product['id_product'];
            }

            $params = [
                'amount' => $amount,
                'successUrl' => $this->context->link->getModuleLink($this->module->name, 'validation', 
                    ['ref' => $orderReference, 'result' => md5($orderReference.$this->context->customer->id.'ok')], true),
                'errorUrl' => $this->context->link->getModuleLink($this->module->name, 'validation', 
                    ['ref' => $orderReference, 'result' => md5($orderReference.$this->context->customer->id.'ko')], true),
                'orderReference' => $orderReference,
                'zeroInterestRate' => 0,
            ];

            try {
                // Get token
                $token = $this->module->client->requestToken($params);

                if (is_null($token)) {
                    throw new \Error('Token unavailable. Request failed.');
                }

                $this->saveTracking([
                    'id_shop' => $this->context->shop->id,
                    'id_cart' => $cart->id,
                    'id_customer' => $this->context->customer->id,
                    'order_reference' => $orderReference,
                    'token' => $token,
                    'total_cart' => $cart->getOrderTotal(),
                    'sandbox' => (int)!Configuration::get('SOISY_FREE_LIVE_MODE')
                ]);

                // Redirect
                Tools::redirect($this->module->client->getRedirectUrl($token));
            } catch (\DomainException $e) {
                $errorMessage = $e->getMessage();
            } catch (\Error $e) {
                $errorMessage = $e->getMessage();
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
            /*$this->errors[] = $errorMessage;
            return $this->redirectWithNotifications('index.php?controller=order');*/
            //ps 1.6 compat
            return $this->module->displayControllerError($this, $errorMessage);
        }
    }

    protected function saveTracking($data)
    {
        $record = [];
        $db = Db::getInstance();

        foreach ($data as $column => $value) {
            $record[$column] = pSQL($value);
        }
        $record['created_at'] = pSQL(date('Y-m-d H:i:s'));

        $db->insert($this->module->name, $record);
    }
}
