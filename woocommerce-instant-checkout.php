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
		public $button_label;
		public $modal_title;
		public $path;
		public $url;
		public $action;
		public $plugin_key = 'instant_checkout';
		public $meta_key = '_instant_checkout';
		const MIN_WP_VERSION = '3.7';

		function __construct() {

			$this->path = plugin_dir_path( __FILE__ );
			$this->url = plugin_dir_url( __FILE__ );
			$this->action = get_option('woocommerce_instant_checkout_action');
			if( empty( $this->action ) )
				$this->action = 'redirect';
			$this->button_label = get_option('woocommerce_instant_checkout_button_label');
			if( empty( $this->button_label ) )
				$this->button_label = __( 'Buy Now', 'woocommerce_instant_checkout' );
			if( empty( $this->modal_title ) )
				$this->modal_title = __( 'Checkout', 'woocommerce_instant_checkout' );
			

			// action hooks
			add_action( 'init', array( $this, 'init' ) );
			add_filter( 'wp_footer', array( $this, 'wp_footer' ) );
			add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_columns' ), 2 );
			add_action( 'woocommerce_process_product_meta', array( $this, 'wc_process_product_meta' ), 10, 2 );
			add_action( 'template_redirect', array( $this, 'redirect_to_checkout' ), 100 );

			// filter hooks
			add_filter( 'parse_query', array( $this, 'product_filters_query' ), 30 );
			add_filter( 'body_class', array( $this, 'body_class' ) );
			add_filter( 'product_type_options', array( $this, 'product_type_options' ) );
			add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'add_to_cart_text' ) );
			add_filter( 'woocommerce_product_filters', array( $this, 'wc_product_filters' ) );
			add_filter( 'woocommerce_payment_gateways_settings', array( $this, 'wc_payment_gateways_settings' ) );

		}

		function init() {
			// echo 'woo ic init';
		}

		function body_class( $classes ){
			global $post;
			if( $this->is_instant_checkout( $post->ID ) ){
				$classes[] = $this->plugin_key;

				if( $this->action == 'modal' )
					$classes[] = 'instant_checkout_modal';
			}
			return $classes;
		}

		function wp_footer(){
			?>
			<div id="instant_checkout_modal">
				<h1>
					<span class="title"><?php echo $this->modal_title; ?></span>
					<span class="close"></span>
				</h1>
				<div class="checkout">
					<?php echo do_shortcode('[woocommerce_checkout]'); ?>
				</div>
			</div>
			<?php
		}

		function wc_payment_gateways_settings( $settings ){
			$insert_at = 0;
			foreach( $settings as $key => $setting ){
				if( $setting['type'] == 'sectionend' && $setting['id'] == 'checkout_process_options' )
					$insert_at = $key;
			}
			array_splice( $settings, 
						  $insert_at, 
						  0, 
						  array(
						  	array(
							  	'title'         => __( 'Instant Checkout', 'woocommerce_instant_checkout' ),
								'desc'          => __( 'Set the way a user will be able to checkout when adding a product enabled for instant checkout to the cart. If you select the option to display a modal on the product page it is suggested that you also require "Force secure checkout" in order to protect your customer\'s private data.', 'woocommerce_instant_checkout' ),
								// 'desc_tip'      => __( 'Allows customers to checkout without creating an account.', 'woocommerce_instant_checkout' ),
								'id'            => 'woocommerce_instant_checkout_action',
								'default'       => 'redirect',
								'type'          => 'radio',
								'options'       => array(
									'redirect'  => __( 'Redirect to checkout', 'woocommerce_instant_checkout' ),
									'modal'     => __( 'Display a popup modal for the checkout', 'woocommerce_instant_checkout' ),
								),
								// 'desc_tip'      =>  true,
								'autoload'      => false
							),
							array(
								'desc'     => __( '"Buy Now" button label for product', 'woocommerce_instant_checkout' ),
								'id'       => 'woocommerce_instant_checkout_button_label',
								'type'     => 'text',
								'default'  => $this->button_label,
							),
			  	));
			// echo '<pre>';
			// print_r($settings);
			// echo '</pre>';
			return $settings;
		}

		function wc_process_product_meta( $post_id, $post ){
			update_post_meta( 
				$post_id, 
				$this->meta_key, 
				isset( $_POST[ $this->meta_key ] ) ? 'yes' : 'no' 
			);
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

			if( is_admin() )
				return;

			if( $this->is_instant_checkout( $post->ID ) ) {

				if ( !empty( $_REQUEST['add-to-cart'] ) && 
					is_numeric( $_REQUEST['add-to-cart'] ) &&
					$this->action == 'redirect' ) {
					// echo 'test redirect success';
					wc_clear_notices();
					wp_safe_redirect( WC()->cart->get_checkout_url() );
					exit;
				}

				if( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ){
					
					$goto = str_replace( 'http://', 'https://', get_permalink( $post->ID ) );
					if( !empty( $_GET ) ) {
						$goto .= '?' . http_build_query($_GET);
					}
					wp_redirect( $goto, 301 );
					exit;
				}

				wp_enqueue_style( 'woocommerce_instant_checkout',  $this->url . 'woocommerce-instant-checkout.css' );
				wp_enqueue_script( 'woocommerce_instant_checkout',  $this->url . 'woocommerce-instant-checkout.js', array( 'jquery' ) );
			}

		}

		function add_to_cart_text( $label ){
			global $post;
			if( $this->is_instant_checkout( $post->ID ) ){
				// TODO customizable label
				$label = $this->button_label;
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
			$is_instant_checkout = get_post_meta( $post_id, $this->meta_key, true );
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