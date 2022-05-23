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
