<?php

function checkout_for_paypal_register_order_type() {
    $labels = array(
        'name' => __('Orders', 'checkout-for-paypal'),
        'singular_name' => __('Order', 'checkout-for-paypal'),
        'menu_name' => __('Checkout for PayPal', 'checkout-for-paypal'),
        'name_admin_bar' => __('Order', 'checkout-for-paypal'),
        'add_new' => __('Add New', 'checkout-for-paypal'),
        'add_new_item' => __('Add New Order', 'checkout-for-paypal'),
        'new_item' => __('New Order', 'checkout-for-paypal'),
        'edit_item' => __('Edit Order', 'checkout-for-paypal'),
        'view_item' => __('View Order', 'checkout-for-paypal'),
        'all_items' => __('All Orders', 'checkout-for-paypal'),
        'search_items' => __('Search Orders', 'checkout-for-paypal'),
        'parent_item_colon' => __('Parent Orders:', 'checkout-for-paypal'),
        'not_found' => __('No Orders found.', 'checkout-for-paypal'),
        'not_found_in_trash' => __('No orders found in Trash.', 'checkout-for-paypal')
    );
    
    $capability = 'manage_options';
    $capabilities = array(
        'edit_post' => $capability,
        'read_post' => $capability,
        'delete_post' => $capability,
        'create_posts' => $capability,
        'edit_posts' => $capability,
        'edit_others_posts' => $capability,
        'publish_posts' => $capability,
        'read_private_posts' => $capability,
        'read' => $capability,
        'delete_posts' => $capability,
        'delete_private_posts' => $capability,
        'delete_published_posts' => $capability,
        'delete_others_posts' => $capability,
        'edit_private_posts' => $capability,
        'edit_published_posts' => $capability
    );
    
    $args = array(
        'labels' => $labels,
        'public' => false,
        'exclude_from_search' => true,
 	'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_nav_menus' => false,
        'show_in_menu' => current_user_can('manage_options') ? true : false,
        'query_var' => false,
        'rewrite' => false,
        'capabilities' => $capabilities,
        'has_archive' => false,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('editor')
    );

    register_post_type('coforpaypal_order', $args);
}

function checkout_for_paypal_order_columns($columns) {
    unset($columns['title']);
    unset($columns['date']);
    $edited_columns = array(
        'title' => __('Order', 'checkout-for-paypal'),
        'txn_id' => __('Transaction ID', 'checkout-for-paypal'),
        'first_name' => __('First Name', 'checkout-for-paypal'),
        'last_name' => __('Last Name', 'checkout-for-paypal'),
        'email' => __('Email', 'checkout-for-paypal'),
        'mc_gross' => __('Total', 'checkout-for-paypal'),
        'payment_status' => __('Payment Status', 'checkout-for-paypal'),
        'date' => __('Date', 'checkout-for-paypal')
    );
    return array_merge($columns, $edited_columns);
}

function checkout_for_paypal_custom_column($column, $post_id) {
    switch ($column) {
        case 'title' :
            echo esc_html($post_id);
            break;
        case 'txn_id' :
            echo esc_html(get_post_meta($post_id, '_txn_id', true));
            break;
        case 'first_name' :
            echo esc_html(get_post_meta($post_id, '_first_name', true));
            break;
        case 'last_name' :
            echo esc_html(get_post_meta($post_id, '_last_name', true));
            break;
        case 'email' :
            echo esc_html(get_post_meta($post_id, '_email', true));
            break;
        case 'mc_gross' :
            echo esc_html(get_post_meta($post_id, '_mc_gross', true));
            break;
        case 'payment_status' :
            echo esc_html(get_post_meta($post_id, '_payment_status', true));
            break;
    }
}
function coforpaypal_order_meta_boxes($post){
    $post_type = 'coforpaypal_order';
    /** Product Data **/
    add_meta_box('coforpaypal_order_data', __('Order Data'),  'coforpaypal_render_order_data_meta_box', $post_type, 'normal', 'high');
}

