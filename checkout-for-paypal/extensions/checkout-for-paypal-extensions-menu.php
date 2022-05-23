<?php

function checkout_for_paypal_display_extensions_menu()
{
    echo '<div class="wrap">';
    echo '<h2>' .__('Checkout for PayPal Extensions', 'checkout-for-paypal') . '</h2>';
    
    $extensions_data = array();

    $extension_1 = array(
        'name' => 'Variable Price',
        'thumbnail' => CHECKOUT_FOR_PAYPAL_URL.'/extensions/images/checkout-for-paypal-variable-price.png',
        'description' => 'Let buyers set the amount they will pay',
        'page_url' => 'https://noorsplugin.com/how-to-add-a-price-field-to-a-paypal-button/',
    );
    array_push($extensions_data, $extension_1);
    
    //Display the list
    foreach ($extensions_data as $extension) {
        ?>
        <div class="checkout_for_paypal_extensions_item_canvas">
        <div class="checkout_for_paypal_extensions_item_thumb">
            <img src="<?php echo esc_url($extension['thumbnail']);?>" alt="<?php echo esc_attr($extension['name']);?>">
        </div>
        <div class="checkout_for_paypal_extensions_item_body">
        <div class="checkout_for_paypal_extensions_item_name">
            <a href="<?php echo esc_url($extension['page_url']);?>" target="_blank"><?php echo esc_html($extension['name']);?></a>
        </div>
        <div class="checkout_for_paypal_extensions_item_description">
        <?php echo esc_html($extension['description']);?>
        </div>
        <div class="checkout_for_paypal_extensions_item_details_link">
        <a href="<?php echo esc_url($extension['page_url']);?>" class="checkout_for_paypal_extensions_view_details" target="_blank">View Details</a>
        </div>    
        </div>
        </div>
        <?php
    } 
    echo '</div>';//end of wrap
}
