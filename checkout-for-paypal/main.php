<?php
/*
  Plugin Name: Checkout for PayPal
  Version: 1.0.27
  Plugin URI: https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/  
  Author: naa986
  Author URI: https://noorsplugin.com/
  Description: Add PayPal Smart Payment Buttons to Your Website
  Text Domain: checkout-for-paypal
  Domain Path: /languages
 */

if (!defined('ABSPATH'))
    exit;

class CHECKOUT_FOR_PAYPAL {
    
    var $plugin_version = '1.0.27';
    var $db_version = '1.0.2';
    var $plugin_url;
    var $plugin_path;
    
    function __construct() {
        define('CHECKOUT_FOR_PAYPAL_VERSION', $this->plugin_version);
        define('CHECKOUT_FOR_PAYPAL_DB_VERSION', $this->db_version);
        define('CHECKOUT_FOR_PAYPAL_SITE_URL', site_url());
        define('CHECKOUT_FOR_PAYPAL_HOME_URL', home_url());
        define('CHECKOUT_FOR_PAYPAL_URL', $this->plugin_url());
        define('CHECKOUT_FOR_PAYPAL_PATH', $this->plugin_path());
        $options = checkout_for_paypal_get_option();     
        if (isset($options['enable_debug']) && $options['enable_debug']=="1") {
            define('CHECKOUT_FOR_PAYPAL_DEBUG', true);
        } else {
            define('CHECKOUT_FOR_PAYPAL_DEBUG', false);
        }
        define('CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH', $this->debug_log_path());
        $this->plugin_includes();
        $this->loader_operations();
    }

    function plugin_includes() {
        include_once('checkout-for-paypal-order.php');
        include_once('cfp-email.php');
        if(is_admin()){
            include_once('addons/checkout-for-paypal-addons-menu.php');
        }
    }

