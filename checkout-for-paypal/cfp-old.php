<?php
function checkout_for_paypal_old_button_handler($atts) {
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
    checkout_for_paypal_debug_log('Received a response from frontend - older integration', true);
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
