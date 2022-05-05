<?php

namespace RetargetingSDK\Javascript\Item;

/**
 * Class CartAdd
 * @package RetargetingSDK\Javascript\Item
 */
class CartAdd extends AbstractItem
{
    /**
     * CartAdd constructor.
     * @param $productId
     * @param $quantity
     * @param array $variation
     */
    public function __construct($productId, $quantity, array $variation)
    {
        $addToCart = [
            'product_id' => $productId,
            'quantity'   => $quantity,
            'variation'  => !empty($variation) ? $variation : false
        ];

        $this->setParams('_ra.addToCartInfo = ' . json_encode($addToCart) . ';');
        $this->setMethod('_ra.addToCart(_ra.addToCartInfo.product_id, _ra.addToCartInfo.quantity, _ra.addToCartInfo.variation);');
    }
}