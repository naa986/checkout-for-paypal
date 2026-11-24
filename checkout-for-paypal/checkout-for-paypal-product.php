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
    $button_image = get_post_meta($post_id, '_coforpaypal_product_button_image', true);
    if(!isset($button_image) || empty($button_image)){
        $button_image = '';
    }
    $is_membership = get_post_meta($post_id, '_coforpaypal_product_is_membership', true);
    $membership_role = get_post_meta($post_id, '_coforpaypal_product_membership_role', true);
    if(empty($membership_role)){
        $membership_role = 'subscriber';
    }
    $membership_duration = get_post_meta($post_id, '_coforpaypal_product_membership_duration', true);
    if(!isset($membership_duration)){
        $membership_duration = '';
    }
    
    wp_nonce_field(basename(__FILE__), 'coforpaypal_product_details_meta_box_nonce');
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
                    <p class="description"><?php _e('The text displayed on the plain payment button (optional). Example: Buy Now', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>
            
            <tr valign="top">
                <th scope="row">
                    <label for="_coforpaypal_product_button_image"><?php _e('Button Image', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <input name="_coforpaypal_product_button_image" type="text" id="_coforpaypal_product_button_image" value="<?php echo esc_url($button_image); ?>" class="regular-text" placeholder="https://example.com/uploads/button.png">
                    <button type="button" class="button" id="coforpaypal_product_button_image_upload_btn"><?php _e('Select Image', 'checkout-for-paypal'); ?></button>
                    <p class="description"><?php _e('The URL of the image that will act as the payment button (optional). This replaces the plain payment button.', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row">
                    <?php _e('Membership / User Registration', 'checkout-for-paypal'); ?>
                </th>
                <td>
                    <fieldset>
                        <label for="_coforpaypal_product_is_membership">
                            <input name="_coforpaypal_product_is_membership" type="checkbox" id="_coforpaypal_product_is_membership" value="1" <?php checked($is_membership, '1'); ?>>
                            <?php _e('Enable Membership Registration for this product', 'checkout-for-paypal'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, a WordPress user account and member record will be created automatically for the customer upon successful payment.', 'checkout-for-paypal'); ?></p>
                    </fieldset>
                </td>
            </tr>

            <tr valign="top" class="coforpaypal_membership_settings_row" style="<?php echo ($is_membership == '1') ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="_coforpaypal_product_membership_role"><?php _e('Membership Role', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <select name="_coforpaypal_product_membership_role" id="_coforpaypal_product_membership_role">
                        <?php
                        $editable_roles = function_exists('get_editable_roles') ? get_editable_roles() : array();
                        if (empty($editable_roles)) {
                            global $wp_roles;
                            if (isset($wp_roles->roles)) {
                                $editable_roles = $wp_roles->roles;
                            }
                        }
                        foreach ($editable_roles as $role_key => $role_data) {
                            $selected = ($role_key === $membership_role) ? 'selected="selected"' : '';
                            echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html(translate_user_role($role_data['name'])) . ' (' . esc_html($role_key) . ')</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select the WordPress user role assigned to a newly created member upon checkout (only applied to new user accounts; existing users will not have their roles altered).', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top" class="coforpaypal_membership_settings_row" style="<?php echo ($is_membership == '1') ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="_coforpaypal_product_default_member_status"><?php _e('Default Membership Status', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <?php
                    $default_member_status = get_post_meta($post_id, '_coforpaypal_product_default_member_status', true);
                    if (empty($default_member_status)) {
                        $default_member_status = 'active';
                    }
                    ?>
                    <select name="_coforpaypal_product_default_member_status" id="_coforpaypal_product_default_member_status">
                        <option value="active" <?php selected($default_member_status, 'active'); ?>><?php _e('Active', 'checkout-for-paypal'); ?></option>
                        <option value="pending" <?php selected($default_member_status, 'pending'); ?>><?php _e('Pending', 'checkout-for-paypal'); ?></option>
                    </select>
                    <p class="description"><?php _e('Choose the default status when a member purchases this product. If "Pending" is chosen, the member cannot access protected content until their membership is switched to "Active" by an administrator.', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top" class="coforpaypal_membership_settings_row" style="<?php echo ($is_membership == '1') ? '' : 'display:none;'; ?>">
                <th scope="row">
                    <label for="_coforpaypal_product_membership_duration"><?php _e('Membership Duration (in days)', 'checkout-for-paypal'); ?></label>
                </th>
                <td>
                    <input name="_coforpaypal_product_membership_duration" type="number" min="0" step="1" id="_coforpaypal_product_membership_duration" value="<?php echo esc_attr($membership_duration); ?>" class="small-text">
                    <p class="description"><?php _e('Number of days this membership is valid for (e.g. 30, 365). Enter 0 or leave empty for lifetime (never expires).', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <script>
    jQuery(document).ready(function($) {
        $('#_coforpaypal_product_is_membership').on('change', function() {
            if ($(this).is(':checked')) {
                $('.coforpaypal_membership_settings_row').show();
            } else {
                $('.coforpaypal_membership_settings_row').hide();
            }
        });
    });
    </script>
    <script>
    jQuery(document).ready(function($) {
        var mediaUploader;

        $('#coforpaypal_product_button_image_upload_btn').on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: <?php echo wp_json_encode(__('Select Image', 'checkout-for-paypal')); ?>,
                button: { 
                    text: <?php echo wp_json_encode(__('Use this image', 'checkout-for-paypal')); ?> 
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#_coforpaypal_product_button_image').val(attachment.url);
            });

            mediaUploader.open();
        });
    });
    </script>
    <?php
    
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
    if (isset($_POST['_coforpaypal_product_button_image'])) {
        $button_image = esc_url_raw($_POST['_coforpaypal_product_button_image']);
        update_post_meta($post_id, '_coforpaypal_product_button_image', $button_image);
    }
    $is_membership = (isset($_POST['_coforpaypal_product_is_membership']) && $_POST['_coforpaypal_product_is_membership'] == '1') ? '1' : '';
    update_post_meta($post_id, '_coforpaypal_product_is_membership', $is_membership);
    if (isset($_POST['_coforpaypal_product_membership_role'])) {
        $membership_role = sanitize_text_field($_POST['_coforpaypal_product_membership_role']);
        update_post_meta($post_id, '_coforpaypal_product_membership_role', $membership_role);
    }
    if (isset($_POST['_coforpaypal_product_membership_duration'])) {
        $membership_duration = sanitize_text_field($_POST['_coforpaypal_product_membership_duration']);
        update_post_meta($post_id, '_coforpaypal_product_membership_duration', $membership_duration);
    }
    if (isset($_POST['_coforpaypal_product_default_member_status'])) {
        $default_status = sanitize_text_field($_POST['_coforpaypal_product_default_member_status']);
        update_post_meta($post_id, '_coforpaypal_product_default_member_status', $default_status);
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
    $button_image = get_post_meta($product_id, '_coforpaypal_product_button_image', true);
    $is_membership = get_post_meta($product_id, '_coforpaypal_product_is_membership', true);
    $membership_role = get_post_meta($product_id, '_coforpaypal_product_membership_role', true);
    if(empty($membership_role)){
        $membership_role = 'subscriber';
    }
    $membership_duration = get_post_meta($product_id, '_coforpaypal_product_membership_duration', true);
    $default_member_status = get_post_meta($product_id, '_coforpaypal_product_default_member_status', true);
    if(empty($default_member_status)){
        $default_member_status = 'active';
    }

    return array(
        'id'                     => $post->ID,
        'title'                  => get_the_title($post->ID),
        'description'            => $post->post_content,
        'price'                  => $price,
        'shipping'               => $shipping,
        'button_text'            => $button_text,
        'button_image'           => $button_image,
        'is_membership'          => $is_membership,
        'membership_role'        => $membership_role,
        'membership_duration'    => $membership_duration,
        'default_member_status'  => $default_member_status
    );
}
