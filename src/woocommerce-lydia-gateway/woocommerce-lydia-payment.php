<?php
/*
Plugin Name: Lydia Solutions
Plugin URI: https://lydia-app.com
Description: Lydia est un service de paiement 2 en 1. Il vous permet d'accepter les règlements par carte bancaire, en un instant, sans contacter votre banque, mais aussi d'accepter les paiements par mobile, très pratique pour vos clients.
Version: 1.2
Author: Lydia Solutions
Author URI: https://lydia-app.com/
*/

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'lydia_payment_init', 0);
function lydia_payment_init() {

	if (!class_exists('WC_Payment_Gateway')) return;

	include_once('woocommerce-lydia-payment-gateway.php');
	add_filter('woocommerce_payment_gateways', 'add_lydia_payment_gateway');
    function add_lydia_payment_gateway($methods) {
        $methods[] = 'WC_Gateway_Lydia_Payment_Gateway';
        return $methods;
	}
}


// Add custom action links
add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'lydia_payment_action_links');
function lydia_payment_action_links( $links ) {
	$plugin_links = array(
		'<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout' ).'">Activer</a>',
	);
	// Merge our new link with the default ones
	return array_merge($plugin_links, $links);
}