    function loader_operations() {
        add_action('plugins_loaded', array($this, 'plugins_loaded_handler'));
        add_action('admin_notices', array($this, 'admin_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'plugin_scripts'));
        add_action('admin_menu', array($this, 'add_options_menu'));
        add_action('init', array($this, 'plugin_init'));
        add_filter('manage_coforpaypal_order_posts_columns', 'checkout_for_paypal_order_columns');
        add_action('manage_coforpaypal_order_posts_custom_column', 'checkout_for_paypal_custom_column', 10, 2);
        add_action('wp_ajax_coforpaypal_ajax_process_order', 'checkout_for_paypal_ajax_process_order');
        add_action('wp_ajax_nopriv_coforpaypal_ajax_process_order', 'checkout_for_paypal_ajax_process_order');
        add_action('checkout_for_paypal_process_order', 'checkout_for_paypal_process_order_handler');
        add_shortcode('checkout_for_paypal', 'checkout_for_paypal_button_handler');
    }

    function plugins_loaded_handler() {  //Runs when plugins_loaded action gets fired
        if(is_admin() && current_user_can('manage_options')){
            add_filter('plugin_action_links', array($this, 'add_plugin_action_links'), 10, 2);
        }
        load_plugin_textdomain( 'checkout-for-paypal', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
        $this->check_upgrade();
    }
    
    function activate_handler() {
        checkout_for_paypal_set_default_email_options();
        //migration code - added 17-11-2023
        $options = checkout_for_paypal_get_option();
        if(isset($options['enable_venmo']) && $options['enable_venmo'] == '1'){
            $options['enable_funding'] = 'venmo';
            $options['enable_venmo'] = '';
            checkout_for_paypal_update_option($options);
        }
        //
        add_option('checkout_for_paypal_db_version', $this->db_version);
    }

    function check_upgrade() {
        if (is_admin()) {
            $db_version = get_option('checkout_for_paypal_db_version');
            if (!isset($db_version) || $db_version != $this->db_version) {
                $this->activate_handler();
                update_option('checkout_for_paypal_db_version', $this->db_version);
            }
        }
    }

    function admin_notice() {
        if (CHECKOUT_FOR_PAYPAL_DEBUG) {  //debug is enabled. Check to make sure log file is writable
            $log_file = CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH;
            if(!file_exists($log_file)){
                return;
            }
            if(!is_writeable($log_file)){
                echo '<div class="updated"><p>' . __('Checkout for PayPal Debug log file is not writable. Please check to make sure that it has the correct file permission (ideally 644). Otherwise the plugin will not be able to write to the log file. The log file can be found in the root directory of the plugin - ', 'checkout-for-paypal') . '<code>' . CHECKOUT_FOR_PAYPAL_URL . '</code></p></div>';
            }
        }
    }

    function plugin_init() {
        //register order type
        checkout_for_paypal_register_order_type();
    }
    
    function enqueue_admin_scripts($hook) {
        if('coforpaypal_order_page_checkout-for-paypal-addons' != $hook) {
            return;
        }
        wp_register_style('checkout-for-paypal-addon-menu', CHECKOUT_FOR_PAYPAL_URL.'/addons/checkout-for-paypal-addons-menu.css');
        wp_enqueue_style('checkout-for-paypal-addon-menu');
    }
    
    function plugin_scripts() {
        if (is_404()) {
            return;
        }
        if (!is_admin()) {
            $load_scripts_globally = get_option('checkout_for_paypal_load_scripts_globally');
            if(isset($load_scripts_globally) && !empty($load_scripts_globally)){
                $this->load_scripts();
                return;
            }
            global $post;
            if(!is_a($post, 'WP_Post')){
                return;
            }
            $is_js_required = false;
            if(has_shortcode($post->post_content, 'checkout_for_paypal')){
                $is_js_required = true;
            }
            if(has_shortcode(get_post_meta($post->ID, 'checkout-for-paypal-custom-field', true), 'checkout_for_paypal')){
                $is_js_required = true;
            }
            if($is_js_required){
                $this->load_scripts();
            }
        }
    }
    
    function load_scripts(){
        $options = checkout_for_paypal_get_option();
        $args = array(
            'client-id' => $options['app_client_id'],
            'currency' => $options['currency_code'],                 
        );
        if(isset($options['enable_funding']) && !empty($options['enable_funding'])){
            $args['enable-funding'] = $options['enable_funding'];
        }
        if(isset($options['disable_funding']) && !empty($options['disable_funding'])){
            $args['disable-funding'] = $options['disable_funding'];
        }
        $locale = get_option('checkout_for_paypal_locale');
        if(isset($locale) && !empty($locale)){
            $args['locale'] = $locale;
        }
        $sdk_js_url = add_query_arg($args, 'https://www.paypal.com/sdk/js');
        wp_enqueue_script('jquery');
        wp_register_script('checkout-for-paypal', $sdk_js_url, array('jquery'), null);
        wp_enqueue_script('checkout-for-paypal');
    }
    
    function plugin_url() {
        if ($this->plugin_url){
            return $this->plugin_url;
        }
        return $this->plugin_url = plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__));
    }

    function plugin_path() {
        if ($this->plugin_path){
            return $this->plugin_path;
        }
        return $this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));
    }

    function debug_log_path() {
        return CHECKOUT_FOR_PAYPAL_PATH . '/logs/'. $this->debug_log_file_name();
    }
    
    function debug_log_file_name() {
        return 'log-'.$this->debug_log_file_suffix().'.txt';
    }
    
    function debug_log_file_suffix() {
        $suffix = get_option('checkoutforpaypal_logfile_suffix');
        if(isset($suffix) && !empty($suffix)) {
            return $suffix;
        }
        $suffix = uniqid();
        update_option('checkoutforpaypal_logfile_suffix', $suffix);
        return $suffix;
    }

    function add_plugin_action_links($links, $file) {
        if ($file == plugin_basename(dirname(__FILE__) . '/main.php')) {
            $links[] = '<a href="'.esc_url(admin_url('edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-settings')).'">'.__('Settings', 'checkout-for-paypal').'</a>';
        }
        return $links;
    }

    function add_options_menu() {
        if (is_admin()) {
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Settings', 'checkout-for-paypal'), __('Settings', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-settings', array($this, 'options_page'));
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Debug', 'checkout-for-paypal'), __('Debug', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-debug', array($this, 'debug_page'));
            add_submenu_page('edit.php?post_type=coforpaypal_order', __('Add-ons', 'checkout-for-paypal'), __('Add-ons', 'checkout-for-paypal'), 'manage_options', 'checkout-for-paypal-addons', 'checkout_for_paypal_display_addons_menu');
        }
    }

    function options_page() {
        $plugin_tabs = array(
            'checkout-for-paypal-settings' => __('General', 'checkout-for-paypal'),
            'checkout-for-paypal-settings&tab=emails' => __('Emails', 'checkout-for-paypal')
        );
        echo '<div class="wrap"><h2>'.__('Checkout for PayPal', 'checkout-for-paypal').' v' . CHECKOUT_FOR_PAYPAL_VERSION . '</h2>';
        $url = 'https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/';
        $link_msg = sprintf(__( 'Please visit the <a target="_blank" href="%s">Checkout for PayPal</a> documentation page for instructions.', 'checkout-for-paypal' ), esc_url($url));
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'target' => array()
            )
        );
        echo '<div class="update-nag">'.wp_kses($link_msg, $allowed_html_tags).'</div>';
        $current = '';
        $tab = '';
        if (isset($_GET['page'])) {
            $current = sanitize_text_field($_GET['page']);
            if (isset($_GET['tab'])) {
                $tab = sanitize_text_field($_GET['tab']);
                $current .= "&tab=" . $tab;
            }
        }
        $content = '';
        $content .= '<h2 class="nav-tab-wrapper">';
        foreach ($plugin_tabs as $location => $tabname) {
            if ($current == $location) {
                $class = ' nav-tab-active';
            } else {
                $class = '';
            }
            $content .= '<a class="nav-tab' . $class . '" href="?post_type=coforpaypal_order&page=' . $location . '">' . $tabname . '</a>';
        }
        $content .= '</h2>';
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'class' => array()
            ),
            'h2' => array(
                'href' => array(),
                'class' => array()
            )
        );
        echo wp_kses($content, $allowed_html_tags);
        
        if(!empty($tab))
        { 
            switch ($tab)
            {
               case 'emails':
                   $this->email_settings();
                   break;
            }
        }
        else
        {
            $this->general_settings();
        }

        echo '</div>';
    }

    function general_settings() {
        if (isset($_POST['checkout_for_paypal_update_settings'])) {
            $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
            if (!wp_verify_nonce($nonce, 'checkout_for_paypal_general_settings')) {
                wp_die(__('Error! Nonce Security Check Failed! please save the general settings again.', 'checkout-for-paypal'));
            }
            $app_client_id = '';
            if(isset($_POST['app_client_id']) && !empty($_POST['app_client_id'])){
                $app_client_id = sanitize_text_field($_POST['app_client_id']);
            }
            $currency_code = '';
            if(isset($_POST['currency_code']) && !empty($_POST['currency_code'])){
                $currency_code = sanitize_text_field($_POST['currency_code']);
            }
            $return_url = '';
            if(isset($_POST['return_url']) && !empty($_POST['return_url'])){
                $return_url = esc_url_raw($_POST['return_url']);
            }
            $cancel_url = '';
            if(isset($_POST['cancel_url']) && !empty($_POST['cancel_url'])){
                $cancel_url = esc_url_raw($_POST['cancel_url']);
            }
            if(isset($_POST['locale'])){
                update_option('checkout_for_paypal_locale', sanitize_text_field($_POST['locale']));
            }
            $load_scripts_globally = (isset($_POST['load_scripts_globally']) && $_POST['load_scripts_globally'] == '1') ? '1' : '';
            update_option('checkout_for_paypal_load_scripts_globally', $load_scripts_globally);
            $enable_funding = '';
            if(isset($_POST['enable_funding'])){
                $enable_funding = sanitize_text_field($_POST['enable_funding']);
            }
            $disable_funding = '';
            if(isset($_POST['disable_funding'])){
                $disable_funding = sanitize_text_field($_POST['disable_funding']);
            }
            $paypal_options = array();
            $paypal_options['app_client_id'] = $app_client_id;
            $paypal_options['currency_code'] = $currency_code;
            $paypal_options['return_url'] = $return_url;
            $paypal_options['cancel_url'] = $cancel_url;
            $paypal_options['enable_funding'] = $enable_funding;
            $paypal_options['disable_funding'] = $disable_funding;
            checkout_for_paypal_update_option($paypal_options);
            echo '<div id="message" class="updated fade"><p><strong>';
            echo __('Settings Saved', 'checkout-for-paypal').'!';
            echo '</strong></p></div>';
        }
        $paypal_options = checkout_for_paypal_get_option();
        $cancel_url = (isset($paypal_options['cancel_url']) && !empty($paypal_options['cancel_url'])) ? $paypal_options['cancel_url'] : '';
        $locale = get_option('checkout_for_paypal_locale');
        if(!isset($locale) || empty($locale)){
            $locale = '';
        }
        $load_scripts_globally = get_option('checkout_for_paypal_load_scripts_globally');
        if(!isset($load_scripts_globally) || empty($load_scripts_globally)){
            $load_scripts_globally = '';
        }
        $enable_funding = (isset($paypal_options['enable_funding']) && !empty($paypal_options['enable_funding'])) ? $paypal_options['enable_funding'] : '';
        $disable_funding = (isset($paypal_options['disable_funding']) && !empty($paypal_options['disable_funding'])) ? $paypal_options['disable_funding'] : '';
        $locale_doc_url = "https://noorsplugin.com/paypal-checkout-locale/";
        $locale_doc_link = sprintf(__('You can find the full list <a target="_blank" href="%s">here</a>.', 'checkout-for-paypal'), esc_url($locale_doc_url));
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'target' => array()
            )
        );
        $funding_src_doc_url = "https://noorsplugin.com/paypal-checkout-funding-sources/";
        $funding_src_doc_link = sprintf(__('You can find the full list of funding sources <a target="_blank" href="%s">here</a>.', 'checkout-for-paypal'), esc_url($funding_src_doc_url));
        ?>
        <table class="coforpaypal-general-settings-table">
            <tbody>
                <tr>
                    <td valign="top">
                        <form method="post" action="">
                            <?php wp_nonce_field('checkout_for_paypal_general_settings'); ?>

                            <table class="form-table">

                                <tbody>

                                    <tr valign="top">
                                        <th scope="row"><label for="app_client_id"><?php _e('Client ID', 'checkout-for-paypal');?></label></th>
                                        <td><input name="app_client_id" type="text" id="app_client_id" value="<?php echo esc_attr($paypal_options['app_client_id']); ?>" class="regular-text">
                                            <p class="description"><?php _e('The client ID for your PayPal REST API app', 'checkout-for-paypal');?></p></td>
                                    </tr>

                                    <tr valign="top">
                                        <th scope="row"><label for="currency_code"><?php _e('Currency Code', 'checkout-for-paypal');?></label></th>
                                        <td><input name="currency_code" type="text" id="currency_code" value="<?php echo esc_attr($paypal_options['currency_code']); ?>" class="regular-text">
                                            <p class="description"><?php _e('The default currency of the payment', 'checkout-for-paypal');?> (<?php _e('example', 'checkout-for-paypal');?>: USD, CAD, GBP, EUR)</p></td>
                                    </tr>

                                    <tr valign="top">
                                        <th scope="row"><label for="return_url"><?php _e('Return URL', 'checkout-for-paypal');?></label></th>
                                        <td><input name="return_url" type="text" id="return_url" value="<?php echo esc_url($paypal_options['return_url']); ?>" class="regular-text">
                                            <p class="description"><?php _e('The page URL to which the customer will be redirected after a successful payment (optional).', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                    
                                    <tr valign="top">
                                        <th scope="row"><label for="cancel_url"><?php _e('Cancel URL', 'checkout-for-paypal');?></label></th>
                                        <td><input name="cancel_url" type="text" id="cancel_url" value="<?php echo esc_url($cancel_url); ?>" class="regular-text">
                                            <p class="description"><?php _e('The page URL to which the customer will be redirected when a payment is cancelled (optional).', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                    
                                    <tr valign="top">
                                        <th scope="row"><label for="locale"><?php _e('Locale', 'checkout-for-paypal');?></label></th>
                                        <td><input name="locale" type="text" id="locale" value="<?php echo esc_attr($locale); ?>" class="regular-text">
                                            <p class="description"><?php _e('The locale used to localize PayPal Checkout components (optional). Example:', 'checkout-for-paypal');?> <strong>fr_FR</strong>. <?php echo wp_kses($locale_doc_link, $allowed_html_tags).' '.__('Leave it empty if you want PayPal to detect the correct locale for the buyer based on their geolocation and browser preferences.', 'checkout-for-paypal');?></p></td>
                                    </tr>                                 
                                    
                                    <tr valign="top">
                                        <th scope="row"><?php _e('Load Scripts Globally', 'checkout-for-paypal');?></th>
                                        <td> <fieldset><legend class="screen-reader-text"><span>Load Scripts Globally</span></legend><label for="load_scripts_globally">
                                                    <input name="load_scripts_globally" type="checkbox" id="load_scripts_globally" <?php if ($load_scripts_globally == '1') echo ' checked="checked"'; ?> value="1">
                                                    <?php _e("Check this option if you want to load PayPal scripts on every page. By default, the scripts are loaded only when a shortcode is present.", 'checkout-for-paypal');?></label>
                                            </fieldset></td>
                                    </tr>
                                    
                                    <tr valign="top">
                                        <th scope="row"><label for="enable_funding"><?php _e('Enabled Funding Sources', 'checkout-for-paypal');?></label></th>
                                        <td><textarea name="enable_funding" id="enable_funding" class="large-text"><?php echo esc_html($enable_funding); ?></textarea>
                                            <p class="description"><?php echo __('Enabled funding sources in comma-separated format (optional).', 'checkout-for-paypal').' ';?>Example: <strong>venmo</strong> or <strong>venmo,credit</strong> or <strong>venmo,credit,paylater</strong>.<?php echo ' '.__('This is not required as the eligibility is determined automatically. However, this field can be used to ensure a funding source is always rendered, if eligible.', 'checkout-for-paypal').' '.wp_kses($funding_src_doc_link, $allowed_html_tags);?></p></td>
                                    </tr>
                                    
                                    <tr valign="top">
                                        <th scope="row"><label for="disable_funding"><?php _e('Disabled Funding Sources', 'checkout-for-paypal');?></label></th>
                                        <td><textarea name="disable_funding" id="disable_funding" class="large-text"><?php echo esc_html($disable_funding); ?></textarea>
                                            <p class="description"><?php echo __('Disabled funding sources in comma-separated format (optional).', 'checkout-for-paypal').' ';?>Example: <strong>card</strong> or <strong>card,credit</strong> or <strong>card,credit,paylater</strong>.<?php echo ' '.__('Any funding sources that you enter here are not displayed as buttons at checkout.', 'checkout-for-paypal').' '.wp_kses($funding_src_doc_link, $allowed_html_tags);?></p></td>
                                    </tr>

                                </tbody>

                            </table>

                            <p class="submit"><input type="submit" name="checkout_for_paypal_update_settings" id="checkout_for_paypal_update_settings" class="button button-primary" value="<?php _e('Save Changes', 'checkout-for-paypal');?>"></p></form>
                    </td>
                    <td valign="top" style="width: 300px">
                        <div style="background: #ffc; border: 1px solid #333; margin: 2px; padding: 3px 15px">
                        <h3><?php _e('Need More Features?', 'checkout-for-paypal')?></h3>
                        <ol>
                        <li><?php printf(__('Check out the <a href="%s">plugin add-ons</a>.', 'checkout-for-paypal'), 'edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-addons');?></li>
                        </ol>    
                        <h3><?php _e('Need Help?', 'checkout-for-paypal')?></h3>
                        <ol>
                        <li><?php printf(__('Use the <a href="%s">Debug</a> menu for diagnostics.', 'checkout-for-paypal'), 'edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-debug');?></li>
                        <li><?php printf(__('Visit the <a target="_blank" href="%s">plugin homepage</a>.', 'checkout-for-paypal'), 'https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/');?></li>
                        </ol>
                        <h3><?php _e('Rate This Plugin', 'checkout-for-paypal')?></h3>
                        <p><?php printf(__('Please <a target="_blank" href="%s">rate us</a> and give feedback.', 'checkout-for-paypal'), 'https://wordpress.org/support/plugin/checkout-for-paypal/reviews?rate=5#new-post');?></p>
                        </div>
                    </td>
                </tr>
            </tbody> 
        </table>        
        <?php
    }
    
    function email_settings() 
    {
        if (isset($_POST['checkout_for_paypal_update_email_settings'])) 
        {
            $nonce = $_REQUEST['_wpnonce'];
            if (!wp_verify_nonce($nonce, 'checkout_for_paypal_email_settings_nonce')) {
                wp_die(__('Error! Nonce Security Check Failed! please save the email settings again.', 'checkout-for-paypal'));
            }
            $_POST = stripslashes_deep($_POST);
            $email_from_name = '';
            if(isset($_POST['email_from_name']) && !empty($_POST['email_from_name'])){
                $email_from_name = sanitize_text_field($_POST['email_from_name']);
            }
            $email_from_address= '';
            if(isset($_POST['email_from_address']) && !empty($_POST['email_from_address'])){
                $email_from_address = sanitize_email($_POST['email_from_address']);
            }
            $purchase_email_enabled = (isset($_POST["purchase_email_enabled"]) && $_POST["purchase_email_enabled"] == '1') ? '1' : '';
            $purchase_email_subject = '';
            if(isset($_POST['purchase_email_subject']) && !empty($_POST['purchase_email_subject'])){
                $purchase_email_subject = sanitize_text_field($_POST['purchase_email_subject']);
            }
            $purchase_email_type = '';
            if(isset($_POST['purchase_email_type']) && !empty($_POST['purchase_email_type'])){
                $purchase_email_type = sanitize_text_field($_POST['purchase_email_type']);
            }
            $purchase_email_body = '';
            if(isset($_POST['purchase_email_body']) && !empty($_POST['purchase_email_body'])){
                $purchase_email_body = wp_kses_post($_POST['purchase_email_body']);
            }
            $sale_notification_email_enabled = (isset($_POST["sale_notification_email_enabled"]) && $_POST["sale_notification_email_enabled"] == '1') ? '1' : '';
            $sale_notification_email_recipient = '';
            if(isset($_POST['sale_notification_email_recipient']) && !empty($_POST['sale_notification_email_recipient'])){
                $sale_notification_email_recipient = sanitize_text_field($_POST['sale_notification_email_recipient']);
            }
            $sale_notification_email_subject = '';
            if(isset($_POST['sale_notification_email_subject']) && !empty($_POST['sale_notification_email_subject'])){
                $sale_notification_email_subject = sanitize_text_field($_POST['sale_notification_email_subject']);
            }
            $sale_notification_email_type = '';
            if(isset($_POST['sale_notification_email_type']) && !empty($_POST['sale_notification_email_type'])){
                $sale_notification_email_type = sanitize_text_field($_POST['sale_notification_email_type']);
            }
            $sale_notification_email_body = '';
            if(isset($_POST['sale_notification_email_body']) && !empty($_POST['sale_notification_email_body'])){
                $sale_notification_email_body = wp_kses_post($_POST['sale_notification_email_body']);
            }
            $paypal_options = array();
            $paypal_options['email_from_name'] = $email_from_name;
            $paypal_options['email_from_address'] = $email_from_address;
            $paypal_options['purchase_email_enabled'] = $purchase_email_enabled;
            $paypal_options['purchase_email_subject'] = $purchase_email_subject;
            $paypal_options['purchase_email_type'] = $purchase_email_type;
            $paypal_options['purchase_email_body'] = $purchase_email_body;
            $paypal_options['sale_notification_email_enabled'] = $sale_notification_email_enabled;
            $paypal_options['sale_notification_email_recipient'] = $sale_notification_email_recipient;
            $paypal_options['sale_notification_email_subject'] = $sale_notification_email_subject;
            $paypal_options['sale_notification_email_type'] = $sale_notification_email_type;
            $paypal_options['sale_notification_email_body'] = $sale_notification_email_body;
            checkout_for_paypal_update_email_option($paypal_options);
            echo '<div id="message" class="updated fade"><p><strong>';
            echo __('Settings Saved', 'checkout-for-paypal').'!';
            echo '</strong></p></div>';
        }
        
        $paypal_options = checkout_for_paypal_get_email_option();
        
        $email_tags_url = "https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/";
        $email_tags_link = sprintf(__('You can find the full list of available email tags <a target="_blank" href="%s">here</a>.', 'checkout-for-paypal'), esc_url($email_tags_url));
        $allowed_html_tags = array(
            'a' => array(
                'href' => array(),
                'target' => array()
            )
        );
        ?>
        <table class="coforpaypal-email-settings-table">
            <tbody>
                <tr>
                    <td valign="top">
                        <form method="post" action="">
                            <?php wp_nonce_field('checkout_for_paypal_email_settings_nonce'); ?>

                            <h2><?Php _e('Email Sender Options', 'checkout-for-paypal');?></h2>
                            <table class="form-table">
                                <tbody>                   
                                    <tr valign="top">
                                        <th scope="row"><label for="email_from_name"><?Php _e('From Name', 'checkout-for-paypal');?></label></th>
                                        <td><input name="email_from_name" type="text" id="email_from_name" value="<?php echo esc_attr($paypal_options['email_from_name']); ?>" class="regular-text">
                                            <p class="description"><?Php _e('The sender name that appears in outgoing emails. Leave empty to use the default.', 'checkout-for-paypal');?></p></td>
                                    </tr>                
                                    <tr valign="top">
                                        <th scope="row"><label for="email_from_address"><?Php _e('From Email Address', 'checkout-for-paypal');?></label></th>
                                        <td><input name="email_from_address" type="text" id="email_from_address" value="<?php echo esc_attr($paypal_options['email_from_address']); ?>" class="regular-text">
                                            <p class="description"><?Php _e('The sender email that appears in outgoing emails. Leave empty to use the default.', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                </tbody>
                            </table>
                            <h2><?Php _e('Purchase Receipt Email', 'checkout-for-paypal');?></h2>
                            <p><?Php _e('A purchase receipt email is sent to the customer after completion of a successful purchase', 'checkout-for-paypal');?></p>
                            <table class="form-table">
                                <tbody>
                                    <tr valign="top">
                                        <th scope="row"><?Php _e('Enable/Disable', 'checkout-for-paypal');?></th>
                                        <td> <fieldset><legend class="screen-reader-text"><span>Enable/Disable</span></legend><label for="purchase_email_enabled">
                                                    <input name="purchase_email_enabled" type="checkbox" id="purchase_email_enabled" <?php if ($paypal_options['purchase_email_enabled'] == '1') echo ' checked="checked"'; ?> value="1">
                                                    <?Php _e('Enable this email notification', 'checkout-for-paypal');?></label>
                                            </fieldset></td>
                                    </tr>                   
                                    <tr valign="top">
                                        <th scope="row"><label for="purchase_email_subject"><?Php _e('Subject', 'checkout-for-paypal');?></label></th>
                                        <td><input name="purchase_email_subject" type="text" id="purchase_email_subject" value="<?php echo esc_attr($paypal_options['purchase_email_subject']); ?>" class="regular-text">
                                            <p class="description"><?Php _e('The subject line for the purchase receipt email.', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="purchase_email_type"><?php _e('Email Type', 'checkout-for-paypal');?></label></th>
                                        <td>
                                        <select name="purchase_email_type" id="purchase_email_type">
                                            <option <?php echo ($paypal_options['purchase_email_type'] === 'plain')?'selected="selected"':'';?> value="plain"><?php _e('Plain Text', 'checkout-for-paypal')?></option>
                                            <option <?php echo ($paypal_options['purchase_email_type'] === 'html')?'selected="selected"':'';?> value="html"><?php _e('HTML', 'checkout-for-paypal')?></option>
                                        </select>
                                        <p class="description"><?php _e('The content type of the purchase receipt email.', 'checkout-for-paypal')?></p>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="purchase_email_body"><?Php _e('Email Body', 'checkout-for-paypal');?></label></th>
                                        <td><?php wp_editor($paypal_options['purchase_email_body'], 'purchase_email_body', array('textarea_name' => 'purchase_email_body'));?>
                                            <p class="description"><?Php echo __('The main content of the purchase receipt email.', 'checkout-for-paypal').' '.wp_kses($email_tags_link, $allowed_html_tags);?></p></td>
                                    </tr>
                                </tbody>
                            </table>
                            <h2><?Php _e('Sale Notification Email', 'checkout-for-paypal');?></h2>
                            <p><?Php _e('A sale notification email is sent to the chosen recipient after completion of a successful purchase', 'checkout-for-paypal');?></p>
                            <table class="form-table">
                                <tbody>
                                    <tr valign="top">
                                        <th scope="row"><?Php _e('Enable/Disable', 'checkout-for-paypal');?></th>
                                        <td> <fieldset><legend class="screen-reader-text"><span>Enable/Disable</span></legend><label for="sale_notification_email_enabled">
                                                    <input name="sale_notification_email_enabled" type="checkbox" id="sale_notification_email_enabled" <?php if ($paypal_options['sale_notification_email_enabled'] == '1') echo ' checked="checked"'; ?> value="1">
                                                    <?Php _e('Enable this email notification', 'checkout-for-paypal');?></label>
                                            </fieldset></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="sale_notification_email_recipient"><?Php _e('Recipient', 'checkout-for-paypal');?></label></th>
                                        <td><input name="sale_notification_email_recipient" type="text" id="sale_notification_email_recipient" value="<?php echo esc_attr($paypal_options['sale_notification_email_recipient']); ?>" class="regular-text">
                                            <p class="description"><?Php _e('The email address that should receive a notification anytime a sale is made. Multiple recipients can be specified by separating the addresses with a comma.', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="sale_notification_email_subject"><?Php _e('Subject', 'checkout-for-paypal');?></label></th>
                                        <td><input name="sale_notification_email_subject" type="text" id="sale_notification_email_subject" value="<?php echo esc_attr($paypal_options['sale_notification_email_subject']); ?>" class="regular-text">
                                            <p class="description"><?Php _e('The subject line for the sale notification email.', 'checkout-for-paypal');?></p></td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="sale_notification_email_type"><?php _e('Email Type', 'checkout-for-paypal');?></label></th>
                                        <td>
                                        <select name="sale_notification_email_type" id="sale_notification_email_type">
                                            <option <?php echo ($paypal_options['sale_notification_email_type'] === 'plain')?'selected="selected"':'';?> value="plain"><?php _e('Plain Text', 'checkout-for-paypal')?></option>
                                            <option <?php echo ($paypal_options['sale_notification_email_type'] === 'html')?'selected="selected"':'';?> value="html"><?php _e('HTML', 'checkout-for-paypal')?></option>
                                        </select>
                                        <p class="description"><?php _e('The content type of the sale notification email.', 'checkout-for-paypal')?></p>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row"><label for="sale_notification_email_body"><?Php _e('Email Body', 'checkout-for-paypal');?></label></th>
                                        <td><?php wp_editor($paypal_options['sale_notification_email_body'], 'sale_notification_email_body', array('textarea_name' => 'sale_notification_email_body'));?>
                                            <p class="description"><?Php echo __('The main content of the sale notification email.', 'checkout-for-paypal').' '.wp_kses($email_tags_link, $allowed_html_tags);?></p></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <p class="submit"><input type="submit" name="checkout_for_paypal_update_email_settings" id="checkout_for_paypal_update_email_settings" class="button button-primary" value="<?Php _e('Save Changes', 'checkout-for-paypal');?>"></p></form>
                    </td>
                    <td valign="top" style="width: 300px">
                        <div style="background: #ffc; border: 1px solid #333; margin: 2px; padding: 3px 15px">
                        <h3><?php _e('Need More Features?', 'checkout-for-paypal')?></h3>
                        <ol>
                        <li><?php printf(__('Check out the <a href="%s">plugin add-ons</a>.', 'checkout-for-paypal'), 'edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-addons');?></li>
                        </ol>    
                        <h3><?php _e('Need Help?', 'checkout-for-paypal')?></h3>
                        <ol>
                        <li><?php printf(__('Use the <a href="%s">Debug</a> menu for diagnostics.', 'checkout-for-paypal'), 'edit.php?post_type=coforpaypal_order&page=checkout-for-paypal-debug');?></li>
                        <li><?php printf(__('Visit the <a target="_blank" href="%s">plugin homepage</a>.', 'checkout-for-paypal'), 'https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/');?></li>
                        </ol>
                        <h3><?php _e('Rate This Plugin', 'checkout-for-paypal')?></h3>
                        <p><?php printf(__('Please <a target="_blank" href="%s">rate us</a> and give feedback.', 'checkout-for-paypal'), 'https://wordpress.org/support/plugin/checkout-for-paypal/reviews?rate=5#new-post');?></p>
                        </div>
                    </td>
                </tr>
            </tbody> 
        </table>
        <?php
    }

    function debug_page() {
        ?>
        <div class="wrap">
            <h2><?php _e('Checkout for PayPal Debug Log', 'checkout-for-paypal');?></h2>
            <div id="poststuff">
                <div id="post-body">
                    <?php
                    if (isset($_POST['checkout_for_paypal_update_log_settings'])) {
                        $nonce = sanitize_text_field($_REQUEST['_wpnonce']);
                        if (!wp_verify_nonce($nonce, 'checkout_for_paypal_debug_log_settings')) {
                            wp_die(__('Error! Nonce Security Check Failed! please save the debug settings again.', 'checkout-for-paypal'));
                        }
                        $options = array();
                        $options['enable_debug'] = (isset($_POST["enable_debug"]) && $_POST["enable_debug"] == '1') ? '1' : '';
                        checkout_for_paypal_update_option($options);
                        echo '<div id="message" class="updated fade"><p>'.__('Settings Saved', 'checkout-for-paypal').'!</p></div>';
                    }
                    if (isset($_POST['checkout_for_paypal_reset_log'])) {
                        $nonce = $_REQUEST['_wpnonce'];
                        if (!wp_verify_nonce($nonce, 'checkout_for_paypal_reset_log_settings')) {
                            wp_die(__('Error! Nonce Security Check Failed! please reset the debug log file again.', 'checkout-for-paypal'));
                        }
                        if (checkout_for_paypal_reset_log()) {
                            echo '<div id="message" class="updated fade"><p>'.__('Debug log file has been reset', 'checkout-for-paypal').'!</p></div>';
                        } else {
                            echo '<div id="message" class="error"><p>'.__('Debug log file could not be reset', 'checkout-for-paypal').'!</p></div>';
                        }
                    }
                    $log_file = CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH;
                    $content = '';
                    if(file_exists($log_file))
                    {
                        $content = file_get_contents($log_file);
                    }
                    $options = checkout_for_paypal_get_option();
                    ?>
                    <div id="template"><textarea cols="70" rows="25" name="checkout_for_paypal_log" id="checkout_for_paypal_log"><?php echo esc_textarea($content); ?></textarea></div>                     
                    <form method="post" action="">
                        <?php wp_nonce_field('checkout_for_paypal_debug_log_settings'); ?>
                        <table class="form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><?php _e('Enable Debug', 'checkout-for-paypal');?></th>
                                    <td> <fieldset><legend class="screen-reader-text"><span>Enable Debug</span></legend><label for="enable_debug">
                                                <input name="enable_debug" type="checkbox" id="enable_debug" <?php if ($options['enable_debug'] == '1') echo ' checked="checked"'; ?> value="1">
                                                <?php _e('Check this option if you want to enable debug', 'checkout-for-paypal');?></label>
                                        </fieldset></td>
                                </tr>

                            </tbody>

                        </table>
                        <p class="submit"><input type="submit" name="checkout_for_paypal_update_log_settings" id="checkout_for_paypal_update_log_settings" class="button button-primary" value="<?php _e('Save Changes', 'checkout-for-paypal');?>"></p>
                    </form>
                    <form method="post" action="">
                        <?php wp_nonce_field('checkout_for_paypal_reset_log_settings'); ?>                            
                        <p class="submit"><input type="submit" name="checkout_for_paypal_reset_log" id="checkout_for_paypal_reset_log" class="button" value="<?php _e('Reset Log', 'checkout-for-paypal');?>"></p>
                    </form>
                </div>         
            </div>
        </div>
        <?php
    }

}

$GLOBALS['checkout_for_paypal'] = new CHECKOUT_FOR_PAYPAL();

function checkout_for_paypal_button_handler($atts) {
    $atts = array_map('sanitize_text_field', $atts);
    $description = '';
    /*
    if(!isset($atts['item_description']) || empty($atts['item_description'])){
        return __('You need to provide a valid description', 'checkout-for-paypal');
    }
    */
    if(isset($atts['item_description']) && !empty($atts['item_description'])){
        $description = $atts['item_description'];
    }
    $dynamic_button = false;
    if(isset($atts['dynamic_button']) && !empty($atts['dynamic_button'])){
        $dynamic_button = true;
    }
    if($dynamic_button){
        $description = apply_filters('cfp_dynamic_button_description', $description, $atts);
    }
    $options = checkout_for_paypal_get_option();
    $currency = $options['currency_code'];
    /* There seems to be a bug where currency override doesn't work on a per button basis
    if(isset($atts['currency']) && !empty($atts['currency'])){
        $currency = $atts['currency'];
    }
    */
    $return_url = (isset($options['return_url']) && !empty($options['return_url'])) ? $options['return_url'] : '';
    if(isset($atts['return_url']) && !empty($atts['return_url'])){
        $return_url = $atts['return_url'];
    }
    $return_output = '';
    if(!empty($return_url)){
        $return_output = 'window.location.replace("'.$return_url.'");';
    }
    $cancel_url = (isset($options['cancel_url']) && !empty($options['cancel_url'])) ? $options['cancel_url'] : '';
    if(isset($atts['cancel_url']) && !empty($atts['cancel_url'])){
        $cancel_url = $atts['cancel_url'];
    }
    $cancel_output = '';
    if(!empty($cancel_url)){
        $cancel_output = 'window.location.replace("'.$cancel_url.'");';
    }
    $no_shipping = '';
    if(isset($atts['no_shipping']) && $atts['no_shipping']=='1'){
        $no_shipping .= <<<EOT
        application_context: {
            shipping_preference: "NO_SHIPPING",
        },        
EOT;
    }
    $width = '300';
    if(isset($atts['width']) && !empty($atts['width'])){
        $width = $atts['width'];
    }
    $layout = 'vertical';
    if(isset($atts['layout']) && $atts['layout'] == 'horizontal'){
        $layout = 'horizontal';
    }
    $color = 'gold';
    if(isset($atts['color']) && $atts['color'] == 'blue'){
        $color = 'blue';
    }
    else if(isset($atts['color']) && $atts['color'] == 'silver'){
        $color = 'silver';
    }
    else if(isset($atts['color']) && $atts['color'] == 'white'){
        $color = 'white';
    }
    else if(isset($atts['color']) && $atts['color'] == 'black'){
        $color = 'black';
    }
    $shape = 'rect';
    if(isset($atts['shape']) && $atts['shape'] == 'pill'){
        $shape = 'pill';
    }
    $id = uniqid();
    $atts['id'] = $id;
    $button_code = '';
    $button_code = apply_filters('checkout_for_paypal_button', $button_code, $atts);
    if(!empty($button_code)){
        return $button_code;
    }
    if(!isset($atts['amount']) || !is_numeric($atts['amount'])){
        return __('You need to provide a valid price amount', 'checkout-for-paypal');
    }
    $amount = $atts['amount'];
    if($dynamic_button){
        $amount = apply_filters('cfp_dynamic_button_amount', $amount, $atts);
    }
    $break_down_amount = 'false';
    $shipping = '';
    if(isset($atts['shipping']) && is_numeric($atts['shipping'])){
        $shipping = $atts['shipping'];
        $break_down_amount = 'true';
    }
    $esc_js = 'esc_js';
    $button_id = 'coforpaypal-button-'.$id;
    $button_container_id = 'coforpaypal-button-container-'.$id;
    $button_code = '<div id="'.esc_attr($button_container_id).'" style="'.esc_attr('max-width: '.$width.'px;').'">';
    $button_code .= '<div id="'.esc_attr($button_id).'" style="'.esc_attr('max-width: '.$width.'px;').'"></div>';
    $button_code .= '</div>';
    $ajax_url = admin_url('admin-ajax.php');
    $button_code .= <<<EOT
    <script>
    jQuery(document).ready(function() {
            
        function initPayPalButton{$id}() {
            var description = "{$esc_js($description)}";
            var amount = "{$esc_js($amount)}";
            var totalamount = 0;
            var shipping = "{$esc_js($shipping)}";
            var currency = "{$esc_js($currency)}";
            var break_down_amount = {$esc_js($break_down_amount)};
            
            var purchase_units = [];
            purchase_units[0] = {};
            purchase_units[0].amount = {};
            
            paypal.Buttons({
                style: {
                    layout: '{$layout}',
                    color: '{$color}',
                    shape: '{$shape}'
                },
                onInit: function (data, actions) {

                },  
                
                onClick: function () {
                    purchase_units[0].description = description;
                    purchase_units[0].amount.value = amount;
                    if(break_down_amount){
                        purchase_units[0].amount.breakdown = {};
                        purchase_units[0].amount.breakdown.item_total = {};
                        purchase_units[0].amount.breakdown.item_total.currency_code = currency;
                        purchase_units[0].amount.breakdown.item_total.value = amount;
                    }
                    if(shipping.length !== 0 && !isNaN(shipping)){
                        purchase_units[0].amount.breakdown.shipping = {};
                        purchase_units[0].amount.breakdown.shipping.currency_code = currency;
                        purchase_units[0].amount.breakdown.shipping.value = shipping;
                        totalamount = parseFloat(amount)+parseFloat(shipping);
                    }
                    if(totalamount > 0){
                        purchase_units[0].amount.value = String(totalamount);
                    }
                },    
                    
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: purchase_units,
                        $no_shipping    
                    });
                },
                            
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        //console.log('Transaction completed by ' + details.payer.name.given_name);
                        //console.log(details);
                        var data = {
                            'action': "coforpaypal_ajax_process_order",
                            'coforpaypal_ajax_process_order': "1",
                            'details': details 
                        };  
                        jQuery.ajax({
                            url : "{$ajax_url}",
                            type : "POST",
                            data : data,
                            success: function(response) {
                                //console.log(response);
                                $return_output
                            }
                        });
                    });
                },
                                    
                onError: function (err) {
                    console.log(err);
                },
                                    
                onCancel: function (data) {
                    $cancel_output
                }
                    
            }).render('#$button_id');
        }
        initPayPalButton{$id}();
    });                     
    </script>        
