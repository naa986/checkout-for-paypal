<?php

if (!defined('ABSPATH')) {
    exit;
}

function checkout_for_paypal_register_product_type() {
    $labels = array(
        'name'               => __('Products', 'checkout-for-paypal'),
        'singular_name'      => __('Product', 'checkout-for-paypal'),
        'menu_name'          => __('Products', 'checkout-for-paypal'),
        'name_admin_bar'     => __('Product', 'checkout-for-paypal'),
        'add_new'            => __('Add New', 'checkout-for-paypal'),
        'add_new_item'       => __('Add New Product', 'checkout-for-paypal'),
        'new_item'           => __('New Product', 'checkout-for-paypal'),
        'edit_item'          => __('Edit Product', 'checkout-for-paypal'),
        'view_item'          => __('View Product', 'checkout-for-paypal'),
        'all_items'          => __('Products', 'checkout-for-paypal'),
        'search_items'       => __('Search Products', 'checkout-for-paypal'),
        'parent_item_colon'  => __('Parent Products:', 'checkout-for-paypal'),
        'not_found'          => __('No Products found.', 'checkout-for-paypal'),
        'not_found_in_trash' => __('No products found in Trash.', 'checkout-for-paypal')
    );
    
    $capability = 'manage_options';
    $capabilities = array(
        'edit_post'              => $capability,
        'read_post'              => $capability,
        'delete_post'            => $capability,
        'create_posts'           => $capability,
        'edit_posts'             => $capability,
        'edit_others_posts'      => $capability,
        'publish_posts'          => $capability,
        'read_private_posts'     => $capability,
        'read'                   => $capability,
        'delete_posts'           => $capability,
        'delete_private_posts'   => $capability,
        'delete_published_posts' => $capability,
        'delete_others_posts'    => $capability,
        'edit_private_posts'     => $capability,
        'edit_published_posts'   => $capability
    );
    
    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'menu_icon'           => 'dashicons-products',
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_nav_menus'   => false,
        'show_in_menu'        => current_user_can('manage_options') ? 'edit.php?post_type=coforpaypal_order' : false,
        'query_var'           => false,
        'rewrite'             => false,
        'capabilities'        => $capabilities,
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => null,
        'supports'            => array('title', 'editor')
    );

    register_post_type('coforpaypal_product', $args);
}

function checkout_for_paypal_product_columns($columns) {
    unset($columns['date']);
    $edited_columns = array(
        'title'      => __('Product Name', 'checkout-for-paypal'),
        'product_id' => __('Product ID', 'checkout-for-paypal'),
        'price'      => __('Price', 'checkout-for-paypal'),
        'shipping'   => __('Shipping', 'checkout-for-paypal'),
        'shortcode'  => __('Shortcode', 'checkout-for-paypal'),
        'date'       => __('Date', 'checkout-for-paypal')
    );
    return array_merge($columns, $edited_columns);
}

function checkout_for_paypal_product_custom_column($column, $post_id) {
    switch ($column) {
        case 'product_id':
            echo esc_html($post_id);
            break;
        case 'price':
            $price = get_post_meta($post_id, '_coforpaypal_product_price', true);
            if (isset($price) && is_numeric($price)) {
                echo esc_html($price);
            } else {
                echo '&#8212;';
            }
            break;
        case 'shipping':
            $shipping = get_post_meta($post_id, '_coforpaypal_product_shipping', true);
            if (isset($shipping) && is_numeric($shipping) && $shipping > 0) {
                echo esc_html($shipping);
            } else {
                echo '&#8212;';
            }
            break;
        case 'shortcode':
            $shortcode = '[coforpaypal_product id="'.$post_id.'"]';
            echo esc_html($shortcode);
            break;
    }
}

