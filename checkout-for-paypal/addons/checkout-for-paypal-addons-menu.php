<?php

function checkout_for_paypal_display_addons_menu()
{
    echo '<div class="wrap">';
    echo '<h2>' .__('Checkout for PayPal Add-ons', 'checkout-for-paypal') . '</h2>';
    
    $addons_data = array();

    $addon_1 = array(
        'name' => 'Variable Price',
        'thumbnail' => CHECKOUT_FOR_PAYPAL_URL.'/addons/images/checkout-for-paypal-variable-price.png',
        'description' => 'Let buyers set the amount they will pay',
        'page_url' => 'https://noorsplugin.com/how-to-add-a-price-field-to-a-paypal-button/',
    );
    array_push($addons_data, $addon_1);
    
    $addon_2 = array(
        'name' => 'Dynamic Buttons',
        'thumbnail' => CHECKOUT_FOR_PAYPAL_URL.'/addons/images/checkout-for-paypal-dynamic-buttons.png',
        'description' => 'Change PayPal buttons dynamically by adding parameters to URLs',
        'page_url' => 'https://noorsplugin.com/checkout-for-paypal-dynamic-buttons/',
    );
    array_push($addons_data, $addon_2);
    
    $addon_3 = array(
        'name' => 'Contact Form 7 Integration',
        'thumbnail' => CHECKOUT_FOR_PAYPAL_URL.'/addons/images/checkout-for-paypal-contact-form-7-integration.png',
        'description' => 'Accept PayPal payments with Contact Form 7',
        'page_url' => 'https://noorsplugin.com/checkout-for-paypal-integration-with-contact-form-7/',
    );
    array_push($addons_data, $addon_3);
    
    //Display the list
    foreach ($addons_data as $addon) {
        ?>
        <div class="checkout_for_paypal_addons_item_canvas">
        <div class="checkout_for_paypal_addons_item_thumb">
            <img src="<?php echo esc_url($addon['thumbnail']);?>" alt="<?php echo esc_attr($addon['name']);?>">
        </div>
        <div class="checkout_for_paypal_addons_item_body">
        <div class="checkout_for_paypal_addons_item_name">
            <a href="<?php echo esc_url($addon['page_url']);?>" target="_blank"><?php echo esc_html($addon['name']);?></a>
        </div>
        <div class="checkout_for_paypal_addons_item_description">
        <?php echo esc_html($addon['description']);?>
        </div>
        <div class="checkout_for_paypal_addons_item_details_link">
        <a href="<?php echo esc_url($addon['page_url']);?>" class="checkout_for_paypal_addons_view_details" target="_blank">View Details</a>
        </div>    
        </div>
        </div>
        <?php
    } 
    echo '</div>';//end of wrap
}
