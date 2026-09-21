<?php

add_action('wp_ajax_coforpaypalcheckout_pp_api_create_order', 'checkout_for_paypal_checkout_pp_api_create_order');
add_action('wp_ajax_nopriv_coforpaypalcheckout_pp_api_create_order', 'checkout_for_paypal_checkout_pp_api_create_order');
add_action('wp_ajax_coforpaypalcheckout_pp_api_capture_order', 'checkout_for_paypal_checkout_pp_api_capture_order');
add_action('wp_ajax_nopriv_coforpaypalcheckout_pp_api_capture_order', 'checkout_for_paypal_checkout_pp_api_capture_order');
add_action('checkout_for_paypal_checkout_process_order', 'checkout_for_paypal_checkout_process_order_handler', 10, 2);

function checkout_for_paypal_checkout_pp_api_create_order(){
    //The data will be in JSON format string (not actual JSON object). By using json_decode it can be converted to a json object or array.
    $json_order_data = isset($_POST['data']) ? stripslashes_deep($_POST['data']) : '{}';
    $order_data_array = json_decode($json_order_data, true);
    if(empty($json_order_data)){
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Empty data received.', 'checkout-for-paypal'),
            )
        );
    }
    $custom_id = isset($order_data_array['purchase_units'][0]['custom_id']) ? $order_data_array['purchase_units'][0]['custom_id'] : '';
    $product_id = isset($custom_id) ? sanitize_text_field($custom_id) : '';
    if(empty($product_id)){
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Empty product id received.', 'checkout-for-paypal'),
            )
        );
    }
    checkout_for_paypal_debug_log("Checkout - Create-order request received for product id: ".$product_id, true);
    // Retrieve product details from database
    $product = checkout_for_paypal_get_product($product_id);
    if(!$product){
        checkout_for_paypal_debug_log("Checkout - Product not found", false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Product not found.', 'checkout-for-paypal'),
            )
        );
    }
    $product_name = $product['title'];
    if(!isset($product_name) || empty($product_name)){
        checkout_for_paypal_debug_log("Checkout - Product name not found", false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Product name not found.', 'checkout-for-paypal'),
            )
        );
    }
    $product_price = $product['price'];
    $total_amount = 0;
    if(isset($product_price) && is_numeric($product_price) && $product_price > 0){
        $product_price = number_format($product_price, 2, '.', '');
        $total_amount = $product_price;
    }
    else{
        checkout_for_paypal_debug_log("Checkout - Product price not valid", false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Product price not valid.', 'checkout-for-paypal'),
            )
        );
    }
    //variable price
    $purchase_units_amount_value = isset($order_data_array['purchase_units'][0]['amount']['value']) ? sanitize_text_field($order_data_array['purchase_units'][0]['amount']['value']) : 0;
    $enable_variable_pricing = get_post_meta($product['id'], '_coforpaypal_product_enable_variable_pricing', true);
    if(defined('COFORPAYPAL_VARIABLE_PRICE_VERSION') && isset($enable_variable_pricing) && $enable_variable_pricing == '1'){
        if(isset($purchase_units_amount_value) && is_numeric($purchase_units_amount_value) && $purchase_units_amount_value > 0) {
            $product_price = sanitize_text_field($purchase_units_amount_value);
            $product_price = number_format($product_price, 2, '.', '');
            $total_amount = $product_price;
        }
        else{
            checkout_for_paypal_debug_log("Checkout - Price not valid", false);
            wp_send_json(
                array(
                    'success' => false,
                    'err_msg' => __('Price not valid.', 'checkout-for-paypal'),
                )
            );
        }
    }
    //
    $has_shipping = false;
    $shipping = isset($product['shipping']) ? $product['shipping'] : 0;
    if(is_numeric($shipping) && $shipping > 0){
        $shipping = number_format($shipping, 2, '.', '');
        $total_amount = $total_amount + $shipping;
        $total_amount = number_format($total_amount, 2, '.', '');
        $has_shipping = true;
    }
    //
    $options = checkout_for_paypal_get_option();
    $currency_code = $options['currency_code'];
  
    checkout_for_paypal_debug_log("Checkout - Creating order data to send to PayPal: ", true);
    $pp_api_order_data = [
        "intent" => "CAPTURE",
        "payment_source" => [
            "paypal" => [
                "experience_context" => [
                    "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                ]
            ]
        ], 			
        "purchase_units" => [
            [
                "description" => $product_name,
                "custom_id" => $product['id'],
                "amount" => [
                    "value" => (string) $total_amount,
                    "currency_code" => $currency_code,
                    "breakdown" => [
                        "item_total" => [
                            "currency_code" => $currency_code,
                            "value" => (string) $product_price,
                        ]
                    ]
                ],
                "items" => [
                    [
                        "name" => substr($product_name, 0, 127),
                        "quantity" => "1",
                        "unit_amount" => [
                            "value" => (string) $product_price,
                            "currency_code" => $currency_code,
                        ]
                    ]
                ]
            ]
        ]
    ];
    // Add shipping to breakdown if it exists
    if ($has_shipping) {
        $pp_api_order_data['purchase_units'][0]['amount']['breakdown']['shipping'] = [
            'currency_code' => $currency_code,
            'value' => (string) $shipping
        ];
    }  
    /*
    $shipping_preference = 'NO_SHIPPING';
    if ($has_shipping) {       
        $shipping_preference = 'GET_FROM_FILE';
    }
    $pp_api_order_data['payment_source']['paypal']['experience_context']['shipping_preference'] = $shipping_preference;
    */
    $json_encoded_pp_api_order_data = wp_json_encode($pp_api_order_data);   
    checkout_for_paypal_debug_log_array($json_encoded_pp_api_order_data, true);  
    $access_token = checkout_for_paypal_get_paypal_access_token();
    if (!$access_token) {
        checkout_for_paypal_debug_log('Access token could not be created using PayPal API', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Access token could not be created using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $url = 'https://api-m.paypal.com/v2/checkout/orders';
    if(isset($options['test_mode']) && $options['test_mode'] == "1"){
        $url = 'https://api-m.sandbox.paypal.com/v2/checkout/orders';
    }
    $response = wp_safe_remote_post($url, array(
        'method' => 'POST',
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
        'body' => $json_encoded_pp_api_order_data
    ));

    if (is_wp_error($response)) {
        checkout_for_paypal_debug_log('Error response', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg'  => __('Failed to create the order using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }

    $body = wp_remote_retrieve_body($response);
    if(!isset($body) || empty($body)){
        checkout_for_paypal_debug_log('Error response from invalid body', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Invalid response body from PayPal API order creation.', 'checkout-for-paypal'),
            )
        );
    }
    $data = json_decode($body);
    if(!isset($data) || empty($data)){
        checkout_for_paypal_debug_log('Invalid response data from PayPal API order creation', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Invalid response data from PayPal API order creation.', 'checkout-for-paypal'),
            )
        );
    }
    checkout_for_paypal_debug_log('Response data from order creation', true);
    checkout_for_paypal_debug_log_array($data, true);
    if(!isset($data->id) || empty($data->id)){
        checkout_for_paypal_debug_log('No order ID from PayPal API order creation', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('No order ID from PayPal API order creation.', 'checkout-for-paypal'),
            )
        );
    }
    $paypal_order_id = $data->id;
    wp_send_json( 
        array( 
            'success' => true,
            'order_id' => $paypal_order_id,
            'additional_data' => array(),
        )
    );
}

