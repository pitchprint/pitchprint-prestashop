<?php
/**
* 2023 PitchPrint
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PitchPrint to newer
* versions in the future. If you wish to customize PitchPrint for your
* needs please refer to http://pitchprint.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2023 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PitchPrint Inc.
*/
if (!defined('_PS_VERSION_')) {
    exit;
}

define('PP_IOBASE', 'https://pitchprint.com');

define('PP_CLIENT_JS', 'https://pitchprint.io/rsc/js/client.js');
define('PP_CAT_CLIENT_JS', 'https://pitchprint.io/rsc/js/cat-client.js');
define('PP_NOES6_JS', 'https://pitchprint.io/rsc/js/noes6.js');

define('PP_ADMIN_JS', 'https://pitchprint.io/rsc/js/a.ps.js');

define('PPADMIN_DEF', "var PPADMIN = window.PPADMIN; if (typeof PPADMIN === 'undefined') window.PPADMIN = PPADMIN = { version: '10.0.0', readyFncs: [] };");
define('PP_VERSION', '10.0.0');

define('PITCHPRINT_API_KEY', 'pitchprint_API_KEY');
define('PITCHPRINT_SECRET_KEY', 'pitchprint_SECRET_KEY');

define('PITCHPRINT_P_DESIGNS', 'pitchprint_p_designs');

define('PITCHPRINT_ID_CUSTOMIZATION_NAME', 'PitchPrint');
define('PITCHPRINT_TABLE_NAME', 'pitch_pa_customization_values');

define('MAGNIFIC_JS', '//dta8vnpq1ae34.cloudfront.net/javascripts/jquery.magnific-popup.min.js');
define('MAGNIFIC_CSS', '//dta8vnpq1ae34.cloudfront.net/stylesheets/magnific-popup.css');

class PitchPrint extends Module
{
    public function __construct()
    {
        $this->name = 'pitchprint';
        $this->module_key = 'bef92b980b5301cad2ccce8d8b87b6da';
        $this->tab = 'front_office_features';
        $this->version = '10.0.2';
        $this->author = 'PitchPrint Inc.';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('PitchPrint');
        $this->description = $this->l('A beautiful web based print customization app for your online store. Integrates with Prestashop 1.7+');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->clearCustomization();
        $this->createCustomization();
        $this->serveDesignIds();
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install()) {
            return false;
        }

        $_pKey = Configuration::get(PITCHPRINT_API_KEY);
        $_pSec = Configuration::get(PITCHPRINT_SECRET_KEY);
        $_pDes = Configuration::get(PITCHPRINT_P_DESIGNS);

        if (empty($_pKey)) {
            Configuration::updateValue(PITCHPRINT_API_KEY, '');
        }
        if (empty($_pSec)) {
            Configuration::updateValue(PITCHPRINT_SECRET_KEY, '');
        }
        if (empty($_pDes)) {
            Configuration::updateValue(PITCHPRINT_P_DESIGNS, json_encode([]));
        }

        // Create table
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . PITCHPRINT_TABLE_NAME . '`
            (
                cId INT NOT NULL PRIMARY KEY,
                value TEXT NOT NULL
            )';

        $db = Db::getInstance()->execute($sql);

