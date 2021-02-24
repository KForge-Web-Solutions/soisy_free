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

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once(_PS_MODULE_DIR_ . 'soisy_free/vendor/autoload.php');
include_once(_PS_MODULE_DIR_ . 'soisy_free/classes/Client.php');//1.6 mess up...
//use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
//use KForge\Soisy\Client;
//cannot use there because pshop 1.6 use eval()...

class Soisy_free extends PaymentModule
{
    const LEGACY_VERSION = '1.6';
    const ORDER_STATE_WAITING = 'LoanRedirect';
    const SOISY_LOAN_SIMULATION_CDN = 'https://cdn.soisy.it/loan-quote-widget.js';
    protected $config_form = false;

    public $successUrl;
    public $errorUrl;
    public $orderStates;

    public function __construct()
    {
        $this->name = 'soisy_free';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'KForge';
        $this->bootstrap = true;

        parent::__construct();

        $this->logger = $this->getLogger();

        $this->displayName = $this->l('Soisy Free');
        $this->description = $this->l('Payment by installments via Soisy');

        $this->confirmUninstall = $this->l('Are you sure you want to unistall the module?');

        $this->limited_countries = array('IT');

        $this->limited_currencies = array('EUR');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        if ($this->isVersion(self::LEGACY_VERSION)) {
            if ((bool)Configuration::get('PS_JS_DEFER') != true) {
                $this->warning = $this->l('For best use, set to ON the "move javascript to the end" option on the performance setting page.');
            }
        }

        $this->orderStates = [
            self::ORDER_STATE_WAITING => [
                'key' => 'SOISY_FREE_ORDER_STATE_WAITING',
                'name' => 'In attesa di finanziamento Soisy',
                'color' => '#32CD32',
                'invoice' => false,
                'paid' => false,
            ],
        ];

        $this->client = new Client(
            Configuration::get('SOISY_FREE_SHOP_ID', ''),
            Configuration::get('SOISY_FREE_API_KEY', ''),
            !Configuration::get('SOISY_FREE_LIVE_MODE'),
            $this
        );
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false) {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }
        include(dirname(__FILE__).'/sql/install.php');
        foreach ($sql as $query) {
            if (Db::getInstance()->execute($query) == false) {
                $this->_errors[] = Db::getInstance()->getMsgError().' db v.'.Db::getInstance()->getVersion();
                return false;
            }
        }
        if (!Configuration::updateValue('SOISY_FREE_LIVE_MODE', false)
            || !Configuration::updateValue('SOISY_FREE_LOG_ENABLED', true)
            || !Configuration::updateValue('SOISY_FREE_SHOP_ID', 'soisytests')
            || !Configuration::updateValue('SOISY_FREE_API_KEY', 'partnerkey')
            || !Configuration::updateValue('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT', 6)
            || !Configuration::updateValue('SOISY_FREE_MIN_AMOUNT', 250)
            || !Configuration::updateValue('SOISY_FREE_MAX_AMOUNT', 30000)) {
            return false;
        } else {
            foreach ($this->orderStates as $state) {
                if (empty(Configuration::get($state['key'])) && !Configuration::updateValue($state['key'], '')) {
                    return false;
                }
            }
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayFooter') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('displayProductPriceBlock') &&
            $this->addOrderStates();
    }