function checkout_for_paypal_get_paypal_access_token() {
    $options = checkout_for_paypal_get_option();
    $url = 'https://api-m.paypal.com/v1/oauth2/token';
    $client_id = $options['app_client_id'];
    $secret_key = $options['app_secret_key'];
    if(isset($options['test_mode']) && $options['test_mode'] == "1"){
        $url = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
        $client_id = $options['app_sandbox_client_id'];
        $secret_key = $options['app_sandbox_secret_key'];
    }
    if(!isset($client_id) || empty($client_id)){
        checkout_for_paypal_debug_log('No client ID. Access token cannot be created.', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Failed to create an access token using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    if(!isset($secret_key) || empty($secret_key)){
        checkout_for_paypal_debug_log('No secret key. Access token cannot be created.', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Failed to create an access token using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $secret_key = base64_decode($secret_key);
    $auth = base64_encode($client_id . ':' . $secret_key);
    checkout_for_paypal_debug_log('Creating access token', true);
    $response = wp_safe_remote_post($url, array(
        'method' => 'POST',
        'headers' => array(
            'Authorization' => 'Basic ' . $auth,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ),
        'body' => 'grant_type=client_credentials'
    ));

    if (is_wp_error($response)) {
        checkout_for_paypal_debug_log('Error response', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Failed to create an access token using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }

    $body = wp_remote_retrieve_body($response);
    if(!isset($body) || empty($body)){
        checkout_for_paypal_debug_log('Error response from invalid body', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Invalid response body when creating an access token using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $data = json_decode($body);
    checkout_for_paypal_debug_log('Response data for access token', true);
    checkout_for_paypal_debug_log_array($data, true);
    if(!isset($data->access_token) || empty($data->access_token)){
        checkout_for_paypal_debug_log('No valid access token from PayPal API response', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('No valid access token from PayPal API response.', 'checkout-for-paypal'),
            )
        );
    }

    return $data->access_token;
}

function checkout_for_paypal_checkout_pp_api_capture_order(){
    $json_pp_bn_data = isset( $_POST['data'] ) ? stripslashes_deep( $_POST['data'] ) : '{}';
    $array_pp_bn_data = json_decode( $json_pp_bn_data, true );
    $order_id = isset( $array_pp_bn_data['order_id'] ) ? sanitize_text_field($array_pp_bn_data['order_id']) : '';
    checkout_for_paypal_debug_log('PayPal capture order request received - PayPal order ID: ' . $order_id, true);
    if(empty($order_id)){
        checkout_for_paypal_debug_log('Empty order ID received from PayPal capture order request', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Error! Empty order ID received for PayPal capture order request.', 'checkout-for-paypal'),
            )
        );
    }
    checkout_for_paypal_debug_log("Creating data to send to PayPal for capturing the order: ", true);
    $api_params = array( 'order_id' => $order_id );
    $json_api_params = json_encode($api_params);  
    checkout_for_paypal_debug_log_array($json_api_params, true);  
    $access_token = checkout_for_paypal_get_paypal_access_token();
    if (!$access_token) {
        checkout_for_paypal_debug_log('Access token could not be created using PayPal API', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Access token could not be created using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $options = checkout_for_paypal_get_option();
    $url = 'https://api-m.paypal.com/v2/checkout/orders';
    if(isset($options['test_mode']) && $options['test_mode'] == "1"){
        $url = 'https://api-m.sandbox.paypal.com/v2/checkout/orders';
    }
    $url .= '/'.$order_id.'/capture';
    $response = wp_safe_remote_post($url, array(
        'method' => 'POST',
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
        'body' => $json_api_params
    ));
    if (is_wp_error($response)) {
        checkout_for_paypal_debug_log('Error response', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Failed to capture the order using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }

    $body = wp_remote_retrieve_body($response);
    if(!isset($body) || empty($body)){
        checkout_for_paypal_debug_log('Error response from invalid body', false);
        checkout_for_paypal_debug_log_array($response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Invalid response body from PayPal API order capture.', 'checkout-for-paypal'),
            )
        );
    }
    $capture_response_data = json_decode($body, true);
    if(!isset($capture_response_data) || empty($capture_response_data)){
        checkout_for_paypal_debug_log('Empty response data', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Empty response data from PayPal API order capture.', 'checkout-for-paypal'),
            )
        );
    }
    checkout_for_paypal_debug_log('Response data from order capture', true);
    checkout_for_paypal_debug_log_array($capture_response_data, true);
    //
    checkout_for_paypal_debug_log('Retrieving order details', true);
    $url = 'https://api-m.paypal.com/v2/checkout/orders';
    if(isset($options['test_mode']) && $options['test_mode'] == "1"){
        $url = 'https://api-m.sandbox.paypal.com/v2/checkout/orders';
    }
    $url .= '/'.$order_id;
    $order_response = wp_safe_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
    ));
    if (is_wp_error($order_response)) {
        checkout_for_paypal_debug_log('Error response', false);
        checkout_for_paypal_debug_log_array($order_response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Failed to retrieve order details using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $order_body = wp_remote_retrieve_body($order_response);
    if(!isset($order_body) || empty($order_body)){
        checkout_for_paypal_debug_log('Error response from invalid body', false);
        checkout_for_paypal_debug_log_array($order_response, false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Invalid response body from retrieving order details using PayPal API.', 'checkout-for-paypal'),
            )
        );
    }
    $order_details_data = json_decode($order_body, true);
    if(!isset($order_details_data) || empty($order_details_data)){
        checkout_for_paypal_debug_log('Empty response data from retrieving order details', false);
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Empty response data from PayPal API order details.', 'checkout-for-paypal'),
            )
        );
    }
    checkout_for_paypal_debug_log('Response data from retrieving order details', true);
    checkout_for_paypal_debug_log_array($order_details_data, true);
    //
    do_action('checkout_for_paypal_checkout_process_order', $capture_response_data, $order_details_data);
    wp_send_json_success();  
}

function checkout_for_paypal_checkout_process_order_handler($capture_response_data, $order_details_data)
{
    if(!isset($order_details_data['payer'])){
        checkout_for_paypal_debug_log("No payer data. This payment cannot be processed.", false);
        return;
    }
    $payer = $order_details_data['payer'];
    if(!isset($order_details_data['purchase_units'][0])){
        checkout_for_paypal_debug_log("No purchase unit data. This payment cannot be processed.", false);
        return;
    }
    $purchase_units = $order_details_data['purchase_units'][0];
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
    //
    $payment_data['product_id'] = '';
    if (isset($purchase_units['custom_id'])) {
        $payment_data['product_id'] = sanitize_text_field($purchase_units['custom_id']);
    }
    $payment_data['product_name'] = '';
    //
    $payment_data['phone_number'] = '';
    if(isset($payer['phone']['phone_number']['national_number'])){
        $payment_data['phone_number'] = sanitize_text_field($payer['phone']['phone_number']['national_number']);
    }
    $payment_data['description'] = '';
    if (isset($purchase_units['description'])) {
        $payment_data['description'] = sanitize_text_field($purchase_units['description']);
        $payment_data['product_name'] = $payment_data['description'];
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
        if(!empty($payment_data['product_name'])){
            $post_content .= '<strong>Product:</strong> '.$payment_data['product_name'].'<br />';
        }
        if(!empty($payment_data['product_id'])){
            $post_content .= '<strong>Product ID:</strong> '.$payment_data['product_id'].'<br />';
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
        $post_content .= print_r($order_details_data, true);
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
        update_post_meta($post_id, '_payment_data', $payment_data);
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
        
        $order_details_data['post_order_id'] = $post_id;
        do_action('checkout_for_paypal_order_processed', $order_details_data);
    } else {
        checkout_for_paypal_debug_log("Order information could not be updated", false);
        return;
    }
    checkout_for_paypal_debug_log("Payment processing completed", true, true);   
    return;
}

function checkout_for_paypal_product_button_handler($atts){
    if(!is_checkout_for_paypal_configured()){
        return __('You need to configure checkout options in the settings', 'checkout-for-paypal');
    }
    $atts = is_array($atts) ? array_map('sanitize_text_field', $atts) : array();
    $product_id = isset($atts['id']) ? sanitize_text_field($atts['id']) : '';
    if (empty($product_id)) {
        return __('You need to provide a product ID', 'checkout-for-paypal');
    }
    $product = function_exists('checkout_for_paypal_get_product') ? checkout_for_paypal_get_product($product_id) : null;
    if (!$product) {
        return __('Product not found', 'checkout-for-paypal');
    }
    $options = checkout_for_paypal_get_option();
    if(!isset($options['checkout_page_url']) || empty($options['checkout_page_url'])){
        return __('Checkout page URL not found', 'checkout-for-paypal');
    }
    $action_url = $options['checkout_page_url'];
    $button_text = 'Buy Now';
    if(isset($product['button_text']) && !empty($product['button_text'])){
        $button_text = $product['button_text'];
    }
    $button_code = '';
    $method = 'post';
    $target = '';
    if(isset($atts['target']) && !empty($atts['target'])) {
        $target = 'target="'.esc_attr($atts['target']).'" ';
    }
    $form_class = '';
    if(isset($atts['form_class']) && !empty($atts['form_class'])) {
        $form_class = 'class="'.esc_attr($atts['form_class']).'" ';
    }
    $button_code .= '<form '.$form_class.$target.'action="'.esc_url($action_url).'" method="'.$method.'" >';
    $button_code .= '<input type="hidden" name="coforpaypal_prod_id" value="'.esc_attr($product_id).'">';
    $price_input_code = '';
    $price_input_code = apply_filters('checkout_for_paypal_product_variable_price', $price_input_code, $button_code, $atts);
    if(!empty($price_input_code)){
        $button_code .= $price_input_code;
    }
    $button_code .= '<input type="submit" value="'.esc_attr($button_text).'" />';
    $button_code .= '</form>';
    return $button_code;        
}

function checkout_for_paypal_checkout_button_handler($atts) {
    if(!is_checkout_for_paypal_configured()){
        return __('You need to configure checkout options in the settings', 'checkout-for-paypal');
    }
    $atts = is_array($atts) ? array_map('sanitize_text_field', $atts) : array();
    $options = checkout_for_paypal_get_option();

    // Product ID is required in URL query parameters
    $product_id = '';
    if(isset($_POST['coforpaypal_prod_id'])){
        $product_id = sanitize_text_field($_POST['coforpaypal_prod_id']);
    }
    else{
        return '';
    }
    //
    if (empty($product_id)) {
        return __('You need to provide a valid product ID', 'checkout-for-paypal');
    }

    $product = function_exists('checkout_for_paypal_get_product') ? checkout_for_paypal_get_product($product_id) : null;
    if (!$product) {
        return __('Product not found', 'checkout-for-paypal');
    }
    //
    if (!isset($product['title']) || empty($product['title'])) {
        return __('Product title not found', 'checkout-for-paypal');
    }
    //
    $product_price = 0;
    if (isset($product['price']) && is_numeric($product['price']) && $product['price'] > 0 ) {
        $product_price = number_format($product['price'], 2, '.', '');
    }
    else{
        return __('Product price is not valid', 'checkout-for-paypal');
    }
    //
    $amount = 0;
    if (isset($_POST['coforpaypal_prod_price'])) {
        if(is_numeric($_POST['coforpaypal_prod_price']) && $_POST['coforpaypal_prod_price'] > 0){
            $amount = sanitize_text_field($_POST['coforpaypal_prod_price']);
            $amount = number_format($amount, 2, '.', '');
            $product_price = $amount;
        }
        else{
            return __('Price is not valid', 'checkout-for-paypal');
        }
    }
    //
    $shipping = 0;
    $has_shipping = false;
    if (isset($product['shipping']) && is_numeric($product['shipping']) && $product['shipping'] > 0 ) {
        $shipping = number_format($product['shipping'], 2, '.', '');
        $has_shipping = true;
    }
    //
    $currency = $options['currency_code'];
    if (!isset($currency) || empty($currency)) {
        return __('Currency not found', 'checkout-for-paypal');
    }
    //
    $return_url = $options['return_url'];
    if (!isset($return_url) || empty($return_url)) {
        return __('Return URL not found', 'checkout-for-paypal');
    }
    $return_output = '';
    if(!empty($return_url)){
        //$return_output = 'window.location.replace("'.esc_js(esc_url($return_url)).'");';
        $return_output = "let temp_return_url = '".esc_js(esc_url($return_url))."';";
	$return_output .= "let return_url = temp_return_url.replace(/&#038;/g, '&');";
        $return_output .= "window.location.replace(return_url);";
    }
    //
    $cancel_url = $options['cancel_url'];
    if (!isset($cancel_url) || empty($cancel_url)) {
        return __('Cancel URL not found', 'checkout-for-paypal');
    }
    $cancel_output = '';
    if(!empty($cancel_url)){
        //$cancel_output = 'window.location.replace("'.esc_js(esc_url($cancel_url)).'");';
        $cancel_output = "let temp_cancel_url = '".esc_js(esc_url($cancel_url))."';";
	$cancel_output .= "let cancel_url = temp_cancel_url.replace(/&#038;/g, '&');";
        $cancel_output .= "window.location.replace(cancel_url);";
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
    $label = 'paypal';
    if(isset($atts['label']) && $atts['label'] == 'checkout'){
        $label = 'checkout';
    }
    else if(isset($atts['label']) && $atts['label'] == 'buynow'){
        $label = 'buynow';
    }
    else if(isset($atts['label']) && $atts['label'] == 'pay'){
        $label = 'pay';
    }
    $id = uniqid();
    $atts['id'] = $id;
    $button_code = '';
    $esc_js = 'esc_js';
    $button_id = 'coforpaypalcheckout-button-'.$id;
    $button_container_id = 'coforpaypalcheckout-button-container-'.$id;

    // Optional order summary box when product info is loaded
    if ($product && (!isset($atts['show_summary']) || $atts['show_summary'] !== '0')) {
        $button_code .= '<div class="coforpaypal-order-summary" style="margin-bottom: 15px; padding: 12px 16px; border: 1px solid #e0e0e0; border-radius: 6px; background-color: #f9f9f9; max-width: ' . esc_attr($width) . 'px;">';
        $button_code .= '<h4 style="margin: 0 0 8px 0; font-size: 16px; color: #333;">' . esc_html($product['title']) . '</h4>';
        $button_code .= '<div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 14px; color: #555;"><span>' . __('Price:', 'checkout-for-paypal') . '</span><span>' . esc_html($product_price . ' ' . $currency) . '</span></div>';
        if ($has_shipping) {
            $button_code .= '<div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 14px; color: #555;"><span>' . __('Shipping:', 'checkout-for-paypal') . '</span><span>' . esc_html($shipping . ' ' . $currency) . '</span></div>';
            $total_amount = $product_price + $shipping;
            $total_amount = number_format($total_amount, 2, '.', '');
            $button_code .= '<div style="display: flex; justify-content: space-between; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #ccc; font-weight: bold; font-size: 14px; color: #222;"><span>' . __('Total:', 'checkout-for-paypal') . '</span><span>' . esc_html($total_amount . ' ' . $currency) . '</span></div>';
        }
        $button_code .= '</div>';
    }

    $button_code .= '<div id="'.esc_attr($button_container_id).'" style="'.esc_attr('max-width: '.$width.'px;').'">';
    //
    $amount_code = '<input class="coforpaypal_checkout_amount_input" type="hidden" name="amount" value="'.esc_attr($amount).'" required>';
    $amount_queryselector = "document.querySelector('#{$button_container_id} .coforpaypal_checkout_amount_input')";
    $button_code .= $amount_code;
    //
    $button_code .= '<div id="'.esc_attr($button_id).'" style="'.esc_attr('max-width: '.$width.'px;').'"></div>';
    $button_code .= '</div>';
    $ajax_url = admin_url('admin-ajax.php');
    /*
    2022, 2023, 2024 themes seem to convert front-end JavaScript & to &#038; automatically breaking the PayPal button
    changed the following logic because of this issue: https://core.trac.wordpress.org/ticket/45387#comment:14
    if(shipping.length !== 0 && !isNaN(shipping)){
    */
    $button_code .= <<<EOT
    <script>
    jQuery(document).ready(function() {
            
        function initPayPalButton{$id}() {
            var amount = {$amount_queryselector};
            var checkoutvar = {};

            var purchase_units = [];
            purchase_units[0] = {};
            purchase_units[0].amount = {};
   
            function validate(event) {
                return true;
            }
            paypal.Buttons({
                style: {
                    layout: '{$esc_js($layout)}',
                    color: '{$esc_js($color)}',
                    shape: '{$esc_js($shape)}',
                    label: '{$esc_js($label)}'
                },
                onInit: function (data, actions) {

                },  
                
                onClick: function () {
                    purchase_units[0].custom_id = '{$esc_js($product_id)}';
                    purchase_units[0].amount.value = amount.value;
                },    
                    
                createOrder: async function(data, actions) {
                    var order_data = {
                        intent: 'CAPTURE',
                        payment_source: {
                            paypal: {
                                experience_context: {
                                    payment_method_preference: 'IMMEDIATE_PAYMENT_REQUIRED',
                                }
                            }
                        },
                        purchase_units: purchase_units,           
                    };
                    let post_data = 'action=coforpaypalcheckout_pp_api_create_order&data=' + encodeURIComponent(JSON.stringify(order_data));
                    try {                
                        const response = await fetch('{$ajax_url}', {
                            method: "post",
                            headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: post_data
                        });

                        const response_data = await response.json();

                        if (response_data.order_id) {
                            console.log('Create-order API call to PayPal completed successfully');
                            return response_data.order_id;
                        } else {
                            const error_message = response_data.err_msg
                            console.error('Error occurred during create-order call to PayPal: ' + error_message);
                            throw new Error(error_message); //This will trigger an alert in the catch block below
                        }
                    } catch (error) {
                        console.error(error.message);
                        alert('Could not initiate PayPal Checkout - ' + error.message);
                    }
                },
                            
                onApprove: async function(data, actions) {
                    console.log('Sending AJAX request for capture-order call');
                    let pp_bn_data = {};
                    pp_bn_data.order_id = data.orderID;
                    pp_bn_data.checkoutvar = checkoutvar;   

                    let post_data = 'action=coforpaypalcheckout_pp_api_capture_order&data=' + encodeURIComponent(JSON.stringify(pp_bn_data));
                    try {
                        const response = await fetch('{$ajax_url}', {
                            method: "post",
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: post_data
                        });

                        const response_data = await response.json();
                        if (response_data.success) {
                            console.log('Capture-order API call to PayPal completed successfully');
                            $return_output
                        } else {
                            const error_message = response_data.err_msg
                            console.error('Error: ' + error_message);
                            throw new Error(error_message); //This will trigger an alert in the catch block below
                        }

                    } catch (error) {
                        console.error(error);
                        alert('Order could not be captured. Error: ' + JSON.stringify(error));
                    }
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
