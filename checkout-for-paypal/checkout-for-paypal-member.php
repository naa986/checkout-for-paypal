<?php

if (!defined('ABSPATH')) {
    exit;
}

function checkout_for_paypal_register_member_type() {
    $labels = array(
        'name'               => __('Members', 'checkout-for-paypal'),
        'singular_name'      => __('Member', 'checkout-for-paypal'),
        'menu_name'          => __('Members', 'checkout-for-paypal'),
        'name_admin_bar'     => __('Member', 'checkout-for-paypal'),
        'add_new'            => __('Add New', 'checkout-for-paypal'),
        'add_new_item'       => __('Add New Member', 'checkout-for-paypal'),
        'new_item'           => __('New Member', 'checkout-for-paypal'),
        'edit_item'          => __('Edit Member', 'checkout-for-paypal'),
        'view_item'          => __('View Member', 'checkout-for-paypal'),
        'all_items'          => __('Members', 'checkout-for-paypal'),
        'search_items'       => __('Search Members', 'checkout-for-paypal'),
        'not_found'          => __('No Members found.', 'checkout-for-paypal'),
        'not_found_in_trash' => __('No members found in Trash.', 'checkout-for-paypal')
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
        'menu_icon'           => 'dashicons-admin-users',
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
        'supports'            => array('title')
    );

    register_post_type('coforpaypal_member', $args);
}

function checkout_for_paypal_member_columns($columns) {
    unset($columns['date']);
    $edited_columns = array(
        'title'           => __('Member', 'checkout-for-paypal'),
        'user_login'      => __('WordPress User', 'checkout-for-paypal'),
        'user_email'      => __('Email', 'checkout-for-paypal'),
        'product'         => __('Membership Level', 'checkout-for-paypal'),
        'status'          => __('Status', 'checkout-for-paypal'),
        'expiry_date'     => __('Expiry Date', 'checkout-for-paypal'),
        'date'            => __('Date Created', 'checkout-for-paypal')
    );
    return array_merge($columns, $edited_columns);
}

function checkout_for_paypal_member_custom_column($column, $post_id) {
    $user_id = get_post_meta($post_id, '_coforpaypal_member_user_id', true);
    $user = !empty($user_id) ? get_userdata($user_id) : null;
    
    switch ($column) {
        case 'user_login':
            if ($user) {
                echo '<a href="' . esc_url(get_edit_user_link($user_id)) . '"><strong>' . esc_html($user->user_login) . '</strong></a>';
            } elseif (!empty($user_id)) {
                echo esc_html(__('User ID: ', 'checkout-for-paypal') . $user_id);
            } else {
                echo '&#8212;';
            }
            break;

        case 'user_email':
            $email = get_post_meta($post_id, '_coforpaypal_member_email', true);
            if (empty($email) && $user) {
                $email = $user->user_email;
            }
            echo !empty($email) ? esc_html($email) : '&#8212;';
            break;

        case 'product':
            $prod_id = get_post_meta($post_id, '_coforpaypal_member_product_id', true);
            if (!empty($prod_id)) {
                $title = get_the_title($prod_id);
                echo '<a href="' . esc_url(get_edit_post_link($prod_id)) . '">' . esc_html($title ? $title : __('Product #', 'checkout-for-paypal') . $prod_id) . '</a>';
            } else {
                echo '&#8212;';
            }
            break;

        case 'status':
            $status = checkout_for_paypal_get_member_status($post_id);
            if ($status === 'active') {
                echo '<span style="color:green;font-weight:bold;">' . __('Active', 'checkout-for-paypal') . '</span>';
            } elseif ($status === 'pending') {
                echo '<span style="color:#e67e22;font-weight:bold;">' . __('Pending', 'checkout-for-paypal') . '</span>';
            } elseif ($status === 'expired') {
                echo '<span style="color:red;font-weight:bold;">' . __('Expired', 'checkout-for-paypal') . '</span>';
            } else {
                echo '<span style="color:#666;">' . esc_html(ucfirst($status)) . '</span>';
            }
            break;

        case 'expiry_date':
            $is_lifetime = get_post_meta($post_id, '_coforpaypal_member_is_lifetime', true);
            if ($is_lifetime == '1') {
                echo __('Never (Lifetime)', 'checkout-for-paypal');
            } else {
                $expiry = get_post_meta($post_id, '_coforpaypal_member_expiry_date', true);
                if (!empty($expiry)) {
                    $date_format = get_option('date_format') . ' ' . get_option('time_format');
                    echo esc_html(date_i18n($date_format, $expiry));
                } else {
                    echo '&#8212;';
                }
            }
            break;
    }
}

