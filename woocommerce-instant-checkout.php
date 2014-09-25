<?php

/**
 * Plugin Name: Woocommerce: Instant Checkout
 * Plugin URI: 
 * Description: Set a product to go directly to checkout without hitting the cart, via redirect or modal
 * Version: 1.0.0
 * Author: Timothy Wood (@codearachnid)
 * Author URI: http://www.codearachnid.com
 * Author Email: tim@imaginesimplicity.com
 * Text Domain: 'woocommerce_ic'
 *
 * License:
 *
 *     Copyright 2013 Imagine Simplicity (tim@imaginesimplicity.com)
 *     License: GNU General Public License v3.0
 *     License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author codearachnid
 *
*/

if ( !defined( 'ABSPATH' ) )
	die( '-1' );


if ( !class_exists( 'woocommerce_instant_checkout' ) ) {
	class woocommerce_instant_checkout {

		private static $_this;
		public $path;
		public $meta_key = '_instant_checkout';
		const MIN_WP_VERSION = '3.7';

		function __construct() {

			$this->path = trailingslashit( dirname( __FILE__ ) );

			add_action( 'init', array( $this, 'init' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'woocommerce_process_product_meta' ), 10, 2 );
			add_filter( 'product_type_options', array( $this, 'product_type_options' ) );

		}

		function init() {
			// echo 'woo ic init';
		}

		function woocommerce_process_product_meta( $post_id, $post ){
			$is_instant_checkout = isset( $_POST[ $this->meta_key ] ) ? 'yes' : 'no';
			update_post_meta( $post_id, $this->meta_key, $is_instant_checkout );
		}
		/**
		 * adds instant checkout option to product_type_options filter
		 * 
		 * @hook product_type_options
		 * @param  array $pto
		 * @return array $pto
		 */
		function product_type_options( $pto ){
			$pto['instant_checkout'] = array(
						'id'            => $this->meta_key,
						'wrapper_class' => 'show_if_simple show_if_grouped',
						'label'         => __( 'Instant Checkout', 'woocommerce_ic' ),
						'description'   => __( 'Instant checkout will add products to cart and instantly provide user checkout methods.', 'woocommerce_ic' ),
						'default'       => 'no'
					);
			return $pto;
		}


		/**
		 * Check the minimum WP version
		 *
		 * @static
		 * @return bool Whether the test passed
		 */
		public static function prerequisites() {;
			$pass = TRUE;
			$pass = $pass && version_compare( get_bloginfo( 'version' ), self::MIN_WP_VERSION, '>=' );
			$pass = $pass && class_exists( 'WooCommerce' );
			return $pass;
		}

		/**
		 * Display fail notices
		 *
		 * @static
		 * @return void
		 */
		public static function fail_notices() {
			printf( '<div class="error"><p>%s</p></div>',
				sprintf( __( 'Woocommerce: Instant Checkout requires WooCommerce and WordPress v%s or higher.', 'woocommerce_ic' ),
					self::MIN_WP_VERSION
				) );
		}

		/**
		 * Static Singleton Factory Method
		 *
		 * @return static $_this instance
		 * @readlink http://eamann.com/tech/the-case-for-singletons/
		 */
		public static function instance() {
			if ( !isset( self::$_this ) ) {
				$className = __CLASS__;
				self::$_this = new $className;
			}
			return self::$_this;
		}
	}

	/**
	 * Instantiate class and set up WordPress actions.
	 *
	 * @return void
	 */
	function load_woocommerce_instant_checkout() {

		// we assume class_exists( 'WPPluginFramework' ) is true
		if ( apply_filters( 'load_woocommerce_instant_checkout/pre_check', woocommerce_instant_checkout::prerequisites() ) ) {

			// when plugin is activated let's load the instance to get the ball rolling
			add_action( 'init', array( 'woocommerce_instant_checkout', 'instance' ), -100, 0 );

		} else {

			// let the user know prerequisites weren't met
			add_action( 'admin_head', array( 'woocommerce_instant_checkout', 'fail_notices' ), 0, 0 );

		}
	}

	// high priority so that it's not too late for addon overrides
	add_action( 'plugins_loaded', 'load_woocommerce_instant_checkout' );

}