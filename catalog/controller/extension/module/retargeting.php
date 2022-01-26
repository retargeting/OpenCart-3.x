<?php
/*
if(isset($_GET['csv']) && $_GET['csv'] === 'retargeting')
{
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
*/
use RetargetingSDK\Helpers\UrlHelper;

$lim = (substr(DIR_SYSTEM, -1) === '/' ? '' : '/');

require_once 'retargetingconfigs.php';
require_once 'retargetingjs.php';
require_once DIR_SYSTEM . $lim .
    'library/retargeting/vendor/autoload.php';

/*
 * Retargeting Tracker for OpenCart 3.x
 */
class ControllerExtensionModuleRetargeting extends Controller
{

    /**
     * @return mixed
     * @throws Exception
     */
    public function index()
    {
        //Get configs
        $data = (new Configs($this))->getConfigs();

        if (isset($_GET))
        {
            //Products Feed
            if(isset($_GET['csv']) && $_GET['csv'] === 'retargeting')
            {
                $start = isset($_GET['start']) ? $_GET['start'] : 0;
                $limit = isset($_GET['limit']) ? $_GET['limit'] : 250;

                $this->getProductsFeed($start, $limit);
            }

            //Plugin Version
            if (isset($_GET['json']) && $_GET['json'] === 'version')
            {
                header('Content-Type: application/json');

                if(VERSION)
                {
                    echo json_encode([ 'data' => [
                        'version' => VERSION
                    ]], JSON_PRETTY_PRINT);

                    die();
                }
            }
        }

        /**
         * ---------------------------------------------------------------------------------------------------------------------
         *
         * API poach && Discount codes generator
         *
         * ---------------------------------------------------------------------------------------------------------------------
         *
         *
         * ********
         * REQUEST:
         * ********
         * POST : key​=your_retargeting_key
         * GET : type​=0​&value​=30​&count​=3
         * * type => (Integer) 0​: Fixed; 1​: Percentage; 2​: Free Delivery;
         * * value => (Float) actual value of discount
         * * count => (Integer) number of discounts codes to be generated
         *
         *
         * *********
         * RESPONDS:
         * *********
         * json with the discount codes
         * * ['code1', 'code2', ... 'codeN']
         *
         *
         * STEP 1: check $_POST
         * STEP 2: add the discount codes to the local database
         * STEP 3: expose the codes to Retargeting
         * STEP 4: kill the script
         */
        if (isset($_GET) && isset($_GET['key']) && ($_GET['key'] === $data['api_key_field']))
        {
            $this->getGeneratedCodes();
        }

        // Helpers
        $data['cart_products']    = isset($this->session->data['cart']) ? $this->session->data['cart'] : false;
        $data['wishlist']         = !empty($this->session->data['wishlist']) ? $this->session->data['wishlist'] : false;

        // Recommendation engine
        $data['rec_eng_output'] = $this->getRecommendationEngineOutput();

        if (isset($this->session->data['order_id'])) {
            setcookie("retargeting_save_order", $this->session->data['order_id'], time()+3600);
        }
        
        //Populating JS
        $data['js_output']        = (
            new JS($this,
                $this->getCurrentPage(),
                $this->getCurrentCategory(),
                $this->getManufacturedId(),
                $this->getProductId()
            )
        )->getMainJs();

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/module/retargeting.twig'))
        {
            return $this->load->view($this->config->get('config_template') . 'extension/module/retargeting.twig', $data);
        }
        else {
            return $this->load->view('extension/module/retargeting', $data);
        }
    }

    /**
     * Event: post.order.add
     * Called after the order has been launched
     * @param $route
     * @param $data
     */
    public function eventAddOrderHistory($route, $data)
    {
        if (isset($data[0]) && !empty($data[0]))
        {
            $this->session->data['retargeting_save_order'] = (int)$data[0];
        }
    }

    /**
     * Get current page
     * @return bool
     */
    public function getCurrentPage()
    {
        if(isset($this->request->get['route']))
        {
            return $this->request->get['route'];
        }

        return false;
    }

    /**
     * Get current category
     * @return string|array
     */
    public function getCurrentCategory()
    {
        if(!empty($this->request->get['path']) && is_array($this->request->get['path']))
        {
            return explode('_', $this->request->get['path']);
        }
        else if(!empty($this->request->get['path']) )
        {
            return explode('_', $this->request->get['path']);
        }

        return '';
    }