function checkout_for_paypal_product_meta_boxes($post) {
    $post_type = 'coforpaypal_product';
    add_meta_box(
        'coforpaypal_product_details',
        __('Product Details', 'checkout-for-paypal'),
        'checkout_for_paypal_render_product_details_meta_box',
        $post_type,
        'normal',
        'high'
    );
}

function checkout_for_paypal_render_product_details_meta_box($post) {
    $post_id = $post->ID;
    $price = get_post_meta($post_id, '_coforpaypal_product_price', true);
    if(!isset($price) || !is_numeric($price)){
        $price = '';
    }
    $shipping = get_post_meta($post_id, '_coforpaypal_product_shipping', true);
    if(!isset($shipping) || !is_numeric($shipping)){
        $shipping = '';
    }
    $button_text = get_post_meta($post_id, '_coforpaypal_product_button_text', true);
    if(!isset($button_text) || empty($button_text)){
        $button_text = '';
    }
    ?>
    <table class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row">
                    <label for="_coforpaypal_product_price"><?php _e('Price', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <input name="_coforpaypal_product_price" type="text" id="_coforpaypal_product_price" value="<?php echo esc_attr($price); ?>" class="regular-text" required>
                    <p class="description"><?php _e('The price of the product (required). Example: 7.75', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="_coforpaypal_product_shipping"><?php _e('Shipping Cost', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <input name="_coforpaypal_product_shipping" type="text" id="_coforpaypal_product_shipping" value="<?php echo esc_attr($shipping); ?>" class="regular-text">
                    <p class="description"><?php _e('shipping charge for this product (optional). Example: 1.65', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <label for="_coforpaypal_product_button_text"><?php _e('Button Text', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <input name="_coforpaypal_product_button_text" type="text" id="_coforpaypal_product_button_text" value="<?php echo esc_attr($button_text); ?>" class="regular-text">
                    <p class="description"><?php _e('The text displayed on the payment button (optional). Example: Buy Now', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
    wp_nonce_field(basename(__FILE__), 'coforpaypal_product_details_meta_box_nonce');
}

function checkout_for_paypal_product_details_meta_box_save($post_id, $post) {
    if (!isset($_POST['coforpaypal_product_details_meta_box_nonce']) || !wp_verify_nonce($_POST['coforpaypal_product_details_meta_box_nonce'], basename(__FILE__))) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || (defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit'])) {
        return;
    }
    if (isset($post->post_type) && 'revision' == $post->post_type) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['_coforpaypal_product_price'])) {
        $price = sanitize_text_field($_POST['_coforpaypal_product_price']);
        update_post_meta($post_id, '_coforpaypal_product_price', $price);
    }
    if (isset($_POST['_coforpaypal_product_shipping'])) {
        $shipping = sanitize_text_field($_POST['_coforpaypal_product_shipping']);
        update_post_meta($post_id, '_coforpaypal_product_shipping', $shipping);
    }
    if (isset($_POST['_coforpaypal_product_button_text'])) {
        $button_text = sanitize_text_field($_POST['_coforpaypal_product_button_text']);
        update_post_meta($post_id, '_coforpaypal_product_button_text', $button_text);
    }
}

add_action('save_post_coforpaypal_product', 'checkout_for_paypal_product_details_meta_box_save', 10, 2);

function checkout_for_paypal_get_product($product_id) {
    if (empty($product_id) || !is_numeric($product_id)) {
        return null;
    }
    $post = get_post($product_id);
    if (!$post || $post->post_type !== 'coforpaypal_product') {
        return null;
    }

    $price = get_post_meta($product_id, '_coforpaypal_product_price', true);
    $shipping = get_post_meta($product_id, '_coforpaypal_product_shipping', true);
    $button_text = get_post_meta($product_id, '_coforpaypal_product_button_text', true);

    return array(
        'id'          => $post->ID,
        'title'       => get_the_title($post->ID),
        'description' => $post->post_content,
        'price'       => $price,
        'shipping'    => $shipping,
        'button_text' => $button_text
    );
}