        return $this->registerHook('displayHeader') &&
        $this->registerHook('displayAdminOrder') &&
        $this->registerHook('displayBackOfficeHeader') &&
        $this->registerHook('actionProductUpdate') &&
        $this->registerHook('actionOrderStatusPostUpdate') &&
        $this->registerHook('displayAdminProductsExtra') &&
        $this->registerHook('displayCustomization') &&
        $this->registerHook('displayCustomerAccount') &&
        $this->registerHook('displayAdminOrderSide') &&
        $this->registerHook('actionCartUpdateQuantityBefore');
    }

    public function hookDisplayAdminOrderSide($params)
    {
        $order = new Order((int) $params['id_order']);
        $products = $order->getCartProducts();

        foreach ($products as &$row) {
            if ($row['id_customization']) {
                $request = 'SELECT `value` FROM `' . _DB_PREFIX_ . PITCHPRINT_TABLE_NAME . "` WHERE `cId` = {$row['id_customization']}";
                $raw = Db::getInstance()->getValue($request);
                $row['pitchprint_customization'] = json_decode(urldecode($raw));
            }
        }

        return $this->get('twig')->render('@Modules/pitchprint/views/templates/admin/displayCustomization.twig', [
            'products' => $products,
        ]);
    }

    private function serveDesignIds()
    {
        if (Tools::getValue('pp_serve_designs') != 1) {
            return;
        }

        $productIds = json_decode(Tools::getValue('ids'));
        $pp_design_options = json_decode(Configuration::get(PITCHPRINT_P_DESIGNS), true);

        $ids = [];
        foreach ($productIds as $pId) {
            $ids[$pId] = isset($pp_design_options[$pId]) ? $pp_design_options[$pId] : '';
        }

        exit(json_encode(['designs' => $ids, 'apiKey' => Configuration::get(PITCHPRINT_API_KEY)]));
    }

    public function uninstall()
    {
        if (parent::uninstall()) {
            return true;
        }

        return false;
    }

    public function createCustomization()
    {
        $productId = (int) Tools::getValue('id_product');
        $pp_values = (string) Tools::getValue('values');
        if (!empty($pp_values) and ($this->context->controller->php_self === 'product'
          || $this->context->controller->php_self === 'category')) {
            $indexval = Db::getInstance()->getValue('SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . "customization_field` WHERE `id_product` = {$productId} AND `type` = 1  AND `is_module` = 1");

            if (empty($indexval)) {
                $indexval = $this->createCustomizationField((int) $productId);
            }

            if (!$this->context->cart->id && isset($_COOKIE[$this->context->cookie->getName()])) {
                $this->context->cart->add();
                $this->context->cookie->id_cart = (int) $this->context->cart->id;
            }

            $cCid = $this->context->cart->getProductCustomization($productId, null, true);
            if (empty($cCid)) {
                Db::getInstance()->insert('customization', [
                    'id_cart' => $this->context->cart->id,
                    'id_product' => $productId,
                    // 'id_product_attribute' => $id_product_attribute,
                    'quantity' => 0,
                    'in_cart' => 0,
                ]);

                $cCid = [[]];
                $cCid[0]['id_customization'] = Db::getInstance()->Insert_ID();
            }

            // Add shop id
            $open_values = json_decode(urldecode($pp_values));
            $open_values->shop_id = (int) Context::getContext()->shop->id;
            $pp_values = urlencode(json_encode($open_values));

            // Store projectId in core table
            $db = Db::getInstance();
            $db->insert('customized_data', [
                'id_customization' => $cCid[0]['id_customization'],
                'type' => 1,
                'index' => $indexval,
                'value' => Db::getInstance()->escape($open_values->projectId),
                'id_module' => $this->id,
            ]);
            // Then store full detail in our table
            $db->insert(PITCHPRINT_TABLE_NAME, [
                'cId' => $cCid[0]['id_customization'],
                'value' => Db::getInstance()->escape($pp_values),
            ]);

            // Store pp_project in session cookie
            if (isset(Context::getContext()->cookie->pp_projects)) {
                $oldCookie = json_decode(Context::getContext()->cookie->pp_projects, true);
                $oldCookie[$productId] = $pp_values;
                Context::getContext()->cookie->pp_projects = json_encode($oldCookie);
            } else {
                Context::getContext()->cookie->pp_projects = json_encode([$productId => $pp_values]);
            }

            $is_ajax = Tools::getValue('ajax');
            if ($is_ajax == true) {
                exit(json_encode(['product_customization_id' => $cCid[0]['id_customization']]));
            }
        }
    }

    public function clearCustomization()
    {
        if ((Tools::getValue('clear') == true) and $this->context->controller->php_self === 'product') {
            $productId = (int) Tools::getValue('id_product');
            $indexval = Db::getInstance()->getValue('SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . "customization_field` WHERE `id_product` = {$productId} AND `type` = 1  AND `is_module` = 1");
            $this->context->cart->deleteCustomizationToProduct($productId, (int) $indexval);

            // Clear product from session cookie
            if (isset(Context::getContext()->cookie->pp_projects)) {
                $current = json_decode(Context::getContext()->cookie->pp_projects, true);
                unset($current[$productId]);
                Context::getContext()->cookie->pp_projects = json_encode($current);
            }
            exit(json_encode(['cleared' => true]));
        }
    }

    public function hookDisplayCustomerAccount($params)
    {
        $smarty = new Smarty();
        $html = $smarty->fetch(__DIR__ . '/views/templates/front/displayCustomerAccount.tpl');

        return $html;
    }

    public function hookDisplayCustomization($params)
    {
        $current_context = Context::getContext();
        if ($current_context->controller->controller_type == 'front') {
            $this->smarty->assign('pp_customization_project_id', $params['customization']['value']);

            return $this->fetch('module:pitchprint/views/templates/front/displayCustomization.tpl');
        }

        return "Project ID: {$params['customization']['value']}";
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $statusId = (int) $params['newOrderStatus']->id;
        $doHook = ($statusId === 3 || $statusId === 4);
        if (!$doHook) {
            return;
        } // At this stage we only provide webhook for order processing or completed

        $order = new Order((int) $params['id_order']);

        $id_cart = $order->id_cart;

        $status = '';

        switch ($statusId) {
            case 3:
                $status = 'processing';
                break;
            case 4:
                $status = 'complete';
                break;
        }

        $products = $order->getCartProducts();
        $customer = $order->getCustomer();
        $items = [];

        $address = new Address($order->id_address_delivery);

        foreach ($products as $prod) {
            $pitchprint = '';

            if ($prod['customizedDatas'] != null) {
                $data_in = Db::getInstance()->executeS('SELECT `value` FROM `' . _DB_PREFIX_ . 'customized_data` WHERE
					`id_customization` =' . $prod['id_customization']);

                foreach ($data_in as $data_item) {
                    $array_data = (array) json_decode(rawurldecode($data_item['value']));

                    if (is_array($array_data)
                        && count(array_keys($array_data))
                            && in_array('type', array_keys($array_data)) && $array_data['type'] == 'p') {
                        $pitchprint = $array_data;
                    }
                }
            }

            $items[] = [
                'name' => $prod['product_name'],
                'id' => $prod['product_id'],
                'qty' => $prod['cart_quantity'],
                'pitchprint' => json_encode($pitchprint),
            ];
        }

        $pp_empty = true;
        foreach ($items as $item) {
            $ppItemDecoded = json_decode($item['pitchprint']);
            if (!empty($ppItemDecoded)) {
                $pp_empty = false;
            }
        }
        if ($pp_empty) {
            return;
        }

        $items = json_encode($items);

        $timestamp = time();
        $pitchprint_api_value = Configuration::get(PITCHPRINT_API_KEY);
        $pitchprint_secret_value = Configuration::get(PITCHPRINT_SECRET_KEY);
        $signature = md5($pitchprint_api_value . html_entity_decode($pitchprint_secret_value) . $timestamp);

        $body = [
            'products' => $items,
            'client' => 'ps',
            'billingEmail' => $customer->email,
            'billingPhone' => $address->phone,
            'shippingName' => $address->firstname . ' ' . $address->lastname,
            'shippingAddress' => $address->company . ',\n' . $address->address1 . ',\n' . $address->address2 . ',\n' . $address->city . ',\n' . $address->postcode . ',\n' . $address->country,
            'orderId' => $params['id_order'],
            'customer' => $customer->id,
            'apiKey' => $pitchprint_api_value,
            'signature' => $signature,
            'status' => $status,
            'timestamp' => $timestamp,
            'shop_id' => (int) Context::getContext()->shop->id,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, "https://api.pitchprint.io/runtime/order-{$status}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $output = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlerr = curl_error($ch);
        curl_close($ch);

        if ($curlerr && $http_status != 200) {
            $error_message = ['error' => $curlerr];
            error_log(print_r($error_message, true));
        }
    }

    public function hookDisplayHeader($params)
    {
        if ($this->context->controller->php_self === 'product') {
            $productId = (int) Tools::getValue('id_product');
            $pp_design_options = json_decode(Configuration::get(PITCHPRINT_P_DESIGNS), true);
            $pp_productValues = isset($pp_design_options[$productId]) ? $pp_design_options[$productId] : '';

            // Language id
            $lang_id = (int) Configuration::get('PS_LANG_DEFAULT');
            // Load product object
            $product = new Product($productId, false, $lang_id);

            $db = Db::getInstance();

            if (isset($pp_design_options[$productId])) {
                $indexval = $db->getValue(
                    'SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . "customization_field` 
					WHERE `id_product` = {$productId} 
					AND `type` = 1  
					AND `is_module` = 1 
					AND `id_customization_field` 
					IN (
						SELECT `id_customization_field` FROM `" . _DB_PREFIX_ . "customization_field_lang` 
						WHERE `name` = '" . PITCHPRINT_ID_CUSTOMIZATION_NAME . "'
					)"
                );

                $customization_datas = $this->context->cart->getProductCustomization($productId, null, true);
                $pp_values = $customization_datas;

                if (!empty($pp_values)) {
                    $pp_values = array_filter($pp_values, function ($item) use ($indexval) {
                        return $item['index'] == $indexval;
                    });
                }

                $pp_customization_id = 0;
                if (!empty($customization_datas)) {
                    $pp_customization_id = $customization_datas[0]['id_customization'];
                }

                if (!empty($pp_values)) {
                    $request = 'SELECT `value` FROM `' . _DB_PREFIX_ . PITCHPRINT_TABLE_NAME . "` WHERE `cId` = {$pp_customization_id}";
                    $pp_values = $db->getValue($request);
                }

                // Check for project in session cookie
                if (empty($pp_values) && isset(Context::getContext()->cookie->pp_projects)) {
                    $ppCookie = json_decode(Context::getContext()->cookie->pp_projects, true);
                    if (isset($ppCookie[$productId])) {
                        $pp_values = $ppCookie[$productId];
                    }
                }

                $pp_previews = '';
                $pp_mode = 'new';
                $pp_project_id = '';

                $opt_ = is_string($pp_values) ? json_decode(rawurldecode($pp_values), true) : $pp_values;

                if (!empty($opt_)) {
                    if ($opt_['type'] === 'u') {
                        $pp_previews = $opt_['previews'];
                        $pp_upload_ready = true;
                        $pp_mode = 'upload';
                    } elseif ($opt_['type'] === 'p') {
                        $pp_mode = 'edit';
                        $pp_project_id = $opt_['projectId'];
                        $pp_previews = $opt_['numPages'];
                    }
                }

                $pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
                $pp_designValuesArray = explode(':', $pp_productValues);

                if (!is_string($pp_values)) {
                    $pp_values = json_encode($pp_values, true);
                }

                // update product customizable
                Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'product` SET `customizable` = 1 WHERE `id_product` = ' . (int) $productId);

                // update product_shop count fields labels
                ObjectModel::updateMultishopTable('product', [
                    'customizable' => 1,
                ], 'a.id_product = ' . (int) $productId);

                Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', '1');

                $ppData = [
                    'createButtons' => true,
                    'client' => 'ps',
                    'uploadUrl' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/pitchprint/uploads/',
                    'cValues' => $pp_values,
                    'projectId' => $pp_project_id,
                    'userId' => $this->context->cookie->id_customer,
                    'previews' => $pp_previews,
                    'mode' => $pp_mode,
                    'langCode' => $this->context->language->iso_code,
                    'enableUpload' => 0, // $pp_designValuesArray[1],
                    'displayMode' => (isset($pp_designValuesArray[3]) ? $pp_designValuesArray[3] : ''),
                    'designId' => $pp_designValuesArray[0],
                    'apiKey' => $pp_apiKey,
                    'product' => [
                        'id' => $productId,
                        'name' => addslashes($product->name),
                        'url' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=product&id_product=' . $productId,
                    ],
                    'id_customization' => $pp_customization_id,
                ];

                $userData = '';
                if ($this->context->customer->isLogged()) {
                    $fname = addslashes($this->context->cookie->customer_firstname);
                    $lname = addslashes($this->context->cookie->customer_lastname);

                    $cus = new Customer((int) $this->context->cookie->id_customer);
                    $cusInfo = $cus->getAddresses((int) Configuration::get('PS_LANG_DEFAULT'));
                    $cusInfo = $cusInfo[0];
                    $addr = "{$cusInfo['address1']}<br>";
                    if (!empty($cusInfo['address2'])) {
                        $addr .= "{$cusInfo['address2']}<br>";
                    }
                    $addr .= "{$cusInfo['city']} {$cusInfo['postcode']}<br>";
                    if (!empty($cusInfo['state'])) {
                        $addr .= "{$cusInfo['state']}<br>";
                    }
                    $addr .= "{$cusInfo['country']}";

                    $addr = trim($addr);

                    $ppData['userData'] = [
                        'email' => $this->context->cookie->email,
                        'name' => $fname . ' ' . $lname,
                        'firstname' => $fname,
                        'lastname' => $lname,
                        'telephone' => $cusInfo['phone'],
                        'fax' => '',
                        'address' => addslashes($addr),
                    ];
                }

                Media::addJsDef(
                    [
                        'pitchprintProductData' => $ppData,
                     ]
                );

                $this->context->controller->registerJavascript(
                    'module-pitchprint-product-data',
                    'modules/' . $this->name . '/views/js/product.js',
                    ['position' => 'bottom', 'priority' => 199]
                );

                $this->context->controller->registerJavascript(
                    'module-pitchprint-client-js',
                    PP_CLIENT_JS,
                    ['server' => 'remote', 'position' => 'head', 'priority' => 200]
                );

                $this->context->controller->registerJavascript(
                    'module-pitchprint-product-cart-type',
                    'modules/' . $this->name . '/views/js/cartType.js',
                    ['position' => 'bottom', 'priority' => 201]
                );

                $this->context->controller->registerJavascript(
                    'module-pitchprint-noes6-js',
                    PP_NOES6_JS,
                    ['server' => 'remote', 'position' => 'bottom', 'priority' => 202]
                );

                $this->context->controller->registerJavascript(
                    'module-pitchprint-product-buttons',
                    'modules/' . $this->name . '/views/js/client.js',
                    ['position' => 'bottom', 'priority' => 203]
                );
            }
        } elseif (substr($this->context->controller->php_self, 0, 5) === 'cart' || $this->context->controller->php_self === 'order-detail' || $this->context->controller->php_self === 'order-confirmation' || $this->context->controller->php_self === 'my-account') {
            $this->context->controller->registerJavascript(
                'pp-client-js',
                PP_CLIENT_JS,
                ['server' => 'remote', 'position' => 'head', 'priority' => 200]
            );

            $this->context->controller->registerJavascript(
                'module-pitchprint-product-buttons-type',
                'modules/' . $this->name . '/views/js/cartType.js',
                ['position' => 'bottom', 'priority' => 201]
            );

            $this->context->controller->registerJavascript(
                'pp-noes6-js',
                PP_NOES6_JS,
                ['server' => 'remote', 'bottom' => 'head', 'priority' => 202]
            );

            $this->context->controller->registerJavascript(
                'magnific-photo',
                MAGNIFIC_JS,
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
            );
            $this->context->controller->registerStylesheet(
                'magnific-photo-css',
                MAGNIFIC_CSS,
                ['server' => 'remote', 'position' => 'bottom', 'priority' => 200]
            );

            $pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
            $ppData = [
                'staging' => true,
                'client' => 'ps',
                'mode' => 'edit',
                'userId' => $this->context->cookie->id_customer,
                'langCode' => $this->context->language->iso_code,
                'apiKey' => $pp_apiKey,
                'afterValidation' => ($this->context->controller->php_self === 'my-account' ? '_fetchProjects' : '_sortCart'),
            ];

            $this->context->smarty->assign(['ppData' => $ppData]);

            return $this->display(__DIR__, '/views/templates/front/ppCartData.tpl');
        }
    }

// Admin functions =====================================================================================

    public function hookDisplayBackOfficeHeader($params)
    {
        if (Tools::getValue('ajax')) {
            return;
        }
        $_controller = $this->context->controller;
        if ($_controller->controller_name === 'AdminCarts' || $_controller->controller_name === 'AdminProducts' || $_controller->controller_name === 'AdminOrders') {
            $this->context->controller->addJquery();
            $this->context->controller->addJS(PP_ADMIN_JS);
        }
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $id_product = (int) $params['id_product'];
        if (Validate::isLoadedObject($product = new Product($id_product))) {
            $pp_val = '';
            $p_designs = json_decode(Configuration::get(PITCHPRINT_P_DESIGNS), true);
            if (!empty($p_designs[$id_product])) {
                $pp_val = $p_designs[$id_product];
            }

            $indexval = Db::getInstance()->getValue('SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . 'customization_field` WHERE `id_product` = ' . (int) Tools::getValue('id_product') . ' AND `type` = 1  AND `is_module` = 1');
            //			$indexval = Db::getInstance()->getValue("SELECT `id_customization_field` FROM `"._DB_PREFIX_."customization_field` WHERE `id_product` = " . $id_product . " AND `type` = 1 ");

            if (isset($p_designs[$id_product])) {
                $pp_data_params = explode(':', $p_designs[$id_product]);
                $current_display_opt = (isset($pp_data_params[3]) ? $pp_data_params[3] : '');
            } else {
                $current_display_opt = '';
            }

            $pp_timestamp = time();
            $pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
            $pp_secretKey = Configuration::get(PITCHPRINT_SECRET_KEY);
            $pp_signature = (!empty($pp_secretKey) && !empty($pp_apiKey)) ? md5($pp_apiKey . $pp_secretKey . $pp_timestamp) : '';
            $pp_options = isset($p_designs[$id_product]) ? $p_designs[$id_product] : '0';

            $this->context->smarty->assign([
                'pp_timestamp' => $pp_timestamp,
                'pp_apiKey' => $pp_apiKey,
                'pp_signature' => $pp_signature,
                'pp_options' => $pp_options,
                'current_display_opt' => $current_display_opt,
                'PPADMIN_DEF' => PPADMIN_DEF,
                'pp_val' => $pp_val,
                'indexval' => $indexval,
            ]);

            return $this->display(__FILE__, '/views/templates/admin/displayAdminProductsExtra.tpl');
        } else {
            $this->context->controller->errors[] = Tools::displayError('You must first save the product before assigning a design!');
        }
    }

    // Reset product upon add to cart
    public function hookActionCartUpdateQuantityBefore($params)
    {
        $productId = $params['product']->id;
        if (isset(Context::getContext()->cookie->pp_projects)) {
            $current = json_decode(Context::getContext()->cookie->pp_projects, true);
            unset($current[$productId]);
            Context::getContext()->cookie->pp_projects = json_encode($current);
        }
    }

    private function createCustomizationField($id_product)
    {
        $p_designs = json_decode(Configuration::get(PITCHPRINT_P_DESIGNS), true);
        if (!isset($p_designs[$id_product]) && empty($p_designs[$id_product])) {
            return null;
        }
        $arr = explode(':', $p_designs[$id_product]);

        Db::getInstance()->insert('customization_field', ['id_product' => $id_product, 'type' => 1, 'required' => $arr[2], 'is_module' => 1]);
        $custmz_field = (int) Db::getInstance()->Insert_ID();

        if (!empty($custmz_field)) {
            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . "customization_field_lang` (`id_customization_field`, `id_lang`, `name`) VALUES ('{$custmz_field}', '{$lang['id_lang']}', '" . PITCHPRINT_ID_CUSTOMIZATION_NAME . "') ON DUPLICATE KEY UPDATE `id_lang` = '{$lang['id_lang']}', `name` = '" . PITCHPRINT_ID_CUSTOMIZATION_NAME . "'");
            }
        }

        return $custmz_field;
    }

    public function hookActionProductUpdate($params)
    {
        $pp_pick = (string) Tools::getValue('ppa_values');
        if (!empty($pp_pick) && $pp_pick != '') {
            $id_product = (int) $params['id_product'];

            $p_designs = json_decode(Configuration::get(PITCHPRINT_P_DESIGNS), true);
            $p_designs[$id_product] = $pp_pick;
            Configuration::updateValue(PITCHPRINT_P_DESIGNS, json_encode($p_designs));
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        $pp_timestamp = time();
        $pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
        $pp_secretKey = Configuration::get(PITCHPRINT_SECRET_KEY);
        $pp_signature = (!empty($pp_secretKey) && !empty($pp_apiKey)) ? md5($pp_apiKey . $pp_secretKey . $pp_timestamp) : '';

        Media::addJsDef(
            [
                'pp_timestamp' => $pp_timestamp,
                'pp_apiKey' => $pp_apiKey,
                'pp_signature' => $pp_signature,
             ]
        );
        $this->context->controller->addJS('modules/' . $this->name . '/views/js/adminOrder.js');
    }

    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit' . $this->name)) {
            $pitchprint_api = (string) Tools::getValue(PITCHPRINT_API_KEY);
            $pitchprint_secret = (string) Tools::getValue(PITCHPRINT_SECRET_KEY);

            if (!$pitchprint_api || !Validate::isGenericName($pitchprint_api) || !$pitchprint_secret || !Validate::isGenericName($pitchprint_secret)) {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                $pitchprint_api = str_replace(' ', '', $pitchprint_api);
                $pitchprint_secret = str_replace(' ', '', $pitchprint_secret);
                Configuration::updateValue(PITCHPRINT_API_KEY, $pitchprint_api);
                Configuration::updateValue(PITCHPRINT_SECRET_KEY, $pitchprint_secret);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->renderForm();
    }

    public function renderForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('PitchPrint API Key'),
                    'name' => PITCHPRINT_API_KEY,
                    'suffix' => '&nbsp; &nbsp; :&nbsp; <a href="https://admin.pitchprint.com/domains" target="_blank">Generate Keys here</a>, &nbsp; &nbsp; : &nbsp; &nbsp; <a target="_blank" href="https://docs.pitchprint.com">Online Documentation</a>',
                    'size' => 40,
                    'required' => true,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('PitchPrint SECRET Key'),
                    'name' => PITCHPRINT_SECRET_KEY,
                    'size' => 40,
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'button',
            ],
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list'),
            ],
        ];

        // Load current value
        $helper->fields_value[PITCHPRINT_API_KEY] = Configuration::get(PITCHPRINT_API_KEY);
        $helper->fields_value[PITCHPRINT_SECRET_KEY] = Configuration::get(PITCHPRINT_SECRET_KEY);

        return $helper->generateForm($fields_form);
    }
}
