<?php

/**
 * Plugin Name: Cielo eCommerce 3.0 Gateway
 * Plugin URI: https://waygex.com/cielo-ecommerce-wp-plugin
 * Description: Gateway de pagamento integrado à API Cielo eCommerce 3.0 - suporta Cartão de Crédito e Pix
 * Version: 1.1.0
 * Author: Waygex Solutions
 * Author URI: https://waygex.com
 * Text Domain: cielo-ecommerce
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * PHP Version: 8.3
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin initialization
 */
add_action('plugins_loaded', 'cielo_ecommerce_init', 11);

function cielo_ecommerce_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Load webhook routes
    // require_once plugin_dir_path(__FILE__) . 'includes/webhook/class-wc-cielo-pix-webhook.php';

    // Load payment gateway classes
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-cielo-credit-card-gateway.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-cielo-pix-gateway.php';
}

/**
 * Add Cielo payment gateways to WooCommerce
 *
 * @param array $gateways
 * @return array
 */
function add_cielo_gateway_class($gateways)
{
    // Add Credit Card gateway
    $gateways[] = 'WC_Cielo_Credit_Card_Gateway';

    // Add Pix gateway
    $gateways[] = 'WC_Cielo_Pix_Gateway';

    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'add_cielo_gateway_class');