    public function uninstall()
    {
        Configuration::deleteByName('SOISY_FREE_LIVE_MODE');
        Configuration::deleteByName('SOISY_FREE_LOG_ENABLED');
        Configuration::deleteByName('SOISY_FREE_SHOP_ID');
        Configuration::deleteByName('SOISY_FREE_API_KEY');
        Configuration::deleteByName('SOISY_FREE_MIN_AMOUNT');
        Configuration::deleteByName('SOISY_FREE_MAX_AMOUNT');
        Configuration::deleteByName('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $output = '';
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitSoisy_freeModule')) == true) {
            $errors = $this->validateForm();

            if (empty($errors)) {
                $this->postProcess();
            } else {
                $output .= $this->displayError($this->l($errors[0]));
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    protected function validateForm()
    {
        $errors = [];
        $errors = $this->tryLoanSimulation(
            Configuration::get('SOISY_FREE_MIN_AMOUNT'),
            Configuration::get('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT'),
            $errors
        );

        return $errors;
    }

    public function tryLoanSimulation($amount, $instalments, $errors)
    {
        $loanRequest = [
            'amount' => 100 * $amount,
            'instalments' => $instalments,
            'zeroInterstRate' => false,
        ];

        try {
            (new Client(
                Tools::getValue('SOISY_FREE_LIVE_MODE') ? Tools::getValue('SOISY_FREE_SHOP_ID') : '',
                Tools::getValue('SOISY_FREE_LIVE_MODE') ? Tools::getValue('SOISY_FREE_API_KEY') : '',
                !Tools::getValue('SOISY_FREE_LIVE_MODE'),
                $this
            ))->getLoanSimulation($loanRequest);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitSoisy_freeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' =>  $this->getConfigFormValues(),/* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm([
            $this->getConfigForm(),
        ]);
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Impostazioni'),
                    'icon' => 'icon-cogs',
                    'class' => 'soisy_free',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'SOISY_FREE_LIVE_MODE',
                        'required' => true,
                        'validate' => 'isBool',
                        'is_bool' => true,
                        'desc' => $this->l('Utilizza il modulo in versione live (attiva) o test (non attiva)'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Si'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Abilita Log'),
                        'name' => 'SOISY_FREE_LOG_ENABLED',
                        'required' => true,
                        'validate' => 'isBool',
                        'is_bool' => true,
                        'desc' => $this->l('Abilita salvataggio Log'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Si'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'soisy_free-input',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'label' => $this->l('Shop ID'),
                        'name' => 'SOISY_FREE_SHOP_ID',
                        'required' => true,
                        'validate' => 'isRequired',
                        'is_bool' => false,
                        'desc' => $this->l('API shop ID'),
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'soisy_free-input',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'label' => $this->l('API key'),
                        'name' => 'SOISY_FREE_API_KEY',
                        'required' => true,
                        'validate' => 'isRequired',
                        'is_bool' => false,
                        'desc' => $this->l('API Shop Key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'SOISY_FREE_LIVE_MODE' => Configuration::get('SOISY_FREE_LIVE_MODE'),
            'SOISY_FREE_LOG_ENABLED' => Configuration::get('SOISY_FREE_LOG_ENABLED'),
            'SOISY_FREE_SHOP_ID' => Configuration::get('SOISY_FREE_SHOP_ID'),
            'SOISY_FREE_API_KEY' => Configuration::get('SOISY_FREE_API_KEY'),
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        $validation = true;

        if ($validation) {
            foreach (array_keys($form_values) as $key) {
                $key = str_replace(['[', ']'], ['', ''], $key);
                $value = Tools::getValue($key);
                Configuration::updateValue($key, $value);
            }
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        if ($this->isVersion(self::LEGACY_VERSION)) {
            $this->context->controller->addJS($this->_path.'views/js/front.js');
            $this->context->controller->addCSS($this->_path.'views/css/front.css');
        } else {
            $this->context->controller->registerJavascript(
                'soisy_free_front',
                $this->_path.'views/js/front.js'
            );
            $this->context->controller->registerStylesheet(
                'soisy_free_css',
                $this->_path.'views/css/front.css'
            );
            $this->context->controller->registerJavascript(
                'soisy_cdn',
                self::SOISY_LOAN_SIMULATION_CDN,
                ['server' => 'remote', 'attributes' => 'defer']
            );
        }
    }
    public function hookDisplayFooter()
    {
        if ($this->isVersion(self::LEGACY_VERSION)) {
            if ((isset($this->context->controller->php_self) and $this->context->controller->php_self == 'product')
                || (isset($this->context->controller->php_self) and $this->context->controller->php_self == 'order')) {
                $this->smarty->assign(['cdn_uri' => self::SOISY_LOAN_SIMULATION_CDN]);
                    return $this->display(__FILE__, 'cdn_quote.tpl');
            }
        }
    }
    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isModuleUsable($params, $params['cart']->getOrderTotal())) {
            return;
        }
        $this->smarty->assign([
                'amount' => round($params['cart']->getOrderTotal(), 2),
                'instalments' => Configuration::get('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT'),
                'shop_id' => Configuration::get('SOISY_FREE_SHOP_ID')
            ]
        );
        //$option = new PaymentOption();
        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pay via Soisy'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/logo-soisy-min.png'))
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true))
            /*->setInputs([
                'token' => ['name' =>'token', 'type' =>'hidden', 'value' =>'12345689',],
            ])*/
            ->setAdditionalInformation($this->fetch('module:'.$this->name.'/views/templates/front/payment_info.tpl'));

