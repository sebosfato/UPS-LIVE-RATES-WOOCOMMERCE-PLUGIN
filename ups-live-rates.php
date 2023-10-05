<?php
/**
Plugin Name: UPS Live Rates
Description: Fetch live shipping rates from UPS.
Version: 1.0
Author: Your Name
 *
 * @package Webkul WooCommerce Shipping
 */

/*----- Preventing Direct Access -----*/
defined( 'ABSPATH' ) || exit;

/**
 * Include your shipping file.
 */
function ups_include_shipping_method() {
  require_once 'class-ups-shipping-method.php';
}
add_action( 'woocommerce_shipping_init', 'ups_include_shipping_method' );

/**
 * Add Your shipping method class in the shipping list
 */
function add_ups_shipping_method($methods) {
    $methods['ups_shipping'] = 'UPS_Shipping_Method';
    return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'add_ups_shipping_method' );