    /**
     * Get product id from request
     * @return string
     */
    public function getProductId()
    {
        return isset($this->request->get['product_id']) ? $this->request->get['product_id'] : '';
    }

    /**
     * Get manufactured id
     * @return string
     */
    public function getManufacturedId()
    {
        return isset($this->request->get['manufacturer_id']) ? $this->request->get['manufacturer_id'] : '';
    }

    /**
     * Get product price
     * @param $price
     * @param $taxClassId
     * @return float
     */
    public function getProductPrice($price, $taxClassId)
    {
        return number_format($this->tax->calculate(
            $price,
            $taxClassId,
            $this->config->get('config_tax')
        ), 2, '.', '');
    }

    /**
     * Get products feed
     * @param $start
     * @param $limit
     * @throws Exception
     */
    public function getProductsFeed($start, $limit)
    {
        header("Content-Disposition: attachment; filename=retargeting.csv");
        header("Content-type: text/csv; charset=utf-8");
        
        ini_set("display_errors", "on");
        error_reporting(E_ALL); 

        $defStock = empty($this->config->get('module_retargeting_stock')) ? 0 : $this->config->get('module_retargeting_stock');

        $params = [
            'start' => $start,
            'limit' => $limit,
            'filter_status' => 1
        ];

        $baseUrl = (new Configs($this))->getBaseUrl();

        $productsLoop = true;

        $outstream = fopen('php://output', 'w');

        fputcsv($outstream, [
            'product id',
            'product name',
            'product url',
            'image url',
            'stock',
            'price',
            'sale price',
            'brand',
            'category',
            'extra data'
        ], ',', '"');

        while($productsLoop) {

            $products = $this->model_catalog_product->getProducts($params);

            if(empty($products)) { // || $params['start'] > 500
                $productsLoop = false;
                break;
            }

            foreach ($products as $key => $product) {

                $productPrice = $product['price'];// \RetargetingSDK\Helpers\ProductFeedHelper::formatPrice();
                $productSpecialPrice = isset($product['special']) ? $product['special'] : 0; //\RetargetingSDK\Helpers\ProductFeedHelper::formatPrice() 
                
                $productPrice = $this->getProductPrice($productPrice, $product['tax_class_id']);
                
                //$productPrice = round($productPrice, 2);

                if ((int) $productSpecialPrice == 0) {
                    $productSpecialPrice = $productPrice;
                } else {
                    
                    $productSpecialPrice = $this->getProductPrice($productSpecialPrice, $product['tax_class_id']);
                }

                $productUrl = $this->url->link('product/product', 'product_id=' . $product['product_id']);

                $productCategoryTree = (new JS($this,
                    $this->getCurrentPage(),
                    $this->getCurrentCategory(),
                    $this->getManufacturedId(),
                    $this->getProductId()))->getProductCategoriesForFeed((int)$product['product_id']);

                $product['quantity'] = $product['quantity'] < 0 ? $defStock : $product['quantity'];

                if ($product['quantity'] == 0 || $productPrice == 0 || empty($productCategoryTree) || $productCategoryTree[0]['name'] === null
                ) {
                    continue;
                }


                $productAdditionalImages = (new JS($this,
                    $this->getCurrentPage(),
                    $this->getCurrentCategory(),
                    $this->getManufacturedId(),
                    $this->getProductId()))->getProductImages((int)$product['product_id'], $baseUrl);

                $extraData = [
                    'media_gallery' => [],
                    'variations' => [],
                    'categories' => []
                ];

                $productCategories = $this->model_catalog_product->getCategories($product['product_id']);

                foreach ($productCategories as $category) {

                    $fullCategory = $this->model_catalog_category->getCategory($category['category_id']);
                    $extraData['categories'][$category['category_id']] = $fullCategory['name'];
                }

                $productImages = $this->model_catalog_product->getProductImages($product['product_id']);

                foreach ($productImages as $image) {

                    $extraData['media_gallery'][] = $this->config->get('config_url') . 'image/' . str_replace(' ', '%20', $image['image']);
                }

                if (!empty($product['image'])) {
                    $productImage = $baseUrl . 'image/' . $product['image'];
                } else if (!empty($this->config->get('config_logo'))) {
                    $productImage = $this->config->get('config_url') . 'image/' . $this->config->get('config_logo');
                } else {
                    $productImage = $this->config->get('config_url') . 'image/no_image-40x40.png';
                }

                $price = $productPrice;
                $promoPrice = $productSpecialPrice > 0 ? $productSpecialPrice : $price;

                $options = $this->model_catalog_product->getProductOptions($product['product_id']);

                foreach($options as $optionValue) {

                    foreach ($optionValue['product_option_value'] as $option) {

                        if (empty($option['price'])) {
                            continue;
                        }

                        $extraData['variations'][] = [
                            'code' => $option['name'],
                            'price' => $option['price_prefix'] === '+' ? $price + $option['price'] : $price - $option['price'],
                            'sale_price' => $option['price_prefix'] === '+' ? $promoPrice + $option['price'] : $promoPrice - $option['price'],
                            'stock' => $option['quantity'] < 0 ? $defStock : $option['quantity']
                        ];

                    }

                }

                $extraData = [
                    'media_gallery' => [],
                    'variations' => [],
                    'categories' => []
                ];

                $productCategories = $this->model_catalog_product->getCategories($product['product_id']);

                foreach ($productCategories as $category) {

                    $fullCategory = $this->model_catalog_category->getCategory($category['category_id']);
                    $extraData['categories'][$fullCategory['category_id']] = $fullCategory['name'];
                }

                $productImages = $this->model_catalog_product->getProductImages($product['product_id']);

                foreach ($productImages as $image) {
                    $extraData['media_gallery'][] = $this->config->get('config_url') . 'image/' . str_replace(' ', '%20', $image['image']);
                }

                //$price = number_format($productPrice, 2, '.', '');
                //$promoPrice = $productSpecialPrice > 0 ? number_format($productSpecialPrice, 2, '.', '') : $price;

                $options = $this->model_catalog_product->getProductOptions($product['product_id']);

                foreach($options as $optionValue) {

                    foreach ($optionValue['product_option_value'] as $option) {

                        if (empty($option['price'])) {
                            continue;
                        }

                        $extraData['variations'][] = [
                            'code' => $option['name'],
                            'price' => $option['price_prefix'] === '+' ? $price + $option['price'] : $price - $option['price'],
                            'sale_price' => $option['price_prefix'] === '+' ? $promoPrice + $option['price'] : $promoPrice - $option['price'],
                            'stock' => $option['quantity'] < 0 ? $defStock : $option['quantity']
                        ];

                    }

                }

                $setupProduct =  new \RetargetingSDK\Product();
                $setupProduct->setId($product['product_id']);
                $setupProduct->setName($product['name']);
                $setupProduct->setUrl( $this->fixURL($productUrl) );
                $setupProduct->setImg( $this->fixURL($productImage) );
                $setupProduct->setPrice($price);
                $setupProduct->setPromo($promoPrice);
                $setupProduct->setBrand(\RetargetingSDK\Helpers\BrandHelper::validate([
                    'id'    => $product['manufacturer_id'],
                    'name'  => $product['manufacturer']
                ]));
                $setupProduct->setCategory($productCategoryTree);
                $setupProduct->setInventory($product['quantity']);
                $setupProduct->setAdditionalImages($productAdditionalImages);
                $setupProduct->setExtraData($extraData);

                fputcsv($outstream, $setupProduct->getData(true, false), ',', '"');

            }

            $params['start'] += $params['limit'];

        }

        fclose($outstream);
        die;

    }
    
