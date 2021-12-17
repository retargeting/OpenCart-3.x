<?php
/**
 * Created by PhpStorm.
 * User: bratucornel
 * Date: 2019-02-19
 * Time: 07:48
 */
namespace RetargetingSDK;

use RetargetingSDK\Helpers\BrandHelper;
use RetargetingSDK\Helpers\CategoryHelper;
use RetargetingSDK\Helpers\ProductFeedHelper;
use RetargetingSDK\Helpers\UrlHelper;
use RetargetingSDK\Helpers\VariationsHelper;

class Product extends AbstractRetargetingSDK
{
    protected $id = 0;
    protected $name = '';
    protected $url = '';
    protected $img = '';
    protected $price = '';
    protected $promo = 0;
    protected $brand = [];
    protected $category = [];
    protected $inventory = 0;
    protected $additionalImages = [];
    protected $extraData = [];

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        // $id = $this->formatIntFloatString($id);

        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $name = $this->getProperFormattedString($name);

        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param $url
     * @throws \Exception
     */
    public function setUrl($url)
    {
//        $url = UrlHelper::validate($url);

        $this->url = $url;
    }

    /**
     * @return mixed
     */
    public function getImg()
    {
        return $this->img;
    }

    /**
     * @param $img
     * @throws \Exception
     */
    public function setImg($img)
    {
        // $img = UrlHelper::validate($img);

        $this->img = $img;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param $price
     * @throws \Exception
     */
    public function setPrice($price)
    {
        $price = ProductFeedHelper::formatPrice($price);

        $this->price = $price;
    }

    /**
     * @return float
     */
    public function getPromo()
    {
        return $this->promo;
    }

    /**
     * @param $promo
     * @throws \Exception
     */
    public function setPromo($promo)
    {

        $this->promo = $promo;
    }

    /**
     * @return array
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * @param string $brand
     */
    public function setBrand($brand)
    {
        $brand = BrandHelper::validate($brand);

        $this->brand = isset($brand['name']) ? $brand['name'] : '';
    }

    /**
     * @return array
     */
    public function getCategory($retrunName = false)
    {
        if($retrunName) {
            return $this->category[0]['name'];
        }
        return $this->category;
    }

    /**
     * @param array $category
     */
    public function setCategory($category)
    {
        $category   = CategoryHelper::validate($category);

        $this->category = isset($category) ? $category : '';
    }

    /**
     * @return integer
     */
    public function getInventory()
    {
        return $this->inventory;
    }

    /**
     * @param array $inventory
     */
    public function setInventory($inventory)
    {
//        $inventory  = VariationsHelper::validate($inventory);

        $this->inventory = $inventory;
    }

    /**
     * @return array
     */
    public function getAdditionalImages()
    {
        return $this->additionalImages;
    }

    /**
     * @param array $additionalImages
     */
    public function setAdditionalImages($additionalImages)
    {
        $additionalImages = $this->validateArrayData($additionalImages);

        $this->additionalImages = $additionalImages;
    }

    /**
     * @param $extraData array
     */
    public function setExtraData($extraData) {
        $this->extraData = $extraData;
    }

    public function getExtraData() {
        return $this->extraData;
    }

    /**
     * @param bool $encoded
     * @return array|string
     */
    public function getData($feed = false, $encoded = true)
    {
        if ($feed) {

            $product = [
                'product id'        => $this->getId(),
                'product name'      => $this->getName(),
                'product url'       => $this->getUrl(),
                'image url'         => $this->getImg(),
                'stock'             => $this->getInventory(),
                'price'             => $this->getPrice(),
                'sale price'        => $this->getPromo(),
                'brand'             => $this->getBrand(),
                'category'          => $this->getCategory(true),
                'extra data'        => json_encode($this->getExtraData()),
            ];

        } else {

            $product = [
                'id'        => $this->getId(),
                'name'      => $this->getName(),
                'url'       => $this->getUrl(),
                'img'       => $this->getImg(),
                'price'     => $this->getPrice(),
                'promo'     => $this->getPromo(),
                'brand'     => $this->getBrand(),
                'category'  => $this->getCategory(),
                'inventory' => $this->getInventory()
            ];

        }

        return $encoded ? $this->toJSON($product) : $product;
    }
}