        return [
            $option
        ];
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if ((isset($this->context->controller->php_self) and $this->context->controller->php_self == 'product')
            || (isset($this->context->controller->php_self) and $this->context->controller->php_self == 'order')) {

            if ($params['type'] != 'after_price') {
                return '';
            }

            $productId = $params['product']->id ?? $params['product']['id'];
            $amount = !empty($productId) ? Product::getPriceStatic($productId) : 0;

            if (!$this->isModuleUsable($params, $amount)) {
                return '';
            }

            $this->smarty->assign([
                    'amount' => round($amount, 2),
                    'instalments' => Configuration::get('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT'),
                    'shop_id' => Configuration::get('SOISY_FREE_SHOP_ID')
                ]
            );                
            if ($this->isVersion(self::LEGACY_VERSION)) {
                $templateFile = 'loan_simulation.tpl';
            } else {
                $templateFile = 'module:'.$this->name.'/views/templates/hook/loan_simulation.tpl';
            }
            if ($this->isVersion(self::LEGACY_VERSION)) {
                return $this->display(__FILE__, $templateFile);
            } else {
                return $this->fetch($templateFile);
            }
        }
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function addOrderStates()
    {
        foreach ($this->orderStates as $orderStateOptions) {
            if (empty(Configuration::get($orderStateOptions['key']))) {
                $order_state = new OrderState();
                $order_state->module_name = $this->name;
                $order_state->name = [
                    $this->context->language->id => $orderStateOptions['name'],
                ];

                $order_state->send_email = false;
                $order_state->color = $orderStateOptions['color'];
                $order_state->hidden = false;
                $order_state->delivery = false;
                $order_state->logable = true;
                $order_state->invoice = $orderStateOptions['invoice'];
                $order_state->paid = $orderStateOptions['paid'];

                if ($order_state->add()) {
                    Configuration::updateValue($orderStateOptions['key'], (int) $order_state->id);

                    if (!$this->isVersion(self::LEGACY_VERSION)) {
                        $source = $this->local_path.'/logo.gif';
                        $destination = dirname($this->local_path, 2).'/img/os/'.(int) $order_state->id.'.gif';
                        copy($source, $destination);
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public function checkAmount($amount)
    {
        return $amount >= Configuration::get('SOISY_FREE_MIN_AMOUNT') && $amount < Configuration::get('SOISY_FREE_MAX_AMOUNT');
    }

    public function hookDisplayPayment($params)
    {
        if (!$this->isModuleUsable($params, $params['cart']->getOrderTotal())) {
            return;
        }

        /*$this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));*/
        $this->smarty->assign([
                'amount' => $params['cart']->getOrderTotal(),
                'instalments' => Configuration::get('SOISY_FREE_QUOTE_INSTALMENTS_AMOUNT'),
                'shop_id' => Configuration::get('SOISY_FREE_SHOP_ID')
            ]
        );
        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookDisplayPaymentReturn($params)
    {
        $is_guest = false;
        /* check if the cart has been made by a Guest customer, for redirect link */
        if (Cart::isGuestCartByCartId($params['objOrder']->id_cart)) {
            $is_guest = true;
            $redirectLink = 'index.php?controller=guest-tracking';
        } else {
            $redirectLink = 'index.php?controller=history';
        }
        if ($is_guest) {
            $customer = new Customer($params['objOrder']->id_customer);
            $redirectLink .= '&id_order='.$params['objOrder']->reference.'&email='.urlencode($customer->email);
        }
        Tools::redirect($redirectLink);
    }

    public function isModuleUsable($params, $amount)
    {
        $check = false;

        if (
            $this->active
            && $this->checkCurrency($params['cart'])
            && ($this->checkAmount($amount))
        ) {
            $check = true;
        }

        return $check;
    }

    public function getAmountInCents($amount)
    {
        return $amount * 100;
    }

    public function getAmountInEuro($amount)
    {
        return $amount / 100;
    }

    //Get transaction data
    public function getTransaction($orderReference, $orderToken = null)
    {
        $db = Db::getInstance();

        $where = "`order_reference` = '" . pSQL($orderReference) ."'";
        if (!is_null($orderToken)) {
            $where .= " AND `token` = '" . pSQL($orderToken) ."'";
        }
        $query = "SELECT * FROM " . _DB_PREFIX_ . $this->name . " WHERE " . $where;

        $transaction = $db->getRow($query, false);

        return $transaction;
    }

    public function getOrderStateId($eventId)
    {
        return Configuration::get($this->orderStates[$eventId]['key']);
    }

    public function isVersion($version)
    {
        return substr(_PS_VERSION_, 0, 3) === $version;
    }

    public function displayControllerError($controller, $message)
    {
        /*
         * Set error message and description for the template.
         */
        $this->context->smarty->assign(array(
            'soisy_free_errors' => [$this->l($message)],
        ));

        if ($this->isVersion(self::LEGACY_VERSION)) {
            $template = 'error_legacy.tpl';
        } else {
            $template = 'module:'.$this->name.'/views/templates/front/error.tpl';
        }

        return $controller->setTemplate($template);
    }

    protected function getLogger()
    {
        $logger = new FileLogger(0);
        $logger->setFilename(_PS_MODULE_DIR_ . 'soisy_free/log/' . date('Ymd') . '_'.$this->name.'.log');

        return $logger;
    }

    /**
     * Log error on disk.
     */
    public function logCall($message, $level = 0)
    {
        if (Configuration::get('SOISY_FREE_LOG_ENABLED')) {
            $this->logger->log($message, $level);
        }
    }
    protected function translate16workaround()
    {
        $this->l('An error occured or you left the payment process. Please contact the merchant to have more informations');
        $this->l('Order not found');
        $this->l('An error occurred during customer redirect.');
        $this->l('An error occurred during order creation');
    }
    
}
