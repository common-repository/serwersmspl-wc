<?php

/**
 * Plugin Name: WooCommerce SerwerSMS.pl
 * Plugin URI: 
 * Description: Integracja WooCommerce z <a href="http://serwersms.pl" target="_blank">SerwerSMS.pl</a>
 * Author: SerwerSMS.pl
 * Author URI: http://serwersms.pl
 * Version: 1.4
 * Text Domain: woocommerce-serwersmspl
 *
 */
use SerwerSMS\SerwerSMS;

require_once('includes/vendor/autoload.php');

if (!class_exists('WC_SerwerSMS_Plugin')) :

    class WC_SerwerSMS_Plugin {

        private static $object = false;
        private $autorized = false;
        private $client = null;

        /**
         * Initialize the plugin.
         */
        public function __construct() {
		
            if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

				add_action('plugins_loaded', array($this, 'init'));
                add_action('woocommerce_integrations_init', array($this, 'init_integration'));
                add_filter('woocommerce_integrations', array($this, 'add_integration'));
				
				if (is_admin()) {
					add_action('admin_enqueue_scripts', array($this, 'init_library'), 75);
				}

            }
			
			register_deactivation_hook(__FILE__, array($this, 'register_deactivation_hook'));
        }
		
		/* Deactivate */
		public function register_deactivation_hook() {
		
			// update_option('woocommerce_integration-serwersms_settings', apply_filters('woocommerce_settings_api_sanitized_fields_integration-serwersms', array()));
		
		}

        /**
         * Init plugin
         */
        public function init() {

            load_plugin_textdomain('woocommerce-serwersms', FALSE, basename(dirname(__FILE__)) . '/languages/');
            add_filter('plugin_action_links', array($this, 'add_action_links'));
        }

        /**
         * Load file class
         */
        public function init_integration() {
            if (!class_exists('WC_SerwerSMS_Integration')) {
                require_once('includes/class-wc-serwersmspl.php');
            }
        }

        /**
         * Add a new integration to WooCommerce
         */
        public function add_integration($integrations) {
            $integrations[] = 'WC_SerwerSMS_Integration';
            return $integrations;
        }

        /**
         * Get instance of singleton object
         */
        public static function get_instance() {
            if (self::$object == false)
                self::$object = new WC_SerwerSMS_Plugin();
            return self::$object;
        }

        /**
         * Load css file and js library
         */
        public function init_library() {
            wp_enqueue_style('serwersms_style', $this->plugins_url() . '/css/serwersms.css');
            wp_enqueue_script('serwersms_validation', $this->plugins_url() . '/js/validation.js');
            wp_enqueue_script('serwersms_script', $this->plugins_url() . '/js/serwersms.js');
        }

        /**
         * Get url plugin
         */
        public function plugins_url() {
            $reflection = new ReflectionClass($this);
            return plugin_dir_url($reflection->getFileName());
        }

        /**
         * Add links
         */
		function add_action_links($links) {
			
			$plugin_links = array();
			$serwersms = false;
			if(!empty($links)) {
				foreach($links as $l) {
					if(strpos($l, 'serwersms') !== false) {
						$serwersms = true;
					}
				}
			}

			if($serwersms) {
				
				$plugin_links = array(
					'<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=integration-serwersms') . '">' . __('Settings', 'woocommerce-serwersms') . '</a>',
					'<a href="http://serwersms.pl/integracje/moduly-i-wtyczki-sms">' . __('Docs', 'woocommerce-serwersms') . '</a>',
					'<a href="http://serwersms.pl/kontakt">' . __('Support', 'woocommerce-serwersms') . '</a>',
				);
				
			}

            return array_merge($plugin_links, $links);
        }

    }

    WC_SerwerSMS_Plugin::get_instance();

endif;
