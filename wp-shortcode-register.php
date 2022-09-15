<?php
/**
 * Plugin Name:       WordPress ShortCode Register Form
 * Plugin URI:        https://realwp.net
 * Description:       Create shortCode Register and Create file Download Order WooCommerce With digits
 * Version:           1.10.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Mehrshad Darzi
 * Author URI:        https://realwp.net
 * Text Domain:       wp-shortcode-register
 * Domain Path:       /languages
 */

class WP_SHORTCODE_REGISTER
{


    /**
     * URL to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_url;

    /**
     * Path to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_path;

    /**
     * Path to this plugin's directory.
     *
     * @type string
     * @status Core
     */
    public static $plugin_version;

    /**
     *__construct
     */
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'plugins_loaded']);
    }

    public function plugins_loaded()
    {
        $this->define_constants();
        add_shortcode('wc-register-product', [$this, 'shortcode']);
        add_action('wp', [$this, 'run_shortcode']);
    }

    /**
     * Define Constant
     */
    public function define_constants()
    {

        /*
         * Get Plugin Data
         */
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(__FILE__);

        /*
         * Set Plugin Version
         */
        self::$plugin_version = $plugin_data['Version'];

        /*
         * Set Plugin Url
         */
        self::$plugin_url = plugins_url('', __FILE__);

        /*
         * Set Plugin Path
         */
        self::$plugin_path = plugin_dir_path(__FILE__);
    }

    /**
     * ShortCode
     */

    public function shortcode($attributes)
    {
        $attributes = shortcode_atts(array(
            'products' => '',
            'redirect' => '',
            'fullname' => 'نام و نام خانوادگی',
            'mobile' => 'شماره همراه',
            'button' => 'ثبت نام',
            'class' => '',
            'payment' => 'free'
        ), $attributes, 'wc_register_product');

        // init Output
        $output = '';

        // Get Template Path
        $template = trailingslashit(get_template_directory()) . 'wc_register_product.php';
        if (file_exists($template)) {
            $path = $template;
        } else {
            $path = self::$plugin_path . '/templates/shortcode.php';
        }

        // Ob Show
        ob_start();
        include $path;
        $output .= ob_get_contents();
        ob_end_clean();

        // Return
        return $output;
    }

    public function run_shortcode()
    {
        if (isset($_GET['wc-register-shortcode'])
            and wp_verify_nonce($_GET['wc-register-shortcode'], 'wc-register-shortcode')
            and isset($_GET['wc-page']) and !empty($_GET['wc-page'])
            and isset($_GET['wc-redirect'])
            and isset($_GET['wc-payment']) and !empty($_GET['wc-payment'])
            and isset($_GET['wc-fullname'])
            and isset($_GET['wc-mobile'])
            and isset($_GET['wc-products'])
        ) {
            global $wpdb;

            // Sanitize Field
            $fullName = sanitize_text_field($_GET['wc-fullname']);
            $Mobile = sanitize_text_field($_GET['wc-mobile']);

            // Check Empty
            if (empty($fullName) || empty($Mobile)) {
                $location = add_query_arg(['_form_notice' => 'لطفا تمامی فیلد ها را پر کنید'], trim($_GET['wc-page']));
                wp_redirect($location);
                exit;
            }

            // Check Validate Mobile Number
            $validateMobile = self::validateMobileNumber($Mobile);
            if (!$validateMobile['success']) {
                $location = add_query_arg(['_form_notice' => $validateMobile['text']], trim($_GET['wc-page']));
                wp_redirect($location);
                exit;
            }

            // Check User Exist Or Created User
            $user = wp_get_current_user();
            if ($user->ID == "0") {
                $user_login = ltrim($Mobile, "0");
                $user = get_user_by('login', $user_login);
                if (!$user) {

                    // Create New User
                    $user_id = wp_insert_user([
                        'user_login' => $user_login,
                        'user_email' => $Mobile . '@' . self::getDomain(),
                        'user_pass' => wp_generate_password(8),
                        'first_name' => trim($fullName),
                        'last_name' => '',
                        'role' => 'subscriber',
                        'display_name' => trim($fullName),
                        'show_admin_bar_front' => 'false'
                    ]);
                    if (is_wp_error($user_id)) {
                        $location = add_query_arg(['_form_notice' => 'خطا در ایجاد کاربر:' . $user_id->get_error_message()], trim($_GET['wc-page']));
                        wp_redirect($location);
                        exit;
                    }

                    // Get User
                    $user = get_user_by('id', $user_id);

                    // Create User Meta
                    update_user_meta($user_id, 'digits_phone', '+98' . $user_login);
                    update_user_meta($user_id, 'digt_countrycode', '+98');
                    update_user_meta($user_id, 'digits_phone_no', $user_login);
                    update_user_meta($user_id, 'billing_phone', $Mobile);
                    update_user_meta($user_id, 'billing_country', '+98');
                    update_user_meta($user_id, 'billing_first_name', $fullName);
                    update_user_meta($user_id, 'billing_last_name', '');

                    // Auto Login User
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                }
            }

            // Generate redirect Link
            $redirect_link = (!empty($_GET['wc-redirect']) ? $_GET['wc-redirect'] : wc_get_account_endpoint_url('dashboard'));

            // Create Order and Added Permission
            if (!empty($_GET['wc-products']) and is_numeric($_GET['wc-products']) and get_post_type($_GET['wc-products']) == "product") {

                // Check Free Payment Or Payment Order
                $payment_method = trim($_GET['wc-payment']);
                if ($payment_method == "free") {

                    // @see https://www.damiencarbery.com/2018/01/programmatically-create-a-woocommerce-order-for-a-downloadable-product/
                    $order = wc_create_order(array(
                        'customer_id' => $user->ID
                    ));
                    $order->add_product(
                        wc_get_product($_GET['wc-products']),
                        1,
                        array('subtotal' => 0, 'total' => 0)
                    );
                    $order->calculate_totals();
                    $order->update_status('completed', '', TRUE);
                    $order->save();
                    wc_downloadable_product_permissions($order->get_order_number());
                    $wpdb->update($wpdb->prefix . 'woocommerce_downloadable_product_permissions', array('user_id' => $user->ID), array('order_id' => $order->get_order_number()), array('%d'), array('%d'));

                    // Redirect to Success Page
                    wp_redirect($redirect_link);
                    exit;
                }

                /**
                 * Create CheckOut Order For Payment
                 * @see https://rudrastyh.com/woocommerce/get-and-hook-payment-gateways.html
                 */

                // Add Product To Cart
                WC()->cart->empty_cart();
                WC()->cart->add_to_cart($_GET['wc-products'], 1);
                WC()->cart->calculate_shipping();
                WC()->cart->calculate_totals();

                // Generate Order
                // @see https://stackoverflow.com/questions/53853204/save-custom-cart-item-data-from-dynamic-created-cart-on-order-creation-in-woocom
                $order_id = WC()->checkout->create_order([
                    'payment_method' => $payment_method
                ]);
                $order = wc_get_order($order_id);
                $user_array = [
                    'first_name' => $fullName,
                    'phone' => $Mobile,
                ];
                $order->set_address($user_array, 'billing');
                $order->set_address($user_array, 'shipping');
                $order->set_customer_id($user->ID);
                $order->calculate_totals();
                $order->save();

                // Store Order ID in session so it can be re-used after payment failure
                WC()->session->set('order_awaiting_payment', $order_id);

                // Process Payment
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                $result = $available_gateways[$payment_method]->process_payment(wc_get_order($order_id));

                // Redirect to success/confirmation/payment page
                if ($result['result'] == 'success') {
                    $result = apply_filters('woocommerce_payment_successful_result', $result, $order_id);
                    wp_redirect($result['redirect']);
                    exit;
                }

                // Wrong in Redirect Payment
                $location = add_query_arg(['_form_notice' => 'خطا در هنگام انتقال به درگاه پرداختی بانک رخ داده است ، لطفا مجدد تلاش کنید'], trim($_GET['wc-page']));
                wp_redirect($location);
                exit;
            }


            // Redirect
            wp_redirect($redirect_link);
            exit;
        }
    }

    /**
     * Utility
     */

    public function currentUrl()
    {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return ($protocol) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    public static function validateMobileNumber($mobile)
    {
        $result = array(
            'success' => true,
            'text' => ''
        );

        //mobile nubmer character
        if (strlen($mobile) !== 11) {
            $result['text'] = 'شماره همراه 11 کاراکتر می باشد';
            $result['success'] = false;
        }

        //mobile start 09
        if (substr($mobile, 0, 2) !== "09") {
            $result['text'] = 'شماره همراه با 09 شروع می شود';
            $result['success'] = false;
        }

        //mobile numberic
        if (!is_numeric($mobile)) {
            $result['text'] = 'شماره همراه تنها شامل کاراکتر عدد می باشد';
            $result['success'] = false;
        }

        return $result;
    }

    public static function getDomain()
    {
        $url = get_option('siteurl');
        $parse = parse_url($url);
        return preg_replace('/^www\./i', '', $parse['host']);
    }


}

new WP_SHORTCODE_REGISTER;