function coforpaypal_render_order_data_meta_box($post){
    $post_id = $post->ID;
    $transaction_id = get_post_meta($post_id, '_txn_id', true);
    if(!isset($transaction_id) || empty($transaction_id)){
        $transaction_id = '';
    }
    $customer_first_name = get_post_meta($post_id, '_first_name', true);
    if(!isset($customer_first_name) || empty($customer_first_name)){
        $customer_first_name = '';
    }
    $customer_last_name = get_post_meta($post_id, '_last_name', true);
    if(!isset($customer_last_name) || empty($customer_last_name)){
        $customer_last_name = '';
    }
    $payer_email = get_post_meta($post_id, '_email', true);
    if(!isset($payer_email) || empty($payer_email)){
        $payer_email = '';
    }
    $total_amount = get_post_meta($post_id, '_mc_gross', true);
    if(!isset($total_amount) || !is_numeric($total_amount)){
        $total_amount = '';
    }
    $payment_status = get_post_meta($post_id, '_payment_status', true);
    if(!isset($payment_status) || empty($payment_status)){
        $payment_status = '';
    }
    ?>
    <table>
        <tbody>
            <tr>
                <td valign="top">
                    <table class="form-table">
                        <tbody>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_txn_id"><?php _e('Transaction ID', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_txn_id" type="text" id="_coforpaypal_order_txn_id" value="<?php echo esc_attr($transaction_id); ?>" class="regular-text"></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_first_name"><?php _e('First Name', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_first_name" type="text" id="_coforpaypal_order_first_name" value="<?php echo esc_attr($customer_first_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_last_name"><?php _e('Last Name', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_last_name" type="text" id="_coforpaypal_order_last_name" value="<?php echo esc_attr($customer_last_name); ?>" class="regular-text"></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_payer_email"><?php _e('Payer Email', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_payer_email" type="text" id="_coforpaypal_order_payer_email" value="<?php echo esc_attr($payer_email); ?>" class="regular-text"></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_mc_gross"><?php _e('Total Amount', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_mc_gross" type="text" id="_coforpaypal_order_mc_gross" value="<?php echo esc_attr($total_amount); ?>" class="regular-text"></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row"><label for="_coforpaypal_order_payment_status"><?php _e('Payment Status', 'checkout-for-paypal');?></label></th>
                                <td><input name="_coforpaypal_order_payment_status" type="text" id="_coforpaypal_order_payment_status" value="<?php echo esc_attr($payment_status); ?>" class="regular-text"></td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </tbody> 
    </table>
    <?php
    wp_nonce_field(basename(__FILE__), 'coforpaypal_order_data_meta_box_nonce');
}

function coforpaypal_order_data_meta_box_save($post_id, $post){
    if(!isset($_POST['coforpaypal_order_data_meta_box_nonce']) || !wp_verify_nonce($_POST['coforpaypal_order_data_meta_box_nonce'], basename(__FILE__))){
        return;
    }
    if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit'])){
        return;
    }
    if(isset($post->post_type) && 'revision' == $post->post_type){
        return;
    }
    if(!current_user_can('manage_options')){
        return;
    }
    //update the values
    if(isset($_POST['_coforpaypal_order_txn_id'])){
        $transaction_id = sanitize_text_field($_POST['_coforpaypal_order_txn_id']);
        update_post_meta($post_id, '_txn_id', $transaction_id);
    }
    if(isset($_POST['_coforpaypal_order_first_name'])){
        $customer_first_name = sanitize_text_field($_POST['_coforpaypal_order_first_name']);
        update_post_meta($post_id, '_first_name', $customer_first_name);
    }
    if(isset($_POST['_coforpaypal_order_last_name'])){
        $customer_last_name = sanitize_text_field($_POST['_coforpaypal_order_last_name']);
        update_post_meta($post_id, '_last_name', $customer_last_name);
    }
    if(isset($_POST['_coforpaypal_order_payer_email'])){
        $payer_email = sanitize_text_field($_POST['_coforpaypal_order_payer_email']);
        update_post_meta($post_id, '_email', $payer_email);
    }
    if(isset($_POST['_coforpaypal_order_mc_gross']) && is_numeric($_POST['_coforpaypal_order_mc_gross'])){
        $total_amount = sanitize_text_field($_POST['_coforpaypal_order_mc_gross']);
        update_post_meta($post_id, '_mc_gross', $total_amount);
    }
    if(isset($_POST['_coforpaypal_order_payment_status'])){
        $payment_status = sanitize_text_field($_POST['_coforpaypal_order_payment_status']);
        update_post_meta($post_id, '_payment_status', $payment_status);
    }
}

add_action('save_post_coforpaypal_order', 'coforpaypal_order_data_meta_box_save', 10, 2 );
