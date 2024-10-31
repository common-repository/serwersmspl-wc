<?php
/**
 * Integration SerwerSMS.pl Integration.
 *
 * @package  WC_SerwerSMS_Integration
 * @category Integration
 * @author   SerwerSMS.pl
 */
DEFINE('SALT', '38545437fdsh47fdsjHd634jfds');

use SerwerSMS\SerwerSMS;

require_once('vendor/autoload.php');

if (!class_exists('WC_SerwerSMS_Integration')) :

    class WC_SerwerSMS_Integration extends WC_Integration {

        public $client = null;
        public static $key = null;

        /**
         * Init and hook in the integration.
         */
        public function __construct() {

            global $woocommerce;

            $this->id = 'integration-serwersms';
            $this->method_title = __('SerwerSMS.pl', 'woocommerce-serwersms');
            $this->method_description = sprintf(wp_kses(__('WooCommerce integration with SerwerSMS.pl. <a href="%s" target="_blank">Check out the docs &rarr;</a>', 'woocommerce-serwersms'), array('a' => array('href' => array(), 'target' => '_blank'))), esc_url('http://serwersms.pl/integracje/moduly-i-wtyczki-sms'));

            $this->autorized = $this->get_option('autorized');
            $this->username = $this->get_option('username');
            $this->password = $this->decode_password($this->get_option('password'));
            $this->phone = $this->get_option('phone');
            $this->sender = $this->get_option('sender');
            $this->unicode = $this->get_option('unicode');
            $this->flash = $this->get_option('flash');
            $this->speed = $this->get_option('speed');
            $this->test = $this->get_option('test');

            $this->sms_flag_user_add = $this->get_option('sms_flag_user_add');
            $this->sms_text_user_add = $this->get_option('sms_text_user_add');

            $this->sms_flag_order_add = $this->get_option('sms_flag_order_add');
            $this->sms_text_order_add = $this->get_option('sms_text_order_add');

            $this->sms_flag_order_processing = $this->get_option('sms_flag_order_processing');
            $this->sms_text_order_processing = $this->get_option('sms_text_order_processing');

            $this->sms_flag_order_complete = $this->get_option('sms_flag_order_complete');
            $this->sms_text_order_complete = $this->get_option('sms_text_order_complete');

            $this->sms_flag_order_cancelled = $this->get_option('sms_flag_order_cancelled');
            $this->sms_text_order_cancelled = $this->get_option('sms_text_order_cancelled');

            $this->sms_flag_order_failed = $this->get_option('sms_flag_order_failed');
            $this->sms_text_order_failed = $this->get_option('sms_text_order_failed');

            $this->sms_flag_order_refunded = $this->get_option('sms_flag_order_refunded');
            $this->sms_text_order_refunded = $this->get_option('sms_text_order_refunded');

            $this->sms_flag_note = $this->get_option('sms_flag_note');

            self:$key = hash('sha256', SALT, true);
            $this->message = '';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();
					
			add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
            add_action('admin_footer', array($this, 'load_javascript'));
			
            add_action('woocommerce_created_customer', array($this, 'hook_user_add'));
            add_action('woocommerce_order_status_changed', array($this, 'hook_order_change'));
            add_action('woocommerce_new_customer_note_notification', array($this, 'hook_note_add'));
			
			add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'sanitize_settings'));

        }
		
		/**
         * Get note of products from order
         */
        public function note_text($order) {
		
			$text = '';
			if(!$order) {
				return $text;
			}
		
			$items = $order->get_items();
			foreach ( $items as $item ) {
				$product_id = $item['product_id'];
				$text .= get_post_meta($product_id, '_purchase_note', true) . ', ';
			}
				
			if(strlen($text)>2) {
				$text = substr($text, 0, -2);
			}
			
			//$payment_gateway = wc_get_payment_gateway_by_order($order);
					
			return $text;
				
		}
		

        /**
         * Get url of plugin
         */
        public function plugin_url() {

            $url = '/' . plugin_basename(dirname(dirname(__FILE__)));
            if (is_ssl()) {
                return str_replace('http://', 'https://', WP_PLUGIN_URL) . $url;
            } else {
                return WP_PLUGIN_URL . $url;
            }
        }

        /**
         * Check is integration
         */
        public function is_integration() {

            global $woocommerce;
            $flag = false;

            if (version_compare($woocommerce->version, '2.1.0', '>='))
                $flag = isset($_GET['page']) && isset($_GET['tab']) && $_GET['page'] == 'wc-settings' && $_GET['tab'] == "integration";
            else
                $flag = isset($_GET['page']) && isset($_GET['tab']) && $_GET['page'] == 'woocommerce_settings' && $_GET['tab'] == "integration";

            return $flag;
        }

        /**
         * Set data to auth
         */
        public function api_set($username, $password) {

            $this->username = $username;
            $this->password = $password;
            return true;
        }

        /**
         * Check user data for autorization
         */
        public function api_connect() {

            try {
                $this->client = new SerwerSMS($this->username, $this->password);
                $this->client->account->limits();
                return true;
            } catch (Exception $e) {
                return false;
            }

            return false;
        }

        /**
         * Get sender list
         */
        public function api_get_senders() {

            $senders = array('' => 'Losowy numer (ECO)');
			
            try {

                $this->client = new SerwerSMS($this->username, $this->password);

                $response = $this->client->senders->index(array('predefined' => false));
                if (isset($response->items) && !empty($response->items)) {
                    foreach ($response->items as $r) {
                        if ($r->status == 'authorized')
                            $senders[$r->name] = $r->name;
                    }
                }

                $response = $this->client->senders->index(array('predefined' => true));
                if (isset($response->items) && !empty($response->items)) {
                    foreach ($response->items as $r) {
                        if ($r->status == 'authorized')
                            $senders[$r->name] = $r->name;
                    }
                }
            } catch (Exception $e) {
                return $senders;
            }

            return $senders;
        }

        /**
         * Send SMS
         */
        public function api_send_sms() {

            try {

                $this->client = new SerwerSMS($this->username, $this->password);

                $params = array(
                    'details' => false,
                    'utf' => ($this->unicode == 'yes') ? true : false,
                    'flash' => ($this->flash == 'yes') ? true : false,
                    'speed' => ($this->speed == 'yes') ? true : false,
                    'test' => ($this->test == 'yes') ? true : false,
                );

                if ($this->sender == '') {
                    $params['utf'] = false;
                }
				
                $response = $this->client->messages->sendSms($this->phone, $this->message, $this->sender, $params);
				
            } catch (Exception $e) {
                return false;
            }

            return false;
        }

        /**
         * Hook: Add customer function.
         *
         * @return void
         */
        public function hook_user_add($user_id) {
		
            if (!$this->autorized)
                return false;

            if ($user_id && $this->sms_flag_user_add == 'yes') {

                $user = get_user_by('id', $user_id);
                if (!empty($user) && isset($user->data->ID)) {

                    $message = $this->sms_text_user_add;
                    $customer = '';
                    $email = '';
                    if (isset($user->data->user_nicename))
                        $customer = $user->data->user_nicename;

                    if (isset($user->data->user_email))
                        $email = $user->data->user_email;

                    $message = preg_replace("/\{customer\}/i", $customer, $message);
                    $message = preg_replace("/\{email\}/i", $email, $message);

                    $this->message = $message;
                    $this->api_send_sms();
                }
            }
        }

        /**
         * Hook: Change order function.
         *
         * @return void
         */
        public function hook_order_change($order_id) {

            if (!$this->autorized)
                return false;

            if ($order_id) {

                $order = new WC_Order($order_id);
                if ($order) {

                    $send = false;
                    $status = '';
					
                    if ($order->post_status == 'wc-on-hold' || $order->post_status == 'wc-processing') {
                        $message = $this->sms_text_order_add;
                        $send = $this->sms_flag_order_add;
                    } 
					
					if ($order->post_status == 'wc-completed') {
                        $message = $this->sms_text_order_complete;
                        $this->phone = $order->billing_phone;
                        $send = $this->sms_flag_order_complete;
                        $status = __('Complete', 'woocommerce-serwersms');
                    } else if ($order->post_status == 'wc-cancelled') {
                        $message = $this->sms_text_order_cancelled;
                        $this->phone = $order->billing_phone;
                        $send = $this->sms_flag_order_cancelled;
                        $status = __('Cancled', 'woocommerce-serwersms');
                    } else if ($order->post_status == 'wc-failed') {
                        $message = $this->sms_text_order_failed;
                        $this->phone = $order->billing_phone;
                        $send = $this->sms_flag_order_failed;
                        $status = __('Failed', 'woocommerce-serwersms');
                    } else if ($order->post_status == 'wc-refunded') {
                        $message = $this->sms_text_order_refunded;
                        $this->phone = $order->billing_phone;
                        $send = $this->sms_flag_order_refunded;
                        $status = __('Refunded', 'woocommerce-serwersms');
                    }

                    if ($send == 'yes' && $this->phone != '') {

                        $id = '';
                        $customer = '';
                        $email = '';
                        $price = '';
                        $shipping_address = '';
                        $phone = '';
                        $title = '';

                        if (isset($order->id))
                            $id = $order->id;
                        
                        if (isset($order->post->post_title))
                            $title = $order->post->post_title;
                        
                        if (isset($order->billing_first_name) && isset($order->billing_last_name))
                            $customer = $order->billing_first_name . ' ' . $order->billing_last_name;

                        if (isset($order->billing_email))
                            $email = $order->billing_email;

                        if (isset($order->order_total))
                            $price = $order->order_total;
                        
                        if (isset($order->shipping_company))
                            $shipping_address .= $order->shipping_company.' ';
                        
                        if (isset($order->shipping_first_name) && isset($order->shipping_last_name))
                            $shipping_address .= $order->shipping_first_name . ' ' . $order->shipping_last_name.', ';
                        
                        if (isset($order->shipping_address_1) and isset($order->shipping_address_2))
                            $shipping_address .= $order->shipping_address_1.' '.$order->shipping_address_2.', ';
                        
                        if (isset($order->shipping_city) and isset($order->shipping_postcode))
                            $shipping_address .= $order->shipping_postcode.' '.$order->shipping_city.', ';
                        
                        if (isset($order->shipping_country))
                            $shipping_address .= $order->shipping_country;
                        
                        if(isset($order->billing_phone))
                            $phone = $order->billing_phone;
                        
						$note_text = $this->note_text($order);
                        $message = preg_replace("/\{order_id\}/i", $id, $message);
                        $message = preg_replace("/\{order_title\}/i", $title, $message);
                        $message = preg_replace("/\{customer\}/i", $customer, $message);
                        $message = preg_replace("/\{email\}/i", $email, $message);
                        $message = preg_replace("/\{price\}/i", $price, $message);
                        $message = preg_replace("/\{status\}/i", $status, $message);
                        $message = preg_replace("/\{shipping_address\}/i", $shipping_address, $message);
                        $message = preg_replace("/\{phone\}/i", $phone, $message);
						$message = preg_replace("/\{note\}/i", $note_text, $message);

                        $this->message = $message;
                        $this->api_send_sms();
                    }
                }
            }
        }

        /**
         * Hook: Add note function.
         *
         * @return void
         */
        public function hook_note_add($note) {

            if (!$this->autorized)
                return false;

            if ($note && $note['customer_note'] != '') {

                $order = new WC_Order($note['order_id']);
                if ($this->sms_flag_note == 'yes') {

                    $this->phone = $order->billing_phone;
                    $this->message = $note['customer_note'];

                    $this->api_send_sms();
                }
            }
        }

        /**
         * Initialize integration settings form fields.
         *
         * @return void
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'username' => array(
                    'title' => __('Username', 'woocommerce-serwersms'),
                    'type' => 'text',
                    'description' => __('Enter username', 'woocommerce-serwersms'),
                    'desc_tip' => true,
                    'default' => ''
                ),
                'password' => array(
                    'title' => __('Password', 'woocommerce-serwersms'),
                    'type' => 'password',
                    'description' => __('Enter password', 'woocommerce-serwersms'),
                    'desc_tip' => true,
                    'default' => ''
                )
            );

            if ($this->autorized) {

                $this->form_fields['username']['disabled'] = true;
                $this->form_fields['password']['disabled'] = true;

                $this->form_fields['reset'] = array(
                    'label' => ' ',
                    'title' => __('Reset plugin', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => __('Reset plugin description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );
				
				if(strpos($_SERVER['REQUEST_URI'],'page=wc-settings&tab=integration') !== false) {
				
					$data = $this->api_get_senders();
					$this->form_fields['sender'] = array(
						'label' => ' ',
						'title' => __('Sender', 'woocommerce-serwersms'),
						'type' => 'select',
						'options' => $data,
						'description' => __('Sender description', 'woocommerce-serwersms'),
						'desc_tip' => false,
						'default' => ''
					);
				
				}
				
                $this->form_fields['phone'] = array(
                    'label' => ' ',
                    'title' => __('Phone', 'woocommerce-serwersms'),
                    'type' => 'text',
                    'description' => __('Phone description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['unicode'] = array(
                    'label' => ' ',
                    'title' => __('Unicode', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => __('Unicode description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['flash'] = array(
                    'label' => ' ',
                    'title' => __('Flash', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => __('Flash description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['speed'] = array(
                    'label' => ' ',
                    'title' => __('Speed', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => __('Speed description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['test'] = array(
                    'label' => ' ',
                    'title' => __('Test', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => __('Test description', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_flag_user_add'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag register', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_user_add'] = array(
                    'label' => '',
                    'title' => __('SMS Text register', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{customer} - name of user<br/>{email} - email of user', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Utworzono nowe konto użytkownika. Nazwa: {customer},  email: {email}'
                );

                $this->form_fields['sms_flag_order_add'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order add', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_order_add'] = array(
                    'label' => '',
                    'title' => __('SMS Text order add', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Masz nowe zamówienie. ID: {order_id}, nazwa: {customer}, email: {email}, cena: {price}'
                );

				/*
                $this->form_fields['sms_flag_order_processing'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order processing', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );
				

                $this->form_fields['sms_text_order_processing'] = array(
                    'label' => '',
                    'title' => __('SMS Text order processing', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order<br/>{status} - status order', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Witaj {customer}! Twoje zamówienie ID {order_id} o wartości {price} zmieniło status na "{status}"'
                );
				*/


                $this->form_fields['sms_flag_order_complete'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order complete', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_order_complete'] = array(
                    'label' => '',
                    'title' => __('SMS Text order complete', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order<br/>{status} - status order<br/>{note} - product notes', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Witaj {customer}! Twoje zamówienie ID {order_id} o wartości {price} zmieniło status na "{status}"'
                );

                $this->form_fields['sms_flag_order_cancelled'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order cancelled', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_order_cancelled'] = array(
                    'label' => '',
                    'title' => __('SMS Text order cancelled', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order<br/>{status} - status order<br/>{note} - product notes', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Witaj {customer}! Twoje zamówienie ID {order_id} o wartości {price} zmieniło status na "{status}"'
                );


                $this->form_fields['sms_flag_order_failed'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order failed', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_order_failed'] = array(
                    'label' => '',
                    'title' => __('SMS Text order failed', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order<br/>{status} - status order<br/>{note} - product notes', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Witaj {customer}! Twoje zamówienie ID {order_id} o wartości {price} zmieniło status na "{status}"'
                );


                $this->form_fields['sms_flag_order_refunded'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag order refunded', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );

                $this->form_fields['sms_text_order_refunded'] = array(
                    'label' => '',
                    'title' => __('SMS Text order refunded', 'woocommerce-serwersms'),
                    'type' => 'textarea',
                    'description' => __('Fields personalization:<br/>{order_id} - order id<br/>{customer} - firstname and lastname of user<br/>{email} - email of user<br/>{price} - total price order<br/>{status} - status order<br/>{note} - product notes', 'woocommerce-serwersms'),
                    'desc_tip' => false,
                    'default' => 'Witaj {customer}! Twoje zamówienie ID {order_id} o wartości {price} zmieniło status na "{status}"'
                );


                $this->form_fields['sms_flag_note'] = array(
                    'label' => ' ',
                    'title' => __('SMS Flag note', 'woocommerce-serwersms'),
                    'type' => 'checkbox',
                    'description' => false,
                    'desc_tip' => false,
                    'default' => ''
                );
            }
        }

        /**
         * Validate, save and refresh form
         */
        public function process_admin_options() {
		
			$post_data = $this->get_post_data();
			foreach ( $this->get_form_fields() as $key => $field ) {
				$this->settings[$key] = $this->get_field_value($key, $field, $post_data);
			}
			
            if (count($this->errors) > 0) {
                $this->display_errors();
                return false;
            } else {
			
                update_option($this->plugin_id . $this->id . '_settings', apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));

				// Load the settings
                $this->init_form_fields();
                $this->init_settings();
				
                return true;
            }
        }

        /**
         * Sanitize our settings
         */
        public function sanitize_settings($settings) {
		
            if (!$this->autorized && isset($settings['username']) && isset($settings['password'])) {

				// Set account data
                $this->api_set($settings['username'], $settings['password']);
				
				// Check is account data is ok
                $this->autorized = $this->api_connect();
				
				if(!$this->autorized) {
					$this->display_auth_error();
				}
            }

            // Code password
            if (isset($settings['password'])) {
                $settings['password'] = $this->encode_password($settings['password']);
            }

            $settings['autorized'] = $this->autorized;

            // Reset plugin
            if (isset($settings['reset']) && $settings['reset'] == 'yes') {
                $this->autorized = false;
                $settings = array();
            }
			
            return $settings;
        }

        /**
         * Validate the username
         */
        public function validate_username_field($key) {

			// When reset
			if (isset($_POST[$this->plugin_id . $this->id . '_reset']) && $_POST[$this->plugin_id . $this->id . '_reset'] == 1) {
				return true;
			}

			$value = '';
            if (isset($_POST[$this->plugin_id . $this->id . '_' . $key]))
                $value = $_POST[$this->plugin_id . $this->id . '_' . $key];

            // When input is disabled
            if ($value == '' && $this->username != '') {
                $value = $this->username;
            }

            if ($value == '')
                $this->errors[] = __('Field is required 1', 'woocommerce-serwersms');

            return $value;
        }

        /**
         * Validate the password
         */
        public function validate_password_field($key, $value = '') {

			
			// When reset
			if (isset($_POST[$this->plugin_id . $this->id . '_reset']) && $_POST[$this->plugin_id . $this->id . '_reset'] == 1) {
				return true;
			}
			
            if (isset($_POST[$this->plugin_id . $this->id . '_' . $key])) {
				if($value == '') {
					$value = $_POST[$this->plugin_id . $this->id . '_' . $key];
				}
			}
			
            // When input is disabled
            if ($value == '' && $this->password != '')
                $value = $this->password;

            if ($value == '')
                $this->errors[] = __('Field is required 2', 'woocommerce-serwersms');

            return $value;
        }

        /**
         * Validate the phone
         */
        public function validate_phone_field($key) {

			// When reset
			if (isset($_POST[$this->plugin_id . $this->id . '_reset']) && $_POST[$this->plugin_id . $this->id . '_reset'] == 1) {
				return true;
			}
		
            $value = '';
            if (isset($_POST[$this->plugin_id . $this->id . '_' . $key]))
                $value = $_POST[$this->plugin_id . $this->id . '_' . $key];

            if ($value == '')
                $this->errors[] = __('Field is required 3', 'woocommerce-serwersms');

            return $value;
        }

        public static function decode_password($value = '') {

            $c = base64_decode($value);
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len=32);
            $ciphertext_raw = substr($c, $ivlen+$sha2len);
            $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, self::$key, $options=OPENSSL_RAW_DATA, $iv);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, self::$key, $as_binary=true);
            if (hash_equals($hmac, $calcmac))
            {
                return $original_plaintext;
            }
        }

        public static function encode_password($value = '') {

            $plaintext = $value;
            $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
            $iv = openssl_random_pseudo_bytes($ivlen);
            $ciphertext_raw = openssl_encrypt($plaintext, $cipher, self::$key, $options=OPENSSL_RAW_DATA, $iv);
            $hmac = hash_hmac('sha256', $ciphertext_raw, self::$key, $as_binary=true);

            return base64_encode( $iv.$hmac.$ciphertext_raw );
        }

        /**
         * Load javascript script
         */
        public function load_javascript() {
            if ($this->is_integration()) {
                ?>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        if ($('form.serwersms').length > 0)
                            $('form.serwersms').validator();
                    });
                </script>

                <?php
            }
        }

        /**
         * Modify template form
         */
        public function admin_options() {
            ?>
            <div class="logo" style="background: #d91218;"><img alt="" src="<?php echo $this->plugin_url(); ?>/image/serwersms_logo.png"></div>

            <h3><?php echo isset($this->method_title) ? $this->method_title : __('Settings', 'woocommerce'); ?></h3>

            <?php echo isset($this->method_description) ? wpautop($this->method_description) : ''; ?>

            <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            </table>

            <div><input type="hidden" name="section" value="<?php echo $this->id; ?>" /></div>

            <?php
        }

        /**
         * Display validating errors
         */
        public function display_errors() {

            foreach ($this->errors as $key => $value) {
                ?>
                <div class="error">
                    <p><?php _e($value, 'woocommerce-serwersms'); ?></p>
                </div>
                <?php
            }
        }

        /**
         * Display auth errors
         */
        public function display_auth_error() {

			?>
			<div class="error">
				<p><?php _e('Incorect login or password', 'woocommerce-serwersms'); ?></p>
			</div>
			<?php
        }

    }


endif;
