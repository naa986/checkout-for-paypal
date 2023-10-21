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

function checkout_for_paypal_do_email_tags($payment_data, $content){
    $search = array(
        '{given_name}', 
        '{surname}', 
        '{txn_id}',
        '{description}',
        '{currency_code}',
        '{amount}',
        '{payer_email}',
        '{shipping_name}',
        '{shipping_address}',
        '{item_total}',
        '{shipping}',
        '{phone_number}',
    );
    $replace = array(
        $payment_data['given_name'], 
        $payment_data['surname'],
        $payment_data['txn_id'],
        $payment_data['description'],
        $payment_data['currency_code'],
        $payment_data['amount'],
        $payment_data['payer_email'],
        $payment_data['shipping_name'],
        $payment_data['shipping_address'],
        $payment_data['item_total'],
        $payment_data['shipping'],
        $payment_data['phone_number'],
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
