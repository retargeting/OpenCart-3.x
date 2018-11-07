<?php
/*
 * Retargeting Tracker for OpenCart 3.x
 */

require_once 'Retargeting_REST_API_Client.php';

class ControllerExtensionModuleRetargeting extends Controller {

    public function index() {

        $this->load->language('extension/module/retargeting');

        $this->load->model('checkout/order');
        $this->load->model('setting/setting');
        $this->load->model('design/layout');
        $this->load->model('catalog/category');
        $this->load->model('catalog/manufacturer');
        $this->load->model('catalog/product');
        $this->load->model('catalog/information');

        // Get values from admin settings
        $data['api_key_field']            = $this->config->get('module_retargeting_apikey');
        $data['api_secret_field']         = $this->config->get('module_retargeting_token');
        $data['retargeting_setEmail']     = htmlspecialchars_decode($this->config->get('module_retargeting_setEmail'));
        $data['retargeting_addToCart']    = htmlspecialchars_decode($this->config->get('module_retargeting_addToCart'));
        $data['retargeting_clickImage']   = htmlspecialchars_decode($this->config->get('module_retargeting_clickImage'));

        /*
         * Product Feed
         */
        if (isset($_GET['json']) && $_GET['json'] === 'retargeting') {
            header('Content-Type: application/json');

            $products = $this->model_catalog_product->getProducts();
            $retargetingFeed = array();
            foreach ($products as $product) {
                $retargetingFeed[] = array(
                    'id' => $product['product_id'],
                    'price' => round(
                      $this->tax->calculate(
                        $product['price'],
                        $product['tax_class_id'],
                        $this->config->get('config_tax')
                    ), 2),
                    'promo' => (
                    isset($product['special']) ? round(
                      $this->tax->calculate(
                        $product['special'],
                        $product['tax_class_id'],
                        $this->config->get('config_tax')
                     ), 2)
                     : 0),
                    'promo_price_end_date' => null,
                    'inventory' => array(
                        'variations' => false,
                        'stock' => (($product['quantity'] > 0) ? 1 : 0)
                    ),
                    'user_groups' => false,
                    'product_availability' => null
                );
            }

            echo json_decode($retargetingFeed);
            die();
        }

        /*
         * Discount Codes Generator
         *
         * REQUEST:
         * POST: key=your_retargeting_key
         * GET: type=0&value=30&count=3
         * type => (int) 0: Fixed; 1: Percentage; 2: Free Delivery
         * value => (float) actual value of the discount
         * count => (int) number of discount codes to be generated
         *
         * RESPONDS:
         * JSON: ['code1', 'code2', 'code3', ... ]
         *
         * STEP 1: check $_POST
         * STEP 2: add the discount codes to the local database
         * STEP 3 : expose the codes to Retargeting
         * STEP 4: kill the script
         */
        if (isset($_GET) && isset($_GET['key']) && ($_GET['key'] === $data['api_key_field'])) {
            $discountType  = (isset($_GET['type'])) ? (filter_var($_GET['type'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';
            $discountValue = (isset($_GET['value'])) ? (filter_var($_GET['value'], FILTER_SANITIZE_NUMBER_FLOAT)) : 'Received other than float';
            $discountCodes = (isset($_GET['count'])) ? (filter_var($_GET['count'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';

            $generateCode = function() {
                return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 1) . substr(str_shuffle('AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz'), 0, 9);
            };

            $dateTime = new DateTime();
            $startDate = $dateTime->format('Y-m-d');
            $dateTime->modify('+6 months');
            $expirationDate = $dateTime->format('Y-m-d');

            for ($i = $discountCodes; $i > 0; $i--) {
                $code = $generateCode();
                $discountCodesCollection[] = $code;

                // Discount type: Fixed Value
                if ($discountType == 0) {
                  $this->db->query("
                      INSERT INTO " . DB_PREFIX . "coupon
                      SET name = 'Discount Code: RTG_FX',
                          code = '{$code}',
                          discount = '{$discountValue}',
                          type = 'F',
                          total = '0',
                          logged = '0',
                          shipping = '0',
                          date_start = '{$startDate}',
                          date_end = '',
                          uses_total = '1',
                          uses_customer = '1',
                          status = '1',
                          date_added = NOW()
                      ");
                // Discount type: Percentage
                } elseif ($discountType == 1) {
                  $this->db->query("
                      INSERT INTO " . DB_PREFIX . "coupon
                      SET name = 'Discount Code: RTG_PRCNT',
                          code = '{$code}',
                          discount = '{$discountValue}',
                          type = 'P',
                          total = '0',
                          logged = '0',
                          shipping = '0',
                          date_start = '{$startDate}',
                          date_end = '',
                          uses_total = '1',
                          uses_customer = '1',
                          status = '1',
                          date_added = NOW()
                      ");
                // Discount type: Free Delivery
              } elseif ($discountType == 2) {
                  $this->db->query("
                      INSERT INTO " . DB_PREFIX . "coupon
                      SET name = 'Discount Code: RTG_SHIP',
                          code = '{$code}',
                          discount = '0',
                          type = 'F',
                          total = '0',
                          logged = '0',
                          shipping = '1',
                          date_start = '{$startDate}',
                          date_end = '',
                          uses_total = '1',
                          uses_customer = '1',
                          status = '1',
                          date_added = NOW()
                      ");
              }
            } // End generating discount codes

            if (isset($discountCodesCollection) && !empty($discountCodesCollection)) {
                header('Content-Type: application/json');

                echo json_encode($discountCodesCollection);
            }

            die();
        } // End Discount Codes Generator

        // Helpers
        $data['cart_products']    = isset($this->session->data['cart']) ? $this->session->data['cart'] : false;
        $data['wishlist']         = !empty($this->session->data['wishlist']) ? $this->session->data['wishlist'] : false;
        $data['current_page']     = isset($this->request->get['route']) ? $this->request->get['route'] : false;
        $data['current_category'] = isset($this->request->get['path']) ? explode('_', $this->request->get['path']) : '';
        $data['count_categories'] = (count($data['current_category']) > 0) ? (count($data['current_category'])) : 0;
        $data['js_output']        = "/* --- Retargeting Tracker Functions --- */\n\n";

        // setEmail
        if (isset($this->session->data['customer_id']) && !empty($this->session->data['customer_id'])) {
            $fullName     = $this->customer->getFirstName() . $this->customer->getLastName();
            $emailAddress = $this->customer->getEmail();
            $phoneNumber  = $this->customer->getTelephone();

            $data['js_output'] .= "
                var _ra = _ra || {};

                _ra.setEmailInfo = {
                    'email': '{$emailAddress}',
                    'name': '{$fullName}',
                    'phone': '{$phoneNumber}'
                };

                if (_ra.ready !== undefined) {
                    _ra.setEmail(_ra.setEmailInfo);
                }
            ";
        } else {
            $data['setEmail'] = "
                /* --- setEmail --- */
                function checkEmail(email) {
                    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,9})+$/;
                    return regex.test(email);
                };

                (function($) {
                    jQuery(\"{$data['retargeting_setEmail']}\").blur(function() {
                        if ( checkEmail($(this).val()) ) {
                          _ra.setEmail({'email': $(this).val()});
                        }
                    });
                })(jQuery);
            ";

            $data['js_output'] .= $data['setEmail'];
        }

        // sendCategory
        if ($data['current_page'] == 'product/category') {
            $categoryIdParent = $data['current_category'][0];
            $categoryInfoParent = $this->model_catalog_category->getCategory($categoryIdParent);

            $data['sendCategory'] = '
                /* --- sendCategory --- */
                var _ra = _ra || {};

                _ra.sendCategoryInfo = {
            ';

            /* We have a nested category */
            if (count($data['current_category']) > 1) {

                for ($i = count($data['current_category']) - 1; $i > 0; $i--) {
                    $categoryId = $data['current_category'][$i];
                    $categoryInfo = $this->model_catalog_category->getCategory($categoryId);
                    $decodedCategoryInfoName = htmlspecialchars_decode($categoryInfo['name']);
                    $data['sendCategory'] .= "
                        'id': {$categoryId},
                        'name': '{$decodedCategoryInfoName}',
                        'parent': {$categoryIdParent},
                        'breadcrumb': [
                    ";
                    break;
                }

                array_pop($data['current_category']);

                for ($i = count($data['current_category']) - 1; $i >= 0; $i--) {
                    $categoryId = $data['current_category'][$i];
                    $categoryInfo = $this->model_catalog_category->getCategory($categoryId);

                    if ($i === 0) {

                        $data['sendCategory'] .= "
                          {
                            'id': {$categoryIdParent},
                            'name': 'Root',
                            'parent': false
                          }
                        ";

                        break;
                    }

                    $data['sendCategory'] .= "
                        {
                          'id': {$categoryId},
                          'name': '{$decodedCategoryInfoName}',
                          'parent': {$categoryIdParent}
                        },
                    ";

                }

                $data['sendCategory'] .= "]";
            /* We have a single category */
            } else {
                $data['category_id'] = $data['current_category'][0];
                $data['category_info'] = $this->model_catalog_category->getCategory($data['category_id']);
                $decodedDataCategoryInfoName = htmlspecialchars_decode($data['category_info']['name']);
                $data['sendCategory'] .= "
                    'id': {$data['category_id']},
                    'name': '{$decodedDataCategoryInfoName}',
                    'parent': false,
                    'breadcrumb': []
                ";
            }

            $data['sendCategory'] .= '};';
            $data['sendCategory'] .= "
                if (_ra.ready !== undefined) {
                    _ra.sendCategory(_ra.sendCategoryInfo);
                };
            ";

            $data['js_output'] .= $data['sendCategory'];

        }

        // sendBrand
        if ($data['current_page'] === 'product/manufacturer/info') {
            // Check if the current product is part of a brand
            if (isset($this->request->get['manufacturer_id']) && !empty($this->request->get['manufacturer_id'])) {
                $data['brand_id'] = $this->request->get['manufacturer_id'];
                $data['brand_name'] = $this->model_catalog_manufacturer->getManufacturer($this->request->get['manufacturer_id']);
                $decodedDataBrandName = htmlspecialchars_decode($data['brand_name']['name']);
                $data['sendBrand'] = "
                    var _ra = _ra || {};

                    _ra.sendBrandInfo = {
                        'id': {$data['brand_id']},
                        'name': '{$decodedDataBrandName}'
                    };

                    if (_ra.ready !== undefined) {
                        _ra.sendBrand(_ra.sendBrandInfo);
                    }
                ";

                $data['js_output'] .= $data['sendBrand'];
            }
        }

        // sendProduct, clickImage
        if ($data['current_page'] === 'product/product') {
            $productId          = $this->request->get['product_id'];
            $productUrl         = $this->url->link('product/product', 'product_id=' . $productId);
            $productDetails     = $this->model_catalog_product->getProduct($productId);
            $productCategories  = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$productId . "'");
            $productCategories  = $productCategories->rows;
            $decodedProductUrl  = htmlspecialchars_decode($productUrl);
            if ($this->request->server['HTTPS']) {
        			$data['shop_url'] = $this->config->get('config_ssl');
        		} else {
        			$data['shop_url'] = $this->config->get('config_url');
        		}
            $rootCat = array(array(
                'id' => 'Root',
                'name' => 'Root',
                'parent' => false,
                'breadcrumb' => array()
            ));

            $data['sendProduct'] = "
                var _ra = _ra || {};

                _ra.sendProductInfo = {
                    'id': $productId,
                    'name': '" . htmlspecialchars($productDetails['name'], ENT_QUOTES) . "',
                    'url': '{$decodedProductUrl}',
                    'img': '{$data['shop_url']}image/{$productDetails['image']}',
                    'price': '".round(
                      $this->tax->calculate(
                        $productDetails['price'],
                        $productDetails['tax_class_id'],
                        $this->config->get('config_tax')
                      ),2)."',
                    'promo': '". (
                      isset(
                        $productDetails['special']) ? round(
                          $this->tax->calculate(
                            $productDetails['special'],
                            $productDetails['tax_class_id'],
                            $this->config->get('config_tax')
                          ),2) : 0) ."',
                          'inventory': {
                    'variations': false,
                    'stock' : ".(($productDetails['quantity'] > 0) ? 1 : 0)."
                },
            ";

            /* Check if the product has a brand assigned */
            if (isset($productDetails['manufacturer_id'])) {
                $decodedProductDetailsManufacturer = htmlspecialchars_decode($productDetails['manufacturer']);
                $data['sendProduct'] .= "
                    'brand': {'id': {$productDetails['manufacturer_id']}, 'name': '{$decodedProductDetailsManufacturer}'},
                ";
            } else {
                $data['sendProduct'] .= "
                    'brand': false,
                ";
            }

            /* Check if the product has a category assigned */
            if (isset($productCategories) && !empty($productCategories)) {
                $productCat = $this->model_catalog_product->getCategories($productId);

                $catDetails = array();
                foreach ($productCat as $prodCatId) {
                    $categoryDetails = $this->model_catalog_category->getCategory($prodCatId['category_id']);

                    if (isset($categoryDetails['status']) && $categoryDetails['status'] == 1) {
                        $catDetails[] = $categoryDetails;
                    }
                }

                $preCat = array();
                foreach ($catDetails as $productCategory) {
                    if (isset($productCategory['parent_id']) && ($productCategory['parent_id'] == 0)) {
                        $preCat = array(array(
                            'id' => $productCategory['category_id'],
                            'name' => htmlspecialchars_decode($productCategory['name']),
                            'parent' => false,
                            'breadcrumb' => array()
                        ));
                    } else {
                        $breadcrumbDetails = $this->model_catalog_category->getCategory($productCategory['parent_id']);
                        $preCat = array(array(
                            'id' => (int)$productCategory['category_id'],
                            'name' => htmlspecialchars_decode($productCategory['name']),
                            'parent' => 'Root',
                            'breadcrumb' => array(array(
                                'id' => 'Root',
                                'name' => 'Root',
                                'parent' => false
                            ))
                        ));
                    }
                }

                if (!empty($preCat)) {
                    $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($preCat);
                } else {
                    $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($rootCat);
                }
            } else {
                $data['sendProduct'] .= "'" . 'category' . "':" . json_encode($rootCat);
            } // Close check if product has categories assigned

            $data['sendProduct'] .= "};";
            $data['sendProduct'] .= "
                if (_ra.ready !== undefined) {
                    _ra.sendProduct(_ra.sendProductInfo);
                }
            ";

            $data['js_output'] .= $data['sendProduct'];

            // clickImage
            $data['clickImage'] = "
                /* --- clickImage --- */
                (function($) {
                    jQuery(\"{$data['retargeting_clickImage']}\").click(function() {
                        _ra.clickImage({$productId});
                    });
                })(jQuery);
            ";
            $data['js_output'] .= $data['clickImage'];

            // addToCart
            $data['addToCart'] = "
                /* --- addToCart --- */
                (function($) {
                    if (jQuery(\"{$data['retargeting_addToCart']}\").length > 0) {
                        jQuery(\"{$data['retargeting_addToCart']}\").click(function() {
                            _ra.addToCart({$productId}, ".(($productDetails['quantity'] > 0) ? 1 : 0).", false, function(){console.log('addToCart fired!')});
                        });
                    }
                })(jQuery);
            ";

            $data['js_output'] .= $data['addToCart'];
        }

        // visitHelpPage
        if ($data['current_page'] === 'information/information') {
            $data['visitHelpPage'] = "
                /* --- visitHelpPage --- */
                var _ra = _ra || {};

                _ra.visitHelpPage = {'visit': true};
                if (_ra.ready !== undefined) {
                    _ra.visitHelpPage();
                }
            ";

            $data['js_output'] .= $data['visitHelpPage'];
        }

        // checkoutIds
        $checkoutModules = array(
          'checkout/checkout',
          'checkout/simplecheckout',
          'checkout/ajaxquickcheckout',
          'checkout/ajaxcheckout',
          'checkout/quickcheckout',
          'checkout/onepagecheckout',
          'checkout/cart',
          'quickcheckout/cart',
          'quickcheckout/checkout'
        );

        if (in_array($data['current_page'], $checkoutModules) && $this->cart->hasProducts() > 0) {
            $cartProducts = $this->cart->getProducts();
            $data['checkoutIds'] = "
                /* --- checkoutIds --- */
                var _ra = _ra || {};

                _ra.checkoutIdsInfo = [
            ";

            $productNo = count($cartProducts);
            foreach ($cartProducts as $item => $detail) {
                $productNo--;
                $data['checkoutIds'] .= ($productNo > 0) ? $detail['product_id'] . "," : $detail['product_id'];
            }

            $data['checkoutIds'] .= "
                ];

                if (_ra.ready !== undefined) {
                    _ra.checkoutIds(_ra.checkoutIdsInfo);
                };
            ";

            $data['js_output'] .= $data['checkoutIds'];
        }

        // saveOrder
        $orderPages = array(
            'checkout/success',
            'success'
        );

        if (in_array($data['current_page'], $orderPages)) {
            if ((isset($this->session->data['retargeting_save_order']) && !empty($this->session->data['retargeting_save_order']))) {
                $orderId = $this->session->data['retargeting_save_order'];
                $data['order_data'] = $this->model_checkout_order->getOrder($orderId);

                $orderNo            = $data['order_data']['order_id'];
                $lastName           = $data['order_data']['lastname'];
                $firstName          = $data['order_data']['firstname'];
                $email              = $data['order_data']['email'];
                $phone              = $data['order_data']['telephone'];
                $state              = $data['order_data']['shipping_country'];
                $city               = $data['order_data']['shipping_city'];
                $address            = $data['order_data']['shipping_address_1'];
                $discountCode       = isset($this->session->data['retargeting_discount_code']) ? $this->session->data['retargeting_discount_code'] : 0;
                $totalDiscountValue = 0;
                $shippingValue      = 0;
                $totalOrderValue    = $this->currency->format(
                    $data['order_data']['total'],
                    $data['order_data']['currency_code'],
                    $data['order_data']['currency_value'],
                    false
                );

                // Grab the ordered products based on order ID
                $orderProductQuery = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$orderId . "'");
                $data['order_product_query'] = $orderProductQuery;

                $data['saveOrder'] = "
                    var _ra = _ra || {};

                    _ra.saveOrderInfo = {
                        'order_no': {$orderNo},
                        'lastname': '{$lastName}',
                        'firstname': '{$firstName}',
                        'email': '{$email}',
                        'phone': '{$phone}',
                        'state': '{$state}',
                        'city': '{$city}',
                        'address': '{$address}',
                        'discount_code': '{$discountCode}',
                        'discount': {$totalDiscountValue},
                        'shipping': {$shippingValue},
                        'rebates': 0,
                        'fees': 0,
                        'total': {$totalOrderValue}
                    };
                ";

                $data['saveOrder'] .= "_ra.saveOrderProducts = [";
                for ($i = count($orderProductQuery->rows) - 1; $i >= 0; $i--) {
                      $productPrice = $this->currency->format(
                        $orderProductQuery->rows[$i]['price'] + (isset($orderProductQuery->rows[$i]['tax']) ? $orderProductQuery->rows[$i]['tax'] : 0),
                        $data['order_data']['currency_code'],
                        $data['order_data']['currency_value'],
                        false
                      );

                      if ($i == 0) {
                          $data['saveOrder'] .= "{
                              'id': {$orderProductQuery->rows[$i]['product_id']},
                              'quantity': {$orderProductQuery->rows[$i]['quantity']},
                              'price': {$productPrice},
                              'variation_code': ''
                          }";

                      break;
                      }

                      $data['saveOrder'] .= "{
                          'id': {$orderProductQuery->rows[$i]['product_id']},
                          'quantity': {$orderProductQuery->rows[$i]['quantity']},
                          'price': {$productPrice},
                          'variation_code': ''
                      },";
                }
                $data['saveOrder'] .= "];";

                $data['saveOrder'] .= "
                    if (_ra.ready !== undefined) {
                        _ra.saveOrder(_ra.saveOrderInfo, _ra.saveOrderProducts);
                    }
                ";

                $data['js_output'] .= $data['saveOrder'];

                // REST API saveOrder
                $apiKey     = $this->config->get('module_retargeting_apikey');
                $restApiKey = $this->config->get('module_retargeting_token');

                if ($restApiKey && $restApiKey != '') {
                    $orderInfo = array(
                        'order_no'      => $orderNo,
                        'lastname'      => $lastName,
                        'firstname'     => $firstName,
                        'email'         => $email,
                        'phone'         => $phone,
                        'state'         => $state,
                        'city'          => $city,
                        'address'       => $address,
                        'discount_code' => $discountCode,
                        'discount'      => $totalDiscountValue,
                        'shipping'      => $shippingValue,
                        'rebates'       => 0,
                        'fees'          => 0,
                        'total'         => $totalOrderValue
                    );

                    $orderProducts = array();

                    foreach ($orderProductQuery->rows as $orderedProduct) {
                        $orderProducts[] = array(
                            'id'             => $orderedProduct['product_id'],
                            'quantity'       => $orderedProduct['quantity'],
                            'price'          => $orderedProduct['price'],
                            'variation_code' => ''
                        );
                    }

                    $orderClient = new Retargeting_REST_API_Client($restApiKey);
                    $orderClient->setResponseFormat("json");
                    $orderClient->setDecoding(false);
                    $response = $orderClient->order->save($orderInfo, $orderProducts);
                }

                unset($this->session->data['retargeting_save_order']);
            }
        }

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/module/retargeting.twig')) {
            return $this->load->view($this->config->get('config_template') . 'extension/module/retargeting.twig', $data);
        } else {
          return $this->load->view('extension/module/retargeting', $data);
        }

    }

    /*
    * Event: post.order.add
    * Called after the order has been launched
    * @return (int) $orderId
    */
    public function eventAddOrderHistory($route, $data) {

        if (isset($data[0]) && !empty($data[0])) {
            $this->session->data['retargeting_save_order'] = (int)$data[0];
        }
    }
}
