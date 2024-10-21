<?php
/*
 * Plugin Name: WooCommerce PayXpert Gateway
 * Plugin URI: http://www.payxpert.com
 * Description: WooCommerce PayXpert Gateway plugin
 * Version: 1.2.0
 * Author: PayXpert
 * Author URI: http://www.payxpert.com
 */

/**
 * PayXpert Standard Payment Gateway Library 
 *
 * Provides a PayXpert Standard Payment Gateway.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define the plugin path
if ( ! defined( 'PX_ABS' ) ) {
	define( 'PX_ABS', plugin_dir_path( __FILE__ ) );
}

// Init Gateways class
require_once PX_ABS."/includes/class-wc-init.php";

