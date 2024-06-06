<?php
add_action('wp_ajax_coforpaypal_pp_api_create_order', 'checkout_for_paypal_pp_api_create_order');
add_action('wp_ajax_nopriv_coforpaypal_pp_api_create_order', 'checkout_for_paypal_pp_api_create_order');
add_action('wp_ajax_coforpaypal_pp_api_capture_order', 'checkout_for_paypal_pp_api_capture_order');
add_action('wp_ajax_nopriv_coforpaypal_pp_api_capture_order', 'checkout_for_paypal_pp_api_capture_order');
add_action('checkout_for_paypal_process_v2_order', 'checkout_for_paypal_process_v2_order_handler', 10, 2);

function checkout_for_paypal_pp_api_create_order(){
    //The data will be in JSON format string (not actual JSON object). By using json_decode it can be converted to a json object or array.
    $json_order_data = isset($_POST['data']) ? stripslashes_deep($_POST['data']) : '{}';
    $order_data_array = json_decode($json_order_data, true);
    $encoded_item_description = isset($order_data_array['purchase_units'][0]['description']) ? $order_data_array['purchase_units'][0]['description'] : '';
    $decoded_item_description = html_entity_decode($encoded_item_description);
    checkout_for_paypal_debug_log("Create-order request received for item: ".$decoded_item_description, true);

    //Set this decoded item name back to the order data.
    $order_data_array['purchase_units'][0]['description'] = $decoded_item_description;
    checkout_for_paypal_debug_log_array($order_data_array, true);
    if(empty($json_order_data)){
        wp_send_json(
            array(
                'success' => false,
                'err_msg' => __('Empty data received.', 'checkout-for-paypal'),
            )
        );
    }
    $options = checkout_for_paypal_get_option();
    $currency_code = $options['currency_code'];
    $description = $order_data_array['purchase_units'][0]['description'];
    $amount = $order_data_array['purchase_units'][0]['amount']['value'];
    $total_amount = $amount;   
    checkout_for_paypal_debug_log("Creating order data to send to PayPal: ", true);
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
                "description" => $description,
                "amount" => [
                    "value" => (string) $total_amount,
                    "currency_code" => $currency_code,
                ],
            ]
        ]
    ];
    //
    $shipping_preference = '';
    if(isset($order_data_array['payment_source']['paypal']['experience_context']['shipping_preference'])
            && !empty($order_data_array['payment_source']['paypal']['experience_context']['shipping_preference'])){       
        $shipping_preference = $order_data_array['payment_source']['paypal']['experience_context']['shipping_preference'];
        $pp_api_order_data['payment_source']['paypal']['experience_context']['shipping_preference'] = $shipping_preference;
    }
    //
    $amount_breakdown = false;
    //shipping
    if(isset($order_data_array['purchase_units'][0]['amount']['breakdown']['shipping']['value']) 
            && is_numeric($order_data_array['purchase_units'][0]['amount']['breakdown']['shipping']['value']) 
                && $order_data_array['purchase_units'][0]['amount']['breakdown']['shipping']['value'] > 0){
        $shipping = $order_data_array['purchase_units'][0]['amount']['breakdown']['shipping']['value'];
        $pp_api_order_data['purchase_units'][0]['amount']['breakdown']['shipping']['currency_code'] = $currency_code;
        $pp_api_order_data['purchase_units'][0]['amount']['breakdown']['shipping']['value'] = (string) $shipping;
        $total_amount = $amount + $shipping;
        $amount_breakdown = true;
    }
    //break down amount when needed
    if($amount_breakdown){
        $pp_api_order_data['purchase_units'][0]['amount']['breakdown']['item_total']['currency_code'] = $currency_code;
        $pp_api_order_data['purchase_units'][0]['amount']['breakdown']['item_total']['value'] = (string) $amount;
        $pp_api_order_data['purchase_units'][0]['amount']['value'] = (string) $total_amount;
    }
    //
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

function checkout_for_paypal_pp_api_capture_order(){
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
    do_action('checkout_for_paypal_process_v2_order', $capture_response_data, $order_details_data);
    wp_send_json_success();  
}

function checkout_for_paypal_process_v2_order_handler($capture_response_data, $order_details_data)
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
