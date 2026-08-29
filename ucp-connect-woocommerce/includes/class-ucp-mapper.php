<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP Data Mapper.
 * Maps WooCommerce objects to UCP schemas.
 */
class UCP_Mapper
{

    /**
     * Map a WC_Product to a UCP Item.
     *
     * @param WC_Product $product WooCommerce product.
     * @return array UCP Item schema.
     */
    public function map_product_to_item($product)
    {
        return array(
            'id' => (string) $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'sku' => $product->get_sku(),
            'url' => $product->get_permalink(),
            'price' => array(
                'value' => (float) $product->get_price(),
                'currency' => get_woocommerce_currency(),
            ),
            'isOnSale' => $product->is_on_sale(),
            'regularPrice' => $product->is_on_sale() ? (float) $product->get_regular_price() : null,
            'images' => $this->get_images($product),
            'availability' => $product->is_in_stock() ? 'IN_STOCK' : 'OUT_OF_STOCK',
            'attributes' => $this->get_attributes($product),
            'dimensions' => $this->get_dimensions($product),
            'weight' => $product->get_weight(),
            'type' => $product->get_type(),
        );
    }

    /**
     * Get all product images (main + gallery).
     *
     * @param WC_Product $product
     * @return array List of image URLs.
     */
    private function get_images($product)
    {
        $images = array();

        // Main Image
        $main_image_id = $product->get_image_id();
        if ($main_image_id) {
            $url = wp_get_attachment_url($main_image_id);
            if ($url)
                $images[] = $url;
        }

        // Gallery Images
        $attachment_ids = $product->get_gallery_image_ids();
        if ($attachment_ids) {
            foreach ($attachment_ids as $attachment_id) {
                $url = wp_get_attachment_url($attachment_id);
                if ($url)
                    $images[] = $url;
            }
        }

        return $images;
    }

    /**
     * Get product attributes.
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_attributes($product)
    {
        $attributes = array();
        foreach ($product->get_attributes() as $attribute) {
            $name = $attribute->get_name();
            // Handle taxonomy-based attributes vs custom text attributes
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                $attributes[$name] = $terms;
            } else {
                $attributes[$name] = $attribute->get_options();
            }
        }
        return $attributes;
    }

    /**
     * Get product dimensions formatted.
     *
     * @param WC_Product $product
     * @return array
     */
    private function get_dimensions($product)
    {
        if (!$product->has_dimensions()) {
            return array();
        }
        return array(
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'unit' => get_option('woocommerce_dimension_unit'),
        );
    }
}
