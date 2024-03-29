<?php

if (!defined('_PS_VERSION_')) exit;

	define('PP_IOBASE', 'https://pitchprint.io');
	define('PP_CLIENT_JS', 'https://pitchprint.io/rsc/js/client.js');
	define('PP_ADMIN_JS', 'https://pitchprint.io/rsc/js/a.ps.js');
	define('PP_NOES6_JS', 'https://pitchprint.io/rsc/js/noes6.js');
	
	define('PPADMIN_DEF', "var PPADMIN = window.PPADMIN; if (typeof PPADMIN === 'undefined') window.PPADMIN = PPADMIN = { version: '9.0.0', readyFncs: [] };");
	define('PP_VERSION', "9.0.0");

    define('PITCHPRINT_API_KEY', 'pitchprint_API_KEY');
    define('PITCHPRINT_SECRET_KEY', 'pitchprint_SECRET_KEY');

    define('PITCHPRINT_P_DESIGNS', 'pitchprint_p_designs');

    define('PITCHPRINT_ID_CUSTOMIZATION_NAME', '@PP@');

	define('MAGNIFIC_JS', '//dta8vnpq1ae34.cloudfront.net/javascripts/jquery.magnific-popup.min.js');
	define('MAGNIFIC_CSS', '//dta8vnpq1ae34.cloudfront.net/stylesheets/magnific-popup.css');

class PitchPrint extends Module {

    public function __construct() {
      $this->name = 'pitchprint';
      $this->tab = 'front_office_features';
      $this->version = 9.0;
      $this->author = 'PitchPrint Inc.';
      $this->need_instance = 1;
      $this->ps_versions_compliancy = array('min' => '1.5', 'max' => _PS_VERSION_);

      parent::__construct();

      $this->displayName = $this->l('PitchPrint');
      $this->description = $this->l('A beautiful web based print customization app for your online store. Integrates with Prestashop.');

      $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install() {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install())
            return false;

        Db::getInstance()->execute("ALTER TABLE `" . _DB_PREFIX_ . "customized_data` CHANGE `value` `value` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");

		$_pKey = Configuration::get(PITCHPRINT_API_KEY);
		$_pSec = Configuration::get(PITCHPRINT_SECRET_KEY);
		$_pDes = Configuration::get(PITCHPRINT_P_DESIGNS);

		if (empty($_pKey)) Configuration::updateValue(PITCHPRINT_API_KEY, '');
		if (empty($_pSec)) Configuration::updateValue(PITCHPRINT_SECRET_KEY, '');
		if (empty($_pDes)) Configuration::updateValue(PITCHPRINT_P_DESIGNS, serialize(array()));

        return $this->registerHook('displayProductButtons') &&
        $this->registerHook('displayHeader') &&
        $this->registerHook('displayAdminOrder') &&
        $this->registerHook('displayBackOfficeHeader') &&
        $this->registerHook('actionProductUpdate') &&
        $this->registerHook('actionOrderStatusPostUpdate') &&
        $this->registerHook('displayAdminProductsExtra') &&
        $this->registerHook('displayCustomerAccount');
    }
    public function uninstall() {
        if (parent::uninstall()) {
            return true;
		}
		return false;
    }

	public function hookActionOrderStatusPostUpdate($params) {
		$order = new Order((int)$params['id_order']);
		$status = ($order->hasBeenPaid() || (int)$params['newOrderStatus']->paid === 1) ? 1 : 0;

		if ($status === 1) {
			$products = $order->getCartProducts();
			$customer = $order->getCustomer();
			$items = array();

			$address = new Address($order->id_address_delivery);
			foreach($products as $prod) {
				$pitchprint = '';
				if ($prod['customizedDatas'] != null) {
					foreach($prod['customizedDatas'] as $c_main) {
						foreach($c_main as $c_inner) {
							if (isset($c_inner['datas'])) {
								foreach ($c_inner['datas'] as $c_data) {
									foreach ($c_data as $c_item)
									if ($c_item['name'] == PITCHPRINT_ID_CUSTOMIZATION_NAME) {
										$pitchprint = rawurldecode($c_item['value']);
									}
								}
							}
						}
					}
				}
				$items[] = array(
					'name' => $prod['product_name'],
					'id' => $prod['product_id'],
					'qty' => $prod['cart_quantity'],
					'pitchprint' => json_encode(json_decode($pitchprint))
				);
			}

			$pp_empty = true;
			foreach ($items as $item) if (!empty(json_decode($item['pitchprint']))) $pp_empty = false;
			if ($pp_empty) return;
			
			$items = json_encode($items);
			
			$timestamp = time();
			$pitchprint_api_value = Configuration::get(PITCHPRINT_API_KEY);
			$pitchprint_secret_value = Configuration::get(PITCHPRINT_SECRET_KEY);
			$signature = md5($pitchprint_api_value . html_entity_decode($pitchprint_secret_value) . $timestamp);

			$body = array (
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
				'timestamp' => $timestamp
			);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_URL, "https://api.pitchprint.io/runtime/order-complete");
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
				$error_message = array('error' => $curlerr);
				error_log(print_r($error_message, true));
			}
		}
	}

    public function hookDisplayProductButtons($params) {
        $productId = (int)Tools::getValue('id_product');
        $indexval = Db::getInstance()->getValue("SELECT `id_customization_field` FROM `"._DB_PREFIX_."customization_field` WHERE `id_product` = {$productId} AND `type` = 1");
		$pp_values = (string)Tools::getValue('values');

        if (Tools::getValue('clear') == true) {
            $this->context->cart->deleteCustomizationToProduct($productId, (int)$indexval);
            die('cleared');
        }

		if (!empty($pp_values)) {
            if (!$this->context->cart->id && isset($_COOKIE[$this->context->cookie->getName()])) {
				$this->context->cart->add();
				$this->context->cookie->id_cart = (int)$this->context->cart->id;
            }
            $this->context->cart->addPictureToProduct($productId, (int)$indexval, 1, $pp_values);
        } else {
            $pp_values = $this->context->cart->getProductCustomization($productId, (int)$indexval, true);
			if (!empty($pp_values)) $pp_values = $pp_values[0]['value'];
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
			} else if ($opt_['type'] === 'p') {
				$pp_mode = 'edit';
				$pp_project_id =  $opt_['projectId'];
				$pp_previews = $opt_['numPages'];
			}
		}

        $pp_design_options = unserialize(Configuration::get(PITCHPRINT_P_DESIGNS));
        $pp_productValues = isset($pp_design_options[$productId]) ? $pp_design_options[$productId] : '';
        $pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
        $pp_designValuesArray = explode(':', $pp_productValues);

        if (Tools::getValue('ajax') == true) die(Tools::getHttpHost(true) . __PS_BASE_URI__ . "index.php?controller=product&id_product=" . $productId);

        if (!is_string($pp_values)) $pp_values = json_encode($pp_values, true);

		$userData = '';
		if ($this->context->customer->isLogged()) {
			$fname = addslashes($this->context->cookie->customer_firstname);
			$lname = addslashes($this->context->cookie->customer_lastname);

			$cus = new Customer((int)$this->context->cookie->id_customer);
			$cusInfo = $cus->getAddresses((int)Configuration::get('PS_LANG_DEFAULT'));
			$cusInfo = $cusInfo[0];
			$addr = "{$cusInfo['address1']}<br>";
			if (!empty($cusInfo['address2'])) $addr .= "{$cusInfo['address2']}<br>";
			$addr .= "{$cusInfo['city']} {$cusInfo['postcode']}<br>";
			if (!empty($cusInfo['state'])) $addr .= "{$cusInfo['state']}<br>";
			$addr .= "{$cusInfo['country']}";

			$addr = trim($addr);

			$userData = ",
				userData: {
					email: '{$this->context->cookie->email}',
					name: '{$fname} {$lname}',
					firstname: '{$fname}',
					lastname: '{$lname}',
					telephone: '{$cusInfo['phone']}',
					fax: '',
					address: decodeURI('" . addslashes($addr) . "'.split('<br>').join('\\n'))
				}";
		}

        return !empty($pp_productValues) ? "
			<script type=\"text/javascript\">
				ajaxsearch = undefined;
				(function(_doc) {
					window.ppclient = new PitchPrintClient({
						uploadUrl: '" . Tools::getHttpHost(true) . __PS_BASE_URI__ . "modules/pitchprint/uploads/',
						userId: '{$this->context->cookie->id_customer}',
						langCode: '{$this->context->language->iso_code}',
						enableUpload: {$pp_designValuesArray[1]},
						displayMode: '" . (isset($pp_designValuesArray[3]) ? $pp_designValuesArray[3] : '') ."',
						designId: '{$pp_designValuesArray[0]}',
						previews: " . json_encode($pp_previews) . ",
						mode: '{$pp_mode}',
						createButtons: true,
						projectId: '{$pp_project_id}',
						pluginRoot: '" . Tools::getHttpHost(true) . __PS_BASE_URI__ . "modules/pitchprint/',
						apiKey: '{$pp_apiKey}',
						client: 'ps',
						product: {
							id: '{$productId}',
							name: '" . addslashes($params['product']->name) . "',
							url: '" . Tools::getHttpHost(true) . __PS_BASE_URI__ . "index.php?controller=product&id_product={$productId}'
						}{$userData}
					});
				})(document);
			</script><script src='".PP_NOES6_JS."'></script>" : "";
    }

	public function hookDisplayCustomerAccount($params) {
		return '<div id="pp_mydesigns_div"></div>';
	}

    public function hookDisplayHeader($params) {
		if ($this->context->controller->php_self === 'product') {
			$productId = (int)Tools::getValue('id_product');
			$pp_design_options = unserialize(Configuration::get(PITCHPRINT_P_DESIGNS));
			if (isset($pp_design_options[$productId])) {
				$this->context->controller->addJS(PP_CLIENT_JS);
				$this->context->controller->addJS($this->_path . 'views/js/cartType.js');
			}
		} else if (substr($this->context->controller->php_self, 0, 5) === 'order' || $this->context->controller->php_self === 'history' || $this->context->controller->php_self === 'my-account') {
			$this->context->controller->addJS(PP_CLIENT_JS);
			$this->context->controller->addJS($this->_path . 'views/js/cartType.js');
			
			$pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
			$add_js = 'if (document.getElementById("block-order-detail")) { new MutationObserver(function(mutations){ if (mutations && mutations.length) { window.ppclient && window.ppclient._sortCart() }}).observe(document.getElementById("block-order-detail"), {childList:true});};';
			if ($this->context->controller->php_self === 'my-account') {
				$this->context->controller->addJS(MAGNIFIC_JS);
				$this->context->controller->addCSS(MAGNIFIC_CSS);
				$after_validation = '_fetchProjects';
				$add_js .= "if ($('.footer_links').length) { $('#pp_mydesigns_div').insertBefore($('.footer_links')); }";
			} else {
				$after_validation = '_sortCart';
			}
			
			return "
				<script>
					(function(_doc) {
						window.ppclient = new PitchPrintClient({
							userId: '{$this->context->cookie->id_customer}',
							langCode: '{$this->context->language->iso_code}',
							mode: 'edit',
							pluginRoot: '" . Tools::getHttpHost(true) . __PS_BASE_URI__ . "modules/pitchprint/',
							apiKey: '{$pp_apiKey}',
							client: 'ps',
							afterValidation: '{$after_validation}',
							isHistoryPage: " . ($this->context->controller->php_self === 'history' ? 'true' : 'false') . "
						});
					})(document);
					jQuery(function($) { {$add_js} });
				</script><script src='".PP_NOES6_JS."'></script>
				<style>.ppc-ps-img{margin:5px;border:1px solid #ccc;padding:5px;display:inline-block;}.pp-90thumb{width:90px;}</style>
			";
		}
    }


//Admin functions =====================================================================================

	public function hookDisplayBackOfficeHeader($params) {
		$_controller = $this->context->controller;
		if ($_controller->controller_name === 'AdminProducts' || $_controller->controller_name === 'AdminOrders') {
			$this->context->controller->addJquery();
			$this->context->controller->addJS(PP_ADMIN_JS);
		}
    }

    public function hookDisplayAdminProductsExtra($params) {
        if (Validate::isLoadedObject($product = new Product((int)Tools::getValue('id_product')))) {
            $pp_val = '';
            $id_product = (int)Tools::getValue('id_product');
            $p_designs = unserialize(Configuration::get(PITCHPRINT_P_DESIGNS));
            if (!empty($p_designs[$id_product])) $pp_val = $p_designs[$id_product];

            $indexval = Db::getInstance()->getValue("SELECT `id_customization_field` FROM `"._DB_PREFIX_."customization_field` WHERE `id_product` = ".(int)Tools::getValue('id_product')." AND `type` = 1");

			if (isset($p_designs[$id_product])) {
				$pp_data_params = explode(':',$p_designs[$id_product]);
				$current_display_opt = ( isset($pp_data_params[3]) ? $pp_data_params[3] : '');
			}else{
				$current_display_opt = '';
			}
			
            $str = '<div class="product-tab-content"><div style="padding: 20px" class="panel product-tab"><h3>Assign PitchPrint Design</h3><div class="alert alert-info">
				  You can create your designs at <a target="_blank" href="https://admin.pitchprint.io/designs">https://admin.pitchprint.io/designs</a> </div><div id="w2p-div">
				  <div style="margin-bottom:10px">
				  <select id="ppa_pick" name="ppa_pick" style="width:300px;" class="c-select form-control" ><option style="color:#aaa" value="0">Loading..</option></select>
				  <input type="hidden" id="ppa_values" name="ppa_values" value="' . $pp_val . '" />
				  <input type="hidden" id="pp_indexVal" name="pp_indexVal" value="' . $indexval . '" />
				  <select id="ppa_pick_display_mode" name="ppa_pick_display_mode" style="width:300px;margin-top: 10px" class="c-select form-control" >
					<option style="color:#aaa" value="">Display Mode</option>
					<option style="color:#aaa" value="modal">Full Window</option>
					<option style="color:#aaa" value="inline">Inline</option>
					<option style="color:#aaa" value="mini">Mini</option>
				  </select>
				  <script>
					(function(){
						var displayMode = jQuery(\'[name="ppa_pick_display_mode"]\');
						displayMode.val("'.$current_display_opt.'");
						
						displayMode[0].addEventListener("web2print_options_changed", _updatePpOptions);
						displayMode.change(_updatePpOptions);
						
						function _updatePpOptions () {
							var ppOptions = jQuery("#ppa_values");
							var a = ppOptions.val().split(":");
							a[3] = displayMode.val();
							ppOptions.val(a.join(":"));	 
						}
						
					})();
				  </script>
				  </div>
					<div class="checkbox" style="margin-bottom:10px">
					<label for="ppa_pick_upload"> <input type="checkbox" name="ppa_pick_upload" id="ppa_pick_upload" value="">Enable clients upload their files.</label>
					</div>
					<div class="checkbox">
					<label for="ppa_pick_hide_cart_btn"> <input type="checkbox" name="ppa_pick_hide_cart_btn" id="ppa_pick_hide_cart_btn" value="">Required.</label>
					</div>
				  </div><div id="pp_div_footer" class="panel-footer">
			<button type="submit" name="submitAddproduct" class="btn btn-default pull-right"><i class="process-icon-save"></i> Save</button>
			<button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right"><i class="process-icon-save"></i> Save and stay</button> </div></div></div>';

			$pp_timestamp = time();
			$pp_apiKey = Configuration::get(PITCHPRINT_API_KEY);
			$pp_secretKey = Configuration::get(PITCHPRINT_SECRET_KEY);
			$pp_signature = (!empty($pp_secretKey) && !empty($pp_apiKey)) ? md5($pp_apiKey . $pp_secretKey . $pp_timestamp) : '';
			$pp_options = isset($p_designs[$id_product]) ? $p_designs[$id_product] : '0';

			return $str . "
				<script type=\"text/javascript\">
					jQuery(function($) {
						" . PPADMIN_DEF . "
						PPADMIN.vars = {
							credentials: { timestamp: '" . $pp_timestamp . "', apiKey: '" . $pp_apiKey . "', signature: '" . $pp_signature . "' },
							productValues: \"{$pp_options}\",
							apiKey: '{$pp_apiKey}'
						};
						PPADMIN.readyFncs.push('init', 'fetchDesigns');
						if (typeof PPADMIN.start !== 'undefined') PPADMIN.start();
					});
				</script>";
        } else {
			$this->context->controller->errors[] = Tools::displayError('You must first save the product before assigning a design!');
		}
    }
    
    public function hookActionProductUpdate($params) {
		$pp_pick = (string)Tools::getValue('ppa_values');
        if (!empty($pp_pick) && $pp_pick != "") {
            $id_product = (int)Tools::getValue('id_product');
            $p_designs = unserialize(Configuration::get(PITCHPRINT_P_DESIGNS));
            $p_designs[$id_product] = (string)Tools::getValue('ppa_values');
            Configuration::updateValue(PITCHPRINT_P_DESIGNS, serialize($p_designs));

            $custmz_field = Db::getInstance()->getValue("SELECT `id_customization_field` FROM `" . _DB_PREFIX_ . "customization_field` WHERE `id_product` = {$id_product} AND `type` = 1");

            if (!empty($custmz_field)) {
                $languages = Language::getLanguages(false);
                foreach ($languages as $lang) {
					Db::getInstance()->execute("INSERT INTO `" . _DB_PREFIX_ . "customization_field_lang` (`id_customization_field`, `id_lang`, `name`) VALUES ('{$custmz_field}', '{$lang['id_lang']}', '" . PITCHPRINT_ID_CUSTOMIZATION_NAME . "') ON DUPLICATE KEY UPDATE `id_lang` = '{$lang['id_lang']}', `name` = '" . PITCHPRINT_ID_CUSTOMIZATION_NAME . "'");
                }
            }
        }
        return;
    }

    public function hookDisplayAdminOrder($params) {
        return "
            <script type=\"text/javascript\">
				jQuery(function($) {
					" . PPADMIN_DEF . "
					PPADMIN.vars = { };
					PPADMIN.readyFncs.push('init');
					if (typeof PPADMIN.start !== 'undefined') PPADMIN.start();
				});
            </script>";
    }



    public function getContent() {
      $output = null;

      if (Tools::isSubmit('submit'.$this->name)) {
          $pitchprint_api = strval(Tools::getValue(PITCHPRINT_API_KEY));
          $pitchprint_secret = strval(Tools::getValue(PITCHPRINT_SECRET_KEY));
          if (!$pitchprint_api  || empty($pitchprint_api) || !Validate::isGenericName($pitchprint_api) || !$pitchprint_secret  || empty($pitchprint_secret) || !Validate::isGenericName($pitchprint_secret)) {
              $output .= $this->displayError( $this->l('Invalid Configuration value') );
          } else {
                $pitchprint_api = str_replace(' ', '', $pitchprint_api);
                $pitchprint_secret = str_replace(' ', '', $pitchprint_secret);
                Configuration::updateValue(PITCHPRINT_API_KEY, $pitchprint_api);
                Configuration::updateValue(PITCHPRINT_SECRET_KEY, $pitchprint_secret);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
          }
      }
      return $output.$this->renderForm();
    }

    public function renderForm() {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
				'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('PitchPrint API Key'),
                    'name' => PITCHPRINT_API_KEY,
                    'suffix' => '&nbsp; &nbsp; :&nbsp; <a href="https://admin.pitchprint.com/domains" target="_blank">Generate Keys here</a>, &nbsp; &nbsp; : &nbsp; &nbsp; <a target="_blank" href="https://docs.pitchprint.com">Online Documentation</a>',
                    'size' => 40,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('PitchPrint SECRET Key'),
                    'name' => PITCHPRINT_SECRET_KEY,
                    'size' => 40,
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value[PITCHPRINT_API_KEY] = Configuration::get(PITCHPRINT_API_KEY);
        $helper->fields_value[PITCHPRINT_SECRET_KEY] = Configuration::get(PITCHPRINT_SECRET_KEY);

        return $helper->generateForm($fields_form);
    }
}