EOT;
    
    return $button_code;
}

function checkout_for_paypal_ajax_process_order(){
    checkout_for_paypal_debug_log('Received a response from frontend', true);
    if(!isset($_POST['coforpaypal_ajax_process_order'])){
        wp_die();
    }
    checkout_for_paypal_debug_log('Received a notification from PayPal', true);
    $post_data = $_POST;
    array_walk_recursive($post_data, function(&$v) { $v = sanitize_text_field($v); });
    checkout_for_paypal_debug_log_array($post_data, true);
    if(!isset($post_data['details'])){
        checkout_for_paypal_debug_log("No transaction details. This payment cannot be processed.", false);
        wp_die();
    }
    //
    do_action('checkout_for_paypal_process_order', $post_data);
    wp_die();
}

function checkout_for_paypal_process_order_handler($post_data)
{
    $details = $post_data['details'];
    if(!isset($details['payer'])){
        checkout_for_paypal_debug_log("No payer data. This payment cannot be processed.", false);
        return;
    }
    $payer = $details['payer'];
    if(!isset($details['purchase_units'][0])){
        checkout_for_paypal_debug_log("No purchase unit data. This payment cannot be processed.", false);
        return;
    }
    $purchase_units = $details['purchase_units'][0];
    if(!isset($purchase_units['payments']['captures'][0])){
        checkout_for_paypal_debug_log("No payment capture data. This payment cannot be processed.", false);
        return;
    }
    $capture = $purchase_units['payments']['captures'][0];
    $payment_status = '';
    if (isset($capture['status'])) {
        $payment_status = sanitize_text_field($capture['status']);
        checkout_for_paypal_debug_log("Payment Status - " . $payment_status, true);
    }
    if (isset($capture['status']['status_details']['reason'])) {
        $status_reason = sanitize_text_field($capture['status']['status_details']['reason']);
        checkout_for_paypal_debug_log("Reason - " . $status_reason, true);
    }
    $payment_data = array();
    $payment_data['txn_id'] = '';
    if (isset($capture['id'])) {
        $payment_data['txn_id'] = sanitize_text_field($capture['id']);
    } else {
        checkout_for_paypal_debug_log("No transaction ID. This payment cannot be processed.", false);
        return;
    }
    $args = array(
        'post_type' => 'coforpaypal_order',
        'meta_query' => array(
            array(
                'key' => '_txn_id',
                'value' => $payment_data['txn_id'],
                'compare' => '=',
            ),
        ),
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {  //a record already exists
        checkout_for_paypal_debug_log("An order with this transaction ID already exists. This payment will not be processed.", false);
        return;
    } 
    $payer_name = '';
    $payment_data['given_name'] = '';
    if (isset($payer['name']['given_name'])) {
        $payment_data['given_name'] = sanitize_text_field($payer['name']['given_name']);
        $payer_name .= $payment_data['given_name'];
    }
    $payment_data['surname'] = '';
    if (isset($payer['name']['surname'])) {
        $payment_data['surname'] = sanitize_text_field($payer['name']['surname']);
        $payer_name .= ' '.$payment_data['surname'];
    }
    $payment_data['payer_email'] = '';
    if (isset($payer['email_address'])) {
        $payment_data['payer_email'] = sanitize_email($payer['email_address']);
    }
    $payment_data['phone_number'] = '';
    if(isset($payer['phone']['phone_number']['national_number'])){
        $payment_data['phone_number'] = sanitize_text_field($payer['phone']['phone_number']['national_number']);
    }
    $payment_data['description'] = '';
    if (isset($purchase_units['description'])) {
        $payment_data['description'] = sanitize_text_field($purchase_units['description']);
    }
    $payment_data['amount'] = '';
    if (isset($purchase_units['amount']['value'])) {
        $payment_data['amount'] = sanitize_text_field($purchase_units['amount']['value']);
    }
    $payment_data['currency_code'] = '';
    if (isset($purchase_units['amount']['currency_code'])) {
        $payment_data['currency_code'] = sanitize_text_field($purchase_units['amount']['currency_code']);
    }
    $payment_data['item_total'] = $payment_data['amount'];
    if(isset($purchase_units['amount']['breakdown']['item_total']['value'])){
        $payment_data['item_total'] = sanitize_text_field($purchase_units['amount']['breakdown']['item_total']['value']);
    }
    $payment_data['shipping'] = '';
    if(isset($purchase_units['amount']['breakdown']['shipping']['value'])){
        $payment_data['shipping'] = sanitize_text_field($purchase_units['amount']['breakdown']['shipping']['value']);
    }
    $payment_data['shipping_name'] = '';
    if (isset($purchase_units['shipping']['name'])) {
        $payment_data['shipping_name'] = isset($purchase_units['shipping']['name']['full_name']) ? sanitize_text_field($purchase_units['shipping']['name']['full_name']) : '';
    }
    /*
    if(empty($ship_to_name)){
        $ship_to_name = $first_name.' '.$last_name;
    }
    */
    $ship_to = '';
    $shipping_address = '';
    if (isset($purchase_units['shipping']['address'])) {
        $address_street = isset($purchase_units['shipping']['address']['address_line_1']) ? sanitize_text_field($purchase_units['shipping']['address']['address_line_1']) : '';
        $ship_to .= !empty($address_street) ? $address_street.'<br />' : '';
        $shipping_address .= !empty($address_street) ? $address_street.', ' : '';
        
        $address_city = isset($purchase_units['shipping']['address']['admin_area_2']) ? sanitize_text_field($purchase_units['shipping']['address']['admin_area_2']) : '';
        $ship_to .= !empty($address_city) ? $address_city.', ' : '';
        $shipping_address .= !empty($address_city) ? $address_city.', ' : '';
        
        $address_state = isset($purchase_units['shipping']['address']['admin_area_1']) ? sanitize_text_field($purchase_units['shipping']['address']['admin_area_1']) : '';
        $ship_to .= !empty($address_state) ? $address_state.' ' : '';
        $shipping_address .= !empty($address_state) ? $address_state.' ' : '';
        
        $address_zip = isset($purchase_units['shipping']['address']['postal_code']) ? sanitize_text_field($purchase_units['shipping']['address']['postal_code']) : '';
        $ship_to .= !empty($address_zip) ? $address_zip.'<br />' : '';
        $shipping_address .= !empty($address_zip) ? $address_zip.', ' : '';
        
        $address_country = isset($purchase_units['shipping']['address']['country_code']) ? sanitize_text_field($purchase_units['shipping']['address']['country_code']) : '';
        $ship_to .= !empty($address_country) ? $address_country : '';
        $shipping_address .= !empty($address_country) ? $address_country : '';
    }
    $payment_data['shipping_address'] = $shipping_address;
    $checkout_for_paypal_order = array(
        'post_title' => 'order',
        'post_type' => 'coforpaypal_order',
        'post_content' => '',
        'post_status' => 'publish',
    );
    checkout_for_paypal_debug_log("Inserting order information", true);
    $post_id = wp_insert_post($checkout_for_paypal_order, true);
    if (is_wp_error($post_id)) {
        checkout_for_paypal_debug_log("Error inserting order information: ".$post_id->get_error_message(), false);
        return;
    }
    if (!$post_id) {
        checkout_for_paypal_debug_log("Order information could not be inserted", false);
        return;
    }
    $post_updated = false;
    if ($post_id > 0) {
        $post_content = '';
        if(!empty($payment_data['description'])){
            $post_content .= '<strong>Item Description:</strong> '.$payment_data['description'].'<br />';
        }
        if(!empty($payment_data['amount'])){
            $post_content .= '<strong>Amount:</strong> '.$payment_data['amount'].'<br />';
        }
        if(!empty($payment_data['item_total'])){
            $post_content .= '<strong>Item Total:</strong> '.$payment_data['item_total'].'<br />';
        }
        if(!empty($payment_data['shipping'])){
            $post_content .= '<strong>Shipping:</strong> '.$payment_data['shipping'].'<br />';
        }
        if(!empty($payment_data['currency_code'])){
            $post_content .= '<strong>Currency:</strong> '.$payment_data['currency_code'].'<br />';
        }
        if(!empty($payer_name)){
            $post_content .= '<strong>Payer Name:</strong> '.$payer_name.'<br />';
        }
        if(!empty($payment_data['payer_email'])){
            $post_content .= '<strong>Email:</strong> '.$payment_data['payer_email'].'<br />';
        }
        if(!empty($payment_data['phone_number'])){
            $post_content .= '<strong>Phone Number:</strong> '.$payment_data['phone_number'].'<br />';
        }
        if(!empty($ship_to)){
            $ship_to = '<h2>'.__('Ship To', 'checkout-for-paypal').'</h2><br />'.$payment_data['shipping_name'].'<br />'.$ship_to.'<br />';
        }
        $post_content .= $ship_to;
        $post_content .= '<h2>'.__('Payment Data', 'checkout-for-paypal').'</h2><br />';
        $post_content .= print_r($details, true);
        $updated_post = array(
            'ID' => $post_id,
            'post_title' => $post_id,
            'post_type' => 'coforpaypal_order',
            'post_content' => $post_content
        );
        $updated_post_id = wp_update_post($updated_post, true);
        if (is_wp_error($updated_post_id)) {
            checkout_for_paypal_debug_log("Error updating order information: ".$updated_post_id->get_error_message(), false);
            return;
        }
        if (!$updated_post_id) {
            checkout_for_paypal_debug_log("Order information could not be updated", false);
            return;
        }
        if ($updated_post_id > 0) {
            $post_updated = true;
        }
    }
    //save order information
    if ($post_updated) {
        update_post_meta($post_id, '_txn_id', $payment_data['txn_id']);
        update_post_meta($post_id, '_first_name', $payment_data['given_name']);
        update_post_meta($post_id, '_last_name', $payment_data['surname']);
        update_post_meta($post_id, '_email', $payment_data['payer_email']);
        update_post_meta($post_id, '_mc_gross', $payment_data['amount']);
        update_post_meta($post_id, '_payment_status', $payment_status);
        checkout_for_paypal_debug_log("Order information updated", true);
        
        $email_options = checkout_for_paypal_get_email_option();
        add_filter('wp_mail_from', 'checkout_for_paypal_set_email_from');
        add_filter('wp_mail_from_name', 'checkout_for_paypal_set_email_from_name');
        if(isset($email_options['purchase_email_enabled']) && !empty($email_options['purchase_email_enabled']) && !empty($payment_data['payer_email'])){
            $subject = $email_options['purchase_email_subject'];
            $type = $email_options['purchase_email_type'];
            $body = $email_options['purchase_email_body'];
            $body = checkout_for_paypal_do_email_tags($payment_data, $body);
            if($type == "html"){
                add_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
                $body = apply_filters('checkout_for_paypal_email_body_wpautop', true) ? wpautop($body) : $body;
            }
            checkout_for_paypal_debug_log("Sending a purchase receipt email to ".$payment_data['payer_email'], true);
            $mail_sent = wp_mail($payment_data['payer_email'], $subject, $body);
            if($type == "html"){
                remove_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
            }
            if($mail_sent == true){
                checkout_for_paypal_debug_log("Email was sent successfully by WordPress", true);
            }
            else{
                checkout_for_paypal_debug_log("Email could not be sent by WordPress", false);
            }
        }
        if(isset($email_options['sale_notification_email_enabled']) && !empty($email_options['sale_notification_email_enabled']) && !empty($email_options['sale_notification_email_recipient'])){
            $subject = $email_options['sale_notification_email_subject'];
            $type = $email_options['sale_notification_email_type'];
            $body = $email_options['sale_notification_email_body'];
            $body = checkout_for_paypal_do_email_tags($payment_data, $body);
            if($type == "html"){
                add_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
                $body = apply_filters('checkout_for_paypal_email_body_wpautop', true) ? wpautop($body) : $body;
            }
            $email_recipients = explode(",", $email_options['sale_notification_email_recipient']);
            foreach($email_recipients as $email_recipient){
                $to = sanitize_email($email_recipient);
                if(is_email($to)){
                    checkout_for_paypal_debug_log("Sending a sale notification email to ".$to, true);
                    $mail_sent = wp_mail($to, $subject, $body);
                    if($mail_sent == true){
                        checkout_for_paypal_debug_log("Email was sent successfully by WordPress", true);
                    }
                    else{
                        checkout_for_paypal_debug_log("Email could not be sent by WordPress", false);
                    }
                }
            }
            if($type == "html"){
                remove_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
            }
        }
        remove_filter('wp_mail_from', 'checkout_for_paypal_set_email_from');
        remove_filter('wp_mail_from_name', 'checkout_for_paypal_set_email_from_name');
        
        $details['post_order_id'] = $post_id;
        do_action('checkout_for_paypal_order_processed', $details);
    } else {
        checkout_for_paypal_debug_log("Order information could not be updated", false);
        return;
    }
    checkout_for_paypal_debug_log("Payment processing completed", true, true);   
    return;
}

function checkout_for_paypal_get_option(){
    $options = get_option('checkout_for_paypal_options');
    if(!is_array($options)){
        $options = checkout_for_paypal_get_empty_options_array();
    }
    return $options;
}

function checkout_for_paypal_update_option($new_options){
    $empty_options = checkout_for_paypal_get_empty_options_array();
    $options = checkout_for_paypal_get_option();
    if(is_array($options)){
        $current_options = array_merge($empty_options, $options);
        $updated_options = array_merge($current_options, $new_options);
        update_option('checkout_for_paypal_options', $updated_options);
    }
    else{
        $updated_options = array_merge($empty_options, $new_options);
        update_option('checkout_for_paypal_options', $updated_options);
    }
}

function checkout_for_paypal_get_empty_options_array(){
    $options = array();
    $options['app_client_id'] = '';
    $options['currency_code'] = '';
    $options['return_url'] = '';
    $options['cancel_url'] = '';
    $options['enable_venmo'] = '';
    $options['enable_funding'] = '';
    $options['disable_funding'] = '';
    $options['enable_debug'] = '';
    return $options;
}

function checkout_for_paypal_debug_log($msg, $success, $end = false) {
    if (!CHECKOUT_FOR_PAYPAL_DEBUG) {
        return;
    }
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - ' . (($success) ? 'SUCCESS :' : 'FAILURE :') . $msg . "\n";
    if ($end) {
        $text .= "\n------------------------------------------------------------------\n\n";
    }
    // Write to log.txt file
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'a');
    fwrite($fp, $text);
    fclose($fp);  // close file
}

function checkout_for_paypal_debug_log_array($array_msg, $success, $end = false) {
    if (!CHECKOUT_FOR_PAYPAL_DEBUG) {
        return;
    }
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - ' . (($success) ? 'SUCCESS :' : 'FAILURE :') . "\n";
    ob_start();
    print_r($array_msg);
    $var = ob_get_contents();
    ob_end_clean();
    $text .= $var;
    if ($end) {
        $text .= "\n------------------------------------------------------------------\n\n";
    }
    // Write to log.txt file
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'a');
    fwrite($fp, $text);
    fclose($fp);  // close filee
}

function checkout_for_paypal_reset_log() {
    $log_reset = true;
    $date_time = date('F j, Y g:i a');//the_date('F j, Y g:i a', '', '', FALSE);
    $text = '[' . $date_time . '] - SUCCESS : Log reset';
    $text .= "\n------------------------------------------------------------------\n\n";
    $fp = fopen(CHECKOUT_FOR_PAYPAL_DEBUG_LOG_PATH, 'w');
    if ($fp != FALSE) {
        @fwrite($fp, $text);
        @fclose($fp);
    } else {
        $log_reset = false;
    }
    return $log_reset;
}
