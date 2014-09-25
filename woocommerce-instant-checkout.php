<?php

/**
 * Plugin Name: Woocommerce: Instant Checkout
 * Plugin URI: 
 * Description: Set a product to go directly to checkout without hitting the cart, via redirect or modal
 * Version: 1.0.0
 * Author: Timothy Wood (@codearachnid)
 * Author URI: http://www.codearachnid.com
 * Author Email: tim@imaginesimplicity.com
 * Text Domain: 'woocommerce_instant_checkout'
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
		public $plugin_key = 'instant_checkout';
		public $meta_key = '_instant_checkout';
		const MIN_WP_VERSION = '3.7';

		function __construct() {

			$this->path = trailingslashit( dirname( __FILE__ ) );

			// action hooks
			add_action( 'init', array( $this, 'init' ) );
			add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_columns' ), 2 );
			add_action( 'woocommerce_process_product_meta', array( $this, 'wc_process_product_meta' ), 10, 2 );
			add_action( 'template_redirect', array( $this, 'redirect_to_checkout' ), 100 );

			// filter hooks
			add_filter( 'parse_query', array( $this, 'product_filters_query' ), 30 );
			add_filter( 'product_type_options', array( $this, 'product_type_options' ) );
			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_text' ) );
			add_filter( 'woocommerce_product_filters', array( $this, 'wc_product_filters' ) );

		}

		function init() {
			// echo 'woo ic init';
		}

		function wc_process_product_meta( $post_id, $post ){
			$is_instant_checkout = isset( $_POST[ $this->meta_key ] ) ? 'yes' : 'no';
			update_post_meta( $post_id, $this->meta_key, $is_instant_checkout );
		}

		function wc_product_filters( $filters_html ){
			global $wp_query;
			preg_match_all( '@(<option\svalue="([^"]*)"(.*?)>([^<]+)<\/option>)@', $filters_html, $options);

			$is_selected = isset( $wp_query->query['product_type'] ) ? selected( $this->plugin_key, $wp_query->query['product_type'], false ) : '';

			array_splice( $options[0], 
						  array_search( 'virtual', $options[2] ) + 1, 
						  0, 
						  sprintf( '<option value="%s" %s>%s</option>', $this->plugin_key, $is_selected, __( ' &rarr; Instant Checkout', 'woocommerce_instant_checkout' ) ) );

			$filters_html = sprintf( '<select name="product_type" id="dropdown_product_type">%s</select>', implode( '', $options[0] ) );
			return $filters_html;
		}

		function redirect_to_checkout() {
			global $post;

			if ( !empty( $_REQUEST['add-to-cart'] ) && 
				  is_numeric( $_REQUEST['add-to-cart'] ) &&
				  $this->is_instant_checkout( $post->ID ) ) {
					wc_clear_notices();
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit;
			}

		}

		function add_to_cart_text( $label ){
			global $post;
			if( $this->is_instant_checkout( $post->ID ) ){
				// TODO customizable label
				$label = __( 'Buy Now' );
			}
			return $label;
		}

		/**
		 * adds instant checkout option to product_type_options filter
		 * 
		 * @hook product_type_options
		 * @param  array $pto
		 * @return array $pto
		 */
		function product_type_options( $pto ){
			$pto[ $this->plugin_key ] = array(
						'id'            => $this->meta_key,
						'wrapper_class' => 'show_if_simple show_if_grouped',
						'label'         => __( 'Instant Checkout', 'woocommerce_instant_checkout' ),
						'description'   => __( 'Instant checkout will add products to cart and instantly provide user checkout methods.', 'woocommerce_instant_checkout' ),
						'default'       => 'no'
					);
			return $pto;
		}

		public function product_filters_query( $query ){
			global $typenow, $wp_query;

			if ( 'product' == $typenow && 
				  isset( $query->query_vars['product_type'] ) && 
				 $this->plugin_key == $query->query_vars['product_type'] ) {
					$query->query_vars['product_type']  = '';
					$query->query_vars['meta_value']    = 'yes';
					$query->query_vars['meta_key']      = $this->meta_key;
			}
		}

		public function is_instant_checkout( $post_id ){
			$is_instant_checkout = update_post_meta( $post_id, $this->meta_key, true );
			return $is_instant_checkout == 'yes';
		}

		public function render_product_columns( $column ) {
			global $post;
			if ( empty( $the_product ) || $the_product->id != $post->ID ) {
				$the_product = wc_get_product( $post );
			}

			if( 'product_type' == $column &&
				( 'simple' == $the_product->product_type &&
				  'grouped' == $the_product->product_type ) &&
				$this->is_instant_checkout( $post->ID ) ){

				echo '<span class="product-type tips instant_checkout" data-tip="' . __( 'Instant Checkout', 'woocommerce_instant_checkout' ) . '"></span>';
		
			}
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
				sprintf( __( 'Woocommerce: Instant Checkout requires WooCommerce and WordPress v%s or higher.', 'woocommerce_instant_checkout' ),
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