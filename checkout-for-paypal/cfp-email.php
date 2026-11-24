<?php

function checkout_for_paypal_get_email_option(){
    $options = get_option('checkout_for_paypal_email_options');
    if(!is_array($options)){
        $options = checkout_for_paypal_get_empty_email_options_array();
    }
    return $options;
}

function checkout_for_paypal_update_email_option($new_options){
    $empty_options = checkout_for_paypal_get_empty_email_options_array();
    $options = checkout_for_paypal_get_email_option();
    if(is_array($options)){
        $current_options = array_merge($empty_options, $options);
        $updated_options = array_merge($current_options, $new_options);
        update_option('checkout_for_paypal_email_options', $updated_options);
    }
    else{
        $updated_options = array_merge($empty_options, $new_options);
        update_option('checkout_for_paypal_email_options', $updated_options);
    }
}

function checkout_for_paypal_get_empty_email_options_array(){
    $options = array();
    $options['email_from_name'] = '';
    $options['email_from_address'] = '';
    $options['purchase_email_enabled'] = '';
    $options['purchase_email_subject'] = '';
    $options['purchase_email_type'] = '';
    $options['purchase_email_body'] = '';
    $options['sale_notification_email_enabled'] = '';
    $options['sale_notification_email_recipient'] = '';
    $options['sale_notification_email_subject'] = '';
    $options['sale_notification_email_type'] = '';
    $options['sale_notification_email_body'] = '';
    return $options;
}

function checkout_for_paypal_set_default_email_options(){
    $options = checkout_for_paypal_get_email_option();
    $options['purchase_email_type'] = 'plain';
    $options['purchase_email_subject'] = __("Purchase Receipt", "checkout-for-paypal");
    $purchage_email_body = __("Dear", "checkout-for-paypal")." {given_name},\n\n";
    $purchage_email_body .= __("Thank you for your purchase. Your purchase details are shown below for your reference:", "checkout-for-paypal")."\n\n";
    $purchage_email_body .= __("Transaction ID:", "checkout-for-paypal")." {txn_id}\n";
    $purchage_email_body .= __("Product:", "checkout-for-paypal")." {description}\n";
    $purchage_email_body .= __("Amount:", "checkout-for-paypal")." {currency_code} {amount}";
    $options['purchase_email_body'] = $purchage_email_body;
    $options['sale_notification_email_recipient'] = get_bloginfo('admin_email');
    $options['sale_notification_email_subject'] = __("New Customer Order", "checkout-for-paypal");
    $options['sale_notification_email_type'] = 'plain';
    $sale_notification_email_body = __("Hello", "checkout-for-paypal")."\n\n";
    $sale_notification_email_body .= __("A purchase has been made.", "checkout-for-paypal")."\n\n";
    $sale_notification_email_body .= __("Purchased by:", "checkout-for-paypal")." {given_name} {surname}\n";
    $sale_notification_email_body .= __("Product sold:", "checkout-for-paypal")." {description}\n";
    $sale_notification_email_body .= __("Amount:", "checkout-for-paypal")." {currency_code} {amount}\n\n";
    $sale_notification_email_body .= __("Thank you", "checkout-for-paypal");       
    $options['sale_notification_email_body'] = $sale_notification_email_body;
    add_option('checkout_for_paypal_email_options', $options);
}

function checkout_for_paypal_get_membership_option(){
    $options = get_option('checkout_for_paypal_membership_options');
    if(!is_array($options)){
        $options = checkout_for_paypal_get_empty_membership_options_array();
    }
    return $options;
}

function checkout_for_paypal_update_membership_option($new_options){
    $empty_options = checkout_for_paypal_get_empty_membership_options_array();
    $options = checkout_for_paypal_get_membership_option();
    if(is_array($options)){
        $current_options = array_merge($empty_options, $options);
        $updated_options = array_merge($current_options, $new_options);
        update_option('checkout_for_paypal_membership_options', $updated_options);
    }
    else{
        $updated_options = array_merge($empty_options, $new_options);
        update_option('checkout_for_paypal_membership_options', $updated_options);
    }
}

function checkout_for_paypal_get_empty_membership_options_array(){
    $options = array();
    $options['msg_login_required'] = '';
    $options['msg_membership_expired'] = '';
    $options['msg_membership_pending'] = '';
    $options['msg_access_denied'] = '';
    $options['new_user_email_enabled'] = '';
    $options['new_user_email_subject'] = '';
    $options['new_user_email_type'] = '';
    $options['new_user_email_body'] = '';
    $options['existing_user_email_enabled'] = '';
    $options['existing_user_email_subject'] = '';
    $options['existing_user_email_type'] = '';
    $options['existing_user_email_body'] = '';
    $options['active_notification_email_enabled'] = '';
    $options['active_notification_email_subject'] = '';
    $options['active_notification_email_type'] = '';
    $options['active_notification_email_body'] = '';
    return $options;
}

function checkout_for_paypal_set_default_membership_options(){
    $options = checkout_for_paypal_get_membership_option();
    //
    if(empty($options['msg_login_required'])){
        $options['msg_login_required'] = __('<p>You must be logged in to access this content. Please <a href="{login_url}">log in here</a>.</p>', 'checkout-for-paypal');
    }
    if(empty($options['msg_membership_expired'])){
        $options['msg_membership_expired'] = __('<p>Your membership has expired.</p>', 'checkout-for-paypal');
    }
    if(empty($options['msg_membership_pending'])){
        $options['msg_membership_pending'] = __('<p>Your membership account is pending approval or activation.</p>', 'checkout-for-paypal');
    }
    if(empty($options['msg_access_denied'])){
        $options['msg_access_denied'] = __('<p>You do not have an active membership for this content.</p>', 'checkout-for-paypal');
    }
    //
    if(empty($options['new_user_email_type'])){
        $options['new_user_email_type'] = 'plain';
    }
    if(empty($options['new_user_email_subject'])){
        $options['new_user_email_subject'] = __("Your Membership Account Details", "checkout-for-paypal");
    }
    if(empty($options['new_user_email_body'])){
        $new_user_email_body = __("Dear", "checkout-for-paypal")." {given_name},\n\n";
        $new_user_email_body .= __("Your membership account has been successfully created. You can log in using the credentials below:", "checkout-for-paypal")."\n\n";
        $new_user_email_body .= __("Login URL:", "checkout-for-paypal")." {login_url}\n";
        $new_user_email_body .= __("Username:", "checkout-for-paypal")." {username}\n";
        $new_user_email_body .= __("Password:", "checkout-for-paypal")." {password}\n";
        $new_user_email_body .= __("Expiry Date:", "checkout-for-paypal")." {expiry_date}\n\n";
        $new_user_email_body .= __("Thank you!", "checkout-for-paypal");
        $options['new_user_email_body'] = $new_user_email_body;
    }
    //
    if(empty($options['existing_user_email_type'])){
        $options['existing_user_email_type'] = 'plain';
    }
    if(empty($options['existing_user_email_subject'])){
        $options['existing_user_email_subject'] = __("Membership Payment Confirmation", "checkout-for-paypal");
    }
    if(empty($options['existing_user_email_body'])){
        $existing_email_body = __("Dear", "checkout-for-paypal")." {given_name},\n\n";
        $existing_email_body .= __("Thank you for your payment. Your membership for {product_name} has been processed.", "checkout-for-paypal")."\n\n";
        $existing_email_body .= __("Username:", "checkout-for-paypal")." {username}\n";
        $existing_email_body .= __("Membership Level:", "checkout-for-paypal")." {product_name}\n";
        $existing_email_body .= __("Expiry Date:", "checkout-for-paypal")." {expiry_date}\n";
        $existing_email_body .= __("Login URL:", "checkout-for-paypal")." {login_url}\n\n";
        $existing_email_body .= __("Thank you!", "checkout-for-paypal");
        $options['existing_user_email_body'] = $existing_email_body;
    }
    //
    if(empty($options['active_notification_email_type'])){
        $options['active_notification_email_type'] = 'plain';
    }
    if(empty($options['active_notification_email_subject'])){
        $options['active_notification_email_subject'] = __("Your Membership is Now Active", "checkout-for-paypal");
    }
    if(empty($options['active_notification_email_body'])){
        $active_email_body = __("Dear", "checkout-for-paypal")." {given_name},\n\n";
        $active_email_body .= __("Great news! Your membership for {product_name} is now active.", "checkout-for-paypal")."\n\n";
        $active_email_body .= __("Membership Level:", "checkout-for-paypal")." {product_name}\n";
        $active_email_body .= __("Expiry Date:", "checkout-for-paypal")." {expiry_date}\n";
        $active_email_body .= __("Login URL:", "checkout-for-paypal")." {login_url}\n\n";
        $active_email_body .= __("Thank you for joining!", "checkout-for-paypal");
        $options['active_notification_email_body'] = $active_email_body;
    }
    //
    add_option('checkout_for_paypal_membership_options', $options);
}

function checkout_for_paypal_do_email_tags($payment_data, $content){
    $search = array(
        '{given_name}', 
        '{surname}', 
        '{txn_id}',
        '{description}',
        '{product_name}',
        '{product_id}',
        '{currency_code}',
        '{amount}',
        '{payer_email}',
        '{shipping_name}',
        '{shipping_address}',
        '{item_total}',
        '{shipping}',
        '{phone_number}',
        '{username}',
        '{password}',
        '{login_url}',
        '{expiry_date}',
    );
    $replace = array(
        isset($payment_data['given_name']) ? $payment_data['given_name'] : '', 
        isset($payment_data['surname']) ? $payment_data['surname'] : '',
        isset($payment_data['txn_id']) ? $payment_data['txn_id'] : '',
        isset($payment_data['description']) ? $payment_data['description'] : '',
        isset($payment_data['product_name']) ? $payment_data['product_name'] : '',
        isset($payment_data['product_id']) ? $payment_data['product_id'] : '',
        isset($payment_data['currency_code']) ? $payment_data['currency_code'] : '',
        isset($payment_data['amount']) ? $payment_data['amount'] : '',
        isset($payment_data['payer_email']) ? $payment_data['payer_email'] : '',
        isset($payment_data['shipping_name']) ? $payment_data['shipping_name'] : '',
        isset($payment_data['shipping_address']) ? $payment_data['shipping_address'] : '',
        isset($payment_data['item_total']) ? $payment_data['item_total'] : '',
        isset($payment_data['shipping']) ? $payment_data['shipping'] : '',
        isset($payment_data['phone_number']) ? $payment_data['phone_number'] : '',
        isset($payment_data['username']) ? $payment_data['username'] : '',
        isset($payment_data['password']) ? $payment_data['password'] : '',
        isset($payment_data['login_url']) ? $payment_data['login_url'] : '',
        isset($payment_data['expiry_date']) ? $payment_data['expiry_date'] : '',
    );
    $content = str_replace($search, $replace, $content);
    return $content;
}

function checkout_for_paypal_set_email_from($from){
    $email_options = checkout_for_paypal_get_email_option();
    if(isset($email_options['email_from_address']) && !empty($email_options['email_from_address'])){
        $from = $email_options['email_from_address'];
    }
    return $from;
}

function checkout_for_paypal_set_email_from_name($from_name){
    $email_options = checkout_for_paypal_get_email_option();
    if(isset($email_options['email_from_name']) && !empty($email_options['email_from_name'])){
        $from_name = $email_options['email_from_name'];
    }
    return $from_name;
}

function checkout_for_paypal_set_html_email_content_type($content_type){
    $content_type = 'text/html';
    return $content_type;
}