    private $checkHTTP = null;

    public function fixURL($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) && !strpos($url, "%20")) {
            $new_URL = explode("?", $url, 2);
            $newURL = explode("/",$new_URL[0]);
    
            if ($this->checkHTTP === null) {
                $this->checkHTTP = !empty(array_intersect(["https:","http:"], $newURL));
            } 
            
            foreach ($newURL as $k=>$v ){
                if (!$this->checkHTTP || $this->checkHTTP && $k > 2) {
                    $newURL[$k] = rawurlencode($v);
                }
            }
    
            if (isset($new_URL[1])) {
                $new_URL[0] = implode("/",$newURL);
                $new_URL[1] = str_replace("&amp;","&",$new_URL[1]);
                return implode("?", $new_URL);
            } else {
                return implode("/",$newURL);
            }
        }
        return $url;
    }
    
    /**
     * Generate a random discount code
     * @return string
     */
    public function generateRandomCode()
    {
        return substr(
                str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 1) .
            substr(str_shuffle('AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz'), 0, 9);
    }

    /**
     * Get generated codes
     * @return false|string
     * @throws Exception
     */
    public function getGeneratedCodes()
    {
        $discountType  = (isset($_GET['type'])) ? (filter_var($_GET['type'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';
        $discountValue = (isset($_GET['value'])) ? (filter_var($_GET['value'], FILTER_SANITIZE_NUMBER_FLOAT)) : 'Received other than float';
        $discountCodes = (isset($_GET['count'])) ? (filter_var($_GET['count'], FILTER_SANITIZE_NUMBER_INT)) : 'Received other than int';

        $dateTime = new DateTime();
        $startDate = $dateTime->format('Y-m-d');
        $dateTime->modify('+6 months');

        for ($i = $discountCodes; $i > 0; $i--)
        {
            $code = $this->generateRandomCode();

            $discountCodesCollection[] = $code;

            // Discount type: Fixed Value
            if ($discountType == 0)
            {
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
        }

        if (!empty($discountCodesCollection))
        {
            header('Content-Type: application/json');

            echo json_encode($discountCodesCollection);

            die();
        }
    }

    /**
     * @return string
     */
    private function getRecommendationEngineOutput()
    {
        $page      = $this->getCurrentPage();
        $recEngine = new \RetargetingSDK\RecommendationEngine();

        switch ($page)
        {
            case 'product/category':
                $recEngine->markCategoryPage();
                break;
            case 'product/product':
                $recEngine->markProductPage();
                break;
            case in_array($page, JS::CHECKOUT_MODULES):
                $recEngine->markCheckoutPage();
                break;
            case in_array($page, JS::ORDER_PAGES):
                $recEngine->markThankYouPage();
                break;
        }

        return $recEngine->generateTags();
    }

    /**
     * @param array $params
     * @return array
     */
    private function getExtraData($params = []) {

        return [
          'margin' => null,
          'categories' => $this->refactorCategories($params['categories']),
          'media_gallery' => $this->getImagesOfProduct($params['product_id'], $params['base_url']),
          'variations' => $this->refactorVariations($params['variations'], $params['price'], $params['promoPrice']),
          'in_supplier_stock' => null
        ];


    }

    public function refactorVariations($options, $price = 0, $promoPrice = 0) {
        $variations = [];

        foreach($options as $optionValue) {

            foreach ($optionValue['product_option_value'] as $option) {

                if (empty($option['price'])) {
                    continue;
                }

                $newPrice = number_format($option['price_prefix'] === '+' ? $price + $option['price'] : $price - $option['price'], 2, '.', '');
                $newSalePrice = number_format($option['price_prefix'] === '+' ? $promoPrice + $option['price'] : $promoPrice - $option['price'], 2, '.', '');
                
                $variations[] = [
                    'code' => $option['name'],
                    'price' => $newPrice,
                    'sale_price' => $newSalePrice,
                    'stock' => $option['quantity']
                ];

            }

        }
        return $variations; 
    }

    /**
     * @param $categories
     */
    public function refactorCategories($categories) {

        $reCategories = [];
        foreach ($categories as $category) {

            $catalogCategory = $this->model_catalog_category->getCategory($category['category_id']);

            if (!isset($catalogCategory['name']) || empty($catalogCategory['name'])) {
                continue;
            }
            $reCategories[$category['category_id']] = $catalogCategory['name'];

        }

        return $reCategories;

    }

    /**
     * @param $product_id
     * @param $base_url
     * @return array
     */
    public function getImagesOfProduct($product_id, $base_url) {

        $images = $this->model_catalog_product->getProductImages($product_id);

        $extraData = [];
        foreach ($images as $image) {
            $extraData[] = $this->fixURL($base_url . 'image/' . $image['image']);
        }
        return $extraData;
    }

}