function checkout_for_paypal_get_member_status($member_post_id) {
    $manual_status = get_post_meta($member_post_id, '_coforpaypal_member_status', true);
    if (!empty($manual_status) && in_array($manual_status, array('pending', 'expired', 'suspended', 'cancelled'))) {
        return $manual_status;
    }
    $is_lifetime = get_post_meta($member_post_id, '_coforpaypal_member_is_lifetime', true);
    if ($is_lifetime == '1') {
        return 'active';
    }
    $expiry = get_post_meta($member_post_id, '_coforpaypal_member_expiry_date', true);
    if (!empty($expiry)) {
        return (time() <= (int)$expiry) ? 'active' : 'expired';
    }
    return 'active';
}

function checkout_for_paypal_member_meta_boxes($post) {
    add_meta_box(
        'coforpaypal_member_details',
        __('Membership Details', 'checkout-for-paypal'),
        'checkout_for_paypal_render_member_details_meta_box',
        'coforpaypal_member',
        'normal',
        'high'
    );
}

function checkout_for_paypal_render_member_details_meta_box($post) {
    $post_id = $post->ID;
    $user_id = get_post_meta($post_id, '_coforpaypal_member_user_id', true);
    $email = get_post_meta($post_id, '_coforpaypal_member_email', true);
    $product_id = get_post_meta($post_id, '_coforpaypal_member_product_id', true);
    $order_id = get_post_meta($post_id, '_coforpaypal_member_order_id', true);
    $is_lifetime = get_post_meta($post_id, '_coforpaypal_member_is_lifetime', true);
    $expiry_timestamp = get_post_meta($post_id, '_coforpaypal_member_expiry_date', true);
    $expiry_date_str = !empty($expiry_timestamp) ? date('Y-m-d\TH:i', (int)$expiry_timestamp) : '';
    $status = get_post_meta($post_id, '_coforpaypal_member_status', true);
    if (empty($status)) {
        $status = 'active';
    }

    wp_nonce_field(basename(__FILE__), 'coforpaypal_member_details_meta_box_nonce');
    ?>
    <table class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row"><label for="_coforpaypal_member_user_id"><?php _e('WordPress User ID', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <input name="_coforpaypal_member_user_id" type="number" id="_coforpaypal_member_user_id" value="<?php echo esc_attr($user_id); ?>" class="regular-text">
                    <?php if (!empty($user_id)) {
                        $user = get_userdata($user_id);
                        if ($user) {
                            echo '<p class="description">' . sprintf(__('Linked to User: <strong>%s</strong> (<a href="%s" target="_blank">Edit User</a>)', 'checkout-for-paypal'), esc_html($user->user_login), esc_url(get_edit_user_link($user_id))) . '</p>';
                        }
                    } ?>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="_coforpaypal_member_email"><?php _e('Member Email', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <input name="_coforpaypal_member_email" type="email" id="_coforpaypal_member_email" value="<?php echo esc_attr($email); ?>" class="regular-text">
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="_coforpaypal_member_product_id"><?php _e('Membership Level / Product', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <select name="_coforpaypal_member_product_id" id="_coforpaypal_member_product_id">
                        <option value=""><?php _e('-- Select Membership Product --', 'checkout-for-paypal'); ?></option>
                        <?php
                        $products = get_posts(array(
                            'post_type'      => 'coforpaypal_product',
                            'posts_per_page' => -1,
                            'post_status'    => 'publish'
                        ));
                        foreach ($products as $prod) {
                            $selected = ($prod->ID == $product_id) ? 'selected="selected"' : '';
                            echo '<option value="' . esc_attr($prod->ID) . '" ' . $selected . '>' . esc_html($prod->post_title) . ' (#' . $prod->ID . ')</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="_coforpaypal_member_is_lifetime"><?php _e('Lifetime Access', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <fieldset>
                        <label for="_coforpaypal_member_is_lifetime">
                            <input name="_coforpaypal_member_is_lifetime" type="checkbox" id="_coforpaypal_member_is_lifetime" value="1" <?php checked($is_lifetime, '1'); ?>>
                            <?php _e('Never expires (Lifetime membership)', 'checkout-for-paypal'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr valign="top" id="coforpaypal_expiry_date_row" style="<?php echo ($is_lifetime == '1') ? 'display:none;' : ''; ?>">
                <th scope="row"><label for="_coforpaypal_member_expiry_date"><?php _e('Expiry Date & Time', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <input name="_coforpaypal_member_expiry_date" type="datetime-local" id="_coforpaypal_member_expiry_date" value="<?php echo esc_attr($expiry_date_str); ?>">
                    <p class="description"><?php _e('Select when this membership expires. When expired, access to protected content will be revoked.', 'checkout-for-paypal'); ?></p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><label for="_coforpaypal_member_status"><?php _e('Status', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <select name="_coforpaypal_member_status" id="_coforpaypal_member_status">
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'checkout-for-paypal'); ?></option>
                        <option value="pending" <?php selected($status, 'pending'); ?>><?php _e('Pending', 'checkout-for-paypal'); ?></option>
                        <option value="expired" <?php selected($status, 'expired'); ?>><?php _e('Expired (Force Expired)', 'checkout-for-paypal'); ?></option>
                        <option value="suspended" <?php selected($status, 'suspended'); ?>><?php _e('Suspended', 'checkout-for-paypal'); ?></option>
                    </select>
                </td>
            </tr>

            <?php if (!empty($order_id)): ?>
            <tr valign="top">
                <th scope="row"><label><?php _e('Originating Order', 'checkout-for-paypal'); ?></label></th>
                <td>
                    <a href="<?php echo esc_url(get_edit_post_link($order_id)); ?>" target="_blank"><?php printf(__('Order #%s', 'checkout-for-paypal'), esc_html($order_id)); ?></a>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <script>
    jQuery(document).ready(function($) {
        $('#_coforpaypal_member_is_lifetime').on('change', function() {
            if ($(this).is(':checked')) {
                $('#coforpaypal_expiry_date_row').hide();
            } else {
                $('#coforpaypal_expiry_date_row').show();
            }
        });
    });
    </script>
    <?php
}

function checkout_for_paypal_member_details_meta_box_save($post_id, $post) {
    if (!isset($_POST['coforpaypal_member_details_meta_box_nonce']) || !wp_verify_nonce($_POST['coforpaypal_member_details_meta_box_nonce'], basename(__FILE__))) {
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

    if (isset($_POST['_coforpaypal_member_user_id'])) {
        update_post_meta($post_id, '_coforpaypal_member_user_id', sanitize_text_field($_POST['_coforpaypal_member_user_id']));
    }
    if (isset($_POST['_coforpaypal_member_email'])) {
        update_post_meta($post_id, '_coforpaypal_member_email', sanitize_email($_POST['_coforpaypal_member_email']));
    }
    if (isset($_POST['_coforpaypal_member_product_id'])) {
        update_post_meta($post_id, '_coforpaypal_member_product_id', sanitize_text_field($_POST['_coforpaypal_member_product_id']));
    }
    $is_lifetime = (isset($_POST['_coforpaypal_member_is_lifetime']) && $_POST['_coforpaypal_member_is_lifetime'] == '1') ? '1' : '';
    update_post_meta($post_id, '_coforpaypal_member_is_lifetime', $is_lifetime);

    if (isset($_POST['_coforpaypal_member_expiry_date']) && !empty($_POST['_coforpaypal_member_expiry_date'])) {
        $timestamp = strtotime(sanitize_text_field($_POST['_coforpaypal_member_expiry_date']));
        if ($timestamp) {
            update_post_meta($post_id, '_coforpaypal_member_expiry_date', $timestamp);
        }
    } else {
        if ($is_lifetime == '1') {
            delete_post_meta($post_id, '_coforpaypal_member_expiry_date');
        }
    }

    if (isset($_POST['_coforpaypal_member_status'])) {
        $old_status = get_post_meta($post_id, '_coforpaypal_member_status', true);
        $new_status = sanitize_text_field($_POST['_coforpaypal_member_status']);
        update_post_meta($post_id, '_coforpaypal_member_status', $new_status);
        if ($old_status !== 'active' && $new_status === 'active') {
            checkout_for_paypal_send_membership_active_email($post_id);
        }
    }
}

add_action('save_post_coforpaypal_member', 'checkout_for_paypal_member_details_meta_box_save', 10, 2);

/**
 * Check if a WordPress user has active membership access.
 * Returns an array: array('status' => 'active'|'expired'|'none', 'member_id' => int, 'expiry' => int)
 */
function checkout_for_paypal_check_user_membership($user_id, $required_product_id = '') {
    if (empty($user_id)) {
        return array('status' => 'none', 'member_id' => 0, 'expiry' => 0);
    }

    $meta_query = array(
        array(
            'key'     => '_coforpaypal_member_user_id',
            'value'   => $user_id,
            'compare' => '='
        )
    );

    if (!empty($required_product_id)) {
        $meta_query[] = array(
            'key'     => '_coforpaypal_member_product_id',
            'value'   => $required_product_id,
            'compare' => '='
        );
    }

    $args = array(
        'post_type'      => 'coforpaypal_member',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => $meta_query
    );

    $members = get_posts($args);
    if (empty($members)) {
        return array('status' => 'none', 'member_id' => 0, 'expiry' => 0);
    }

    $has_expired = false;
    $has_pending = false;
    $last_expiry = 0;
    $last_member_id = 0;

    foreach ($members as $member) {
        $member_id = $member->ID;
        $status = checkout_for_paypal_get_member_status($member_id);
        $expiry = get_post_meta($member_id, '_coforpaypal_member_expiry_date', true);

        if ($status === 'active') {
            return array('status' => 'active', 'member_id' => $member_id, 'expiry' => $expiry);
        } elseif ($status === 'pending') {
            $has_pending = true;
            $last_member_id = $member_id;
            $last_expiry = $expiry;
        } elseif ($status === 'expired') {
            $has_expired = true;
            $last_expiry = $expiry;
            $last_member_id = $member_id;
        }
    }

    if ($has_pending) {
        return array('status' => 'pending', 'member_id' => $last_member_id, 'expiry' => $last_expiry);
    }

    if ($has_expired) {
        return array('status' => 'expired', 'member_id' => $last_member_id, 'expiry' => $last_expiry);
    }

    return array('status' => 'none', 'member_id' => 0, 'expiry' => 0);
}

/**
 * Send active membership notification email
 */
function checkout_for_paypal_send_membership_active_email($member_post_id) {
    $membership_options = checkout_for_paypal_get_membership_option();
    if (!isset($membership_options['active_notification_email_enabled']) || $membership_options['active_notification_email_enabled'] !== '1') {
        return false;
    }

    $user_id = get_post_meta($member_post_id, '_coforpaypal_member_user_id', true);
    $user_email = get_post_meta($member_post_id, '_coforpaypal_member_email', true);
    if (empty($user_email) && !empty($user_id)) {
        $user = get_userdata($user_id);
        if ($user) {
            $user_email = $user->user_email;
        }
    }
    if (empty($user_email) || !is_email($user_email)) {
        return false;
    }

    $product_id = get_post_meta($member_post_id, '_coforpaypal_member_product_id', true);
    $product_name = '';
    if (!empty($product_id)) {
        $prod = checkout_for_paypal_get_product($product_id);
        if ($prod) {
            $product_name = $prod['title'];
        }
    }

    $expiry_timestamp = get_post_meta($member_post_id, '_coforpaypal_member_expiry_date', true);
    $is_lifetime = get_post_meta($member_post_id, '_coforpaypal_member_is_lifetime', true);
    if ($is_lifetime == '1' || empty($expiry_timestamp)) {
        $expiry_date_str = __('Never (Lifetime)', 'checkout-for-paypal');
    } else {
        $date_format = get_option('date_format');
        $expiry_date_str = date_i18n($date_format, $expiry_timestamp);
    }

    $given_name = '';
    $surname = '';
    $username = '';
    if (!empty($user_id)) {
        $user = get_userdata($user_id);
        if ($user) {
            $given_name = $user->first_name;
            $surname = $user->last_name;
            $username = $user->user_login;
        }
    }

    $payment_data = array(
        'given_name'    => $given_name,
        'surname'       => $surname,
        'username'      => $username,
        'payer_email'   => $user_email,
        'product_id'    => $product_id,
        'product_name'  => $product_name,
        'expiry_date'   => $expiry_date_str,
        'login_url'     => wp_login_url(),
    );

    $subject = $membership_options['active_notification_email_subject'];
    $type = isset($membership_options['active_notification_email_type']) ? $membership_options['active_notification_email_type'] : 'plain';
    $body = $membership_options['active_notification_email_body'];
    $body = checkout_for_paypal_do_email_tags($payment_data, $body);

    add_filter('wp_mail_from', 'checkout_for_paypal_set_email_from');
    add_filter('wp_mail_from_name', 'checkout_for_paypal_set_email_from_name');
    if ($type === 'html') {
        add_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
        $body = apply_filters('checkout_for_paypal_email_body_wpautop', true) ? wpautop($body) : $body;
    }

    checkout_for_paypal_debug_log("Sending membership activated email to " . $user_email, true);
    $mail_sent = wp_mail($user_email, $subject, $body);

    if ($type === 'html') {
        remove_filter('wp_mail_content_type', 'checkout_for_paypal_set_html_email_content_type');
    }
    remove_filter('wp_mail_from', 'checkout_for_paypal_set_email_from');
    remove_filter('wp_mail_from_name', 'checkout_for_paypal_set_email_from_name');

    if ($mail_sent) {
        checkout_for_paypal_debug_log("Membership activated email sent successfully by WordPress", true);
    } else {
        checkout_for_paypal_debug_log("Membership activated email could not be sent by WordPress", false);
    }

    return $mail_sent;
}
