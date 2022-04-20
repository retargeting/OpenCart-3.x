<?php

require_once DIR_SYSTEM . 'library/retargeting/vendor/autoload.php';

/**
  *  Retargeting Tracker for OpenCart 3.x
  *  @author Carol-Theodor Pelu
  *  admin/controller/extension/module/retargeting.php
*/

class ControllerExtensionModuleRetargeting extends Controller {

    private $error = array();

    /**
     * ControllerExtensionModuleRetargeting constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
    }

    /**
     * Index method
     * @throws Exception
     */
    public function index()
    {
        if(!empty($this->missingRequirements()))
        {
            $this->uninstall();

            exit($this->missingRequirements());
        }

        $this->load->language('extension/module/retargeting');

        $this->load->model('setting/setting');
        $this->load->model('setting/event');
        $this->load->model('localisation/language');
        $this->load->model('design/layout');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['layouts']   = $this->model_design_layout->getLayouts();

        /*
         * Pull all layouts from DataBase
         */
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_retargeting', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        /* Translated strings */
        $data['heading_title']     = $this->language->get('heading_title');
        $data['text_edit']         = $this->language->get('text_edit');
        $data['text_enabled']      = $this->language->get('text_enabled');
        $data['text_disabled']     = $this->language->get('text_disabled');
        $data['text_token']        = $this->language->get('text_token');
        $data['entry_status']      = $this->language->get('entry_status');
        $data['entry_apikey']      = $this->language->get('entry_apikey');
        $data['entry_token']       = $this->language->get('entry_token');
        $data['entry_email']       = $this->language->get('entry_email');
        $data['entry_cart_button'] = $this->language->get('entry_cart_button');
        $data['entry_image']       = $this->language->get('entry_image');
        $data['button_save']       = $this->language->get('button_save');
        $data['button_cancel']     = $this->language->get('button_cancel');

        /*
         * Populate the errors array
         */
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($thi->error['code'])) {
            $data['error_code'] = $this->error['code'];
        } else {
            $data['error_code'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/retargeting', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/retargeting', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        $data['user_token'] = $this->session->data['user_token'];

        /*
         * Populate custom variables
         */
        if (isset($this->request->post['module_retargeting_status'])) {
            $data['module_retargeting_status'] = $this->request->post['module_retargeting_status'];
        } else {
            $data['module_retargeting_status'] = $this->config->get('module_retargeting_status');
        }

        if (isset($this->request->post['module_retargeting_apikey'])) {
            $data['module_retargeting_apikey'] = $this->request->post['module_retargeting_apikey'];
        } else {
            $data['module_retargeting_apikey'] = $this->config->get('module_retargeting_apikey');
        }

        if (isset($this->request->post['module_retargeting_token'])) {
            $data['module_retargeting_token'] = $this->request->post['module_retargeting_token'];
        } else {
            $data['module_retargeting_token'] = $this->config->get('module_retargeting_token');
        }

        /* 1. setEmail */
        if (isset($this->request->post['module_retargeting_setEmail'])) {
            $data['module_retargeting_setEmail'] = $this->request->post['module_retargeting_setEmail'];
        } else {
            $data['module_retargeting_setEmail'] = $this->config->get('module_retargeting_setEmail');
        }

        /* 2. addToCart */
        if (isset($this->request->post['module_retargeting_addToCart'])) {
            $data['module_retargeting_addToCart'] = $this->request->post['module_retargeting_addToCart'];
        } else {
            $data['module_retargeting_addToCart'] = $this->config->get('module_retargeting_addToCart');
        }

        /* 3. clickImage */
        if (isset($this->request->post['module_retargeting_clickImage'])) {
            $data['module_retargeting_clickImage'] = $this->request->post['module_retargeting_clickImage'];
        } else {
            $data['module_retargeting_clickImage'] = $this->config->get('module_retargeting_clickImage');
        }
        /* 4. stock */
        if (isset($this->request->post['module_retargeting_stock'])) {
            $data['module_retargeting_stock'] = $this->request->post['module_retargeting_stock'];
        } else {
            $data['module_retargeting_stock'] = $this->config->get('module_retargeting_stock');
        }
        /* 5. cron */
        if (isset($this->request->post['module_retargeting_cron'])) {
            $data['module_retargeting_cron'] = $this->request->post['module_retargeting_cron'];
        } else {
            $data['module_retargeting_cron'] = $this->config->get('module_retargeting_cron');
        }

        if ($data['module_retargeting_cron'] == 1) {
            $dir = dirname(DIR_APPLICATION);
            $data['cron'] = "<br /><b>Please make sure you have this cronJob in your Hosting CronJob List <br />
<pre style='color:red'>0 */3 * * * /usr/bin/php -q ".$dir."/index.php --csv retargeting-cron > ".$dir."/rtg.cron.log</pre></b>";
        } else {
            $data['cron'] = "";
        }

        /*
         * Common admin area items
         */
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        $data['site_url'] = $protocol.$_SERVER['HTTP_HOST'];

        $this->response->setOutput($this->load->view('extension/module/retargeting', $data));
    }

    /**
     * Install module
     * @throws Exception
     */
    public function install()
    {
        if(!empty($this->missingRequirements()))
        {
            $this->uninstall();

            exit($this->missingRequirements());
        }

        $this->load->model('setting/event');
        $this->load->model('design/layout');
        $this->load->model('setting/setting');

        foreach ($this->model_design_layout->getLayouts() as $layout) {
            $this->db->query("
                INSERT INTO " . DB_PREFIX . "layout_module SET
                    layout_id = '{$layout['layout_id']}',
                    code = 'retargeting',
                    position = 'content_bottom',
                    sort_order = '99'
                ");
        }

        $this->model_setting_event->addEvent(
            'retargeting_add_order',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/module/retargeting/eventAddOrderHistory'
        );

        $this->model_setting_event->addEvent(
            'retargeting_edit_product',
            'admin/model/catalog/product/editProduct/after',
            'extension/module/retargeting/model_edit_product_after'
        );

        $this->model_setting_event->addEvent(
            'retargeting_delete_product',
            'admin/model/catalog/product/deleteProduct/before',
            'extension/module/retargeting/model_delete_product_before'
        );
    }

    /**
     * Call stock management api to update product real time
     * @param $route
     * @param $data
     * @throws \Retargeting\Exceptions\RTGException
     */
    public function model_edit_product_after($route, $data)
    {
        if($data && isset($this->request->get['product_id']))
        {
            $product_id = $this->request->get['product_id'];
            $baseUrl = $this->getBaseUrl();
            $apiKey = $this->config->get('module_retargeting_token');

            $product_info = $this->model_catalog_product->getProduct($product_id);

            $productUrl = $this->url->link('product/product', 'product_id=' . $product_id);

            $stock = new \RetargetingSDK\Api\StockManagement();

            $stock->setProductId($product_id);
            $stock->setName($product_info['name']);
            $stock->setPrice($product_info['price']);
            $stock->setPromo(isset($product_info['special']) && $product_info['special'] > 0 ? round($product_info['special'], 2) : 0);
            $stock->setImage($baseUrl . 'image/' . $product_info['image']);
            $stock->setUrl($productUrl);

            (bool)$product_info['status'] && (int)$product_info['quantity'] > 0 ? $stock->setStock(true) : $stock->setStock(false);

            $stock->updateStock($apiKey, $stock->prepareStockInfo());
        }
    }

    /**
     * Call stock management api to update product real time
     *
     * @param $route
     * @param $data
     * @throws \Retargeting\Exceptions\RTGException
     */
    public function model_delete_product_before($route, $data)
    {
        if(!empty($data) && isset($data[0]))
        {
            $this->load->model('catalog/product');

            $product_id = (int)$data[0];
            $baseUrl = $this->getBaseUrl();
            $apiKey = $this->config->get('module_retargeting_token');

            $product_info = $this->model_catalog_product->getProduct($product_id);

            $productUrl = $this->url->link('product/product', 'product_id=' . $product_id);

            $stock = new \RetargetingSDK\Api\StockManagement();

            $stock->setProductId($product_id);
            $stock->setName($product_info['name']);
            $stock->setPrice($product_info['price']);
            $stock->setPromo(isset($product_info['special']) && $product_info['special'] > 0 ? round($product_info['special'], 2) : 0);
            $stock->setImage($baseUrl . 'image/' . $product_info['image']);
            $stock->setUrl($productUrl);

            (bool)$product_info['status'] && (int)$product_info['quantity'] > 0 ? $stock->setStock(true) : $stock->setStock(false);

            $stock->updateStock($apiKey, $stock->prepareStockInfo());
        }
    }

    /**
     * Get shop url
     * @return mixed
     */
    public function getBaseUrl()
    {
        if (isset($this->request->server['HTTPS']) && (($this->request->server['HTTPS'] == 'on') || ($this->request->server['HTTPS'] == '1'))) {
            return $this->config->get('config_ssl');
        }

        return $this->config->get('config_url');
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        $this->load->model('design/layout');
        $this->load->model('setting/setting');
        $this->load->model('setting/event');

        $this->db->query("DELETE FROM " . DB_PREFIX . "layout_module WHERE code = 'retargeting'");

        $this->model_setting_setting->deleteSetting('retargeting');

        $this->model_setting_event->deleteEventByCode('retargeting_add_order');
        $this->model_setting_event->deleteEventByCode('retargeting_edit_product');
    }

    /**
     * Validation method
     * @return bool
     */
    public function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/module/retargeting')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!isset($this->request->post['module_retargeting_apikey']) || strlen(utf8_decode($this->request->post['module_retargeting_apikey'])) < 3) {
            $this->error['warning'] = $this->language->get('error_apikey');
        }

        if (!isset($this->request->post['module_retargeting_token']) || strlen(utf8_decode($this->request->post['module_retargeting_token'])) < 3) {
            $this->error['warning'] = $this->language->get('error_token');
        }

        if (!isset($this->request->post['module_retargeting_token']) || strlen(utf8_decode($this->request->post['module_retargeting_apikey'])) < 3 &&
            !isset($this->request->post['module_retargeting_token']) || strlen(utf8_decode($this->request->post['module_retargeting_token'])) < 3) {
            $this->error['warning'] = $this->language->get('error_code');
        }

        return !$this->error;
    }

    /**
     * Check is system fit rtg requirements
     * @return string
     */
    private function missingRequirements()
    {
        $errors = [];
        $contactYourWebAdmin = " in order to function. Please contact your web server administrator for assistance.";

        /*if (true === version_compare(PHP_VERSION, '7.1', '<='))
        {
            $errors[] = 'Your PHP version is too old. The Retargeting plugin requires PHP 7.1 or higher'
                .$contactYourWebAdmin . 'Your PHP version is currently ' . PHP_VERSION . '. ';
        }*/

        if (extension_loaded('json') === false)
        {
            $errors[] = 'The Retargeting plugin requires the JSON extension for PHP'.$contactYourWebAdmin;
        }

        if (false === extension_loaded('curl'))
        {
            $errors[] = 'The Retargeting plugin requires the Curl extension for PHP'.$contactYourWebAdmin;
        }

        if (!empty($errors))
        {
            return implode("<br>\n", $errors);
        }
    }
}
