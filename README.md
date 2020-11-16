# Checkout for PayPal

## Description

[Checkout for PayPal](https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/) plugin allows you to easily create PayPal smart payment buttons using PayPal's checkout API. It generates dynamic payment buttons using shortcodes that enable PayPal checkout on your WordPress site. It was developed by [Noor Alam](https://noorsplugin.com/about/) from [Noor's Plugin](https://noorsplugin.com/) and is currently being used on hundreds of websites.

Your customers will be able to pay for your products using PayPal, Pay Later or Credit Card. All you need to do is insert a shortcode into one of your web pages and your website will be ready to go live.

Checkout for PayPal supports PayPal Sandbox. PayPal Sandbox is a simulation environment which allows you to do test purchases between a test buyer and a seller account. This is to make sure that your store can process PayPal transactions without any issues. It also helps you get prepared before selling to real customers.

## Features

* Sell products or services using PayPal
* Create PayPal buttons on the fly in a post/page using shortcodes
* Allow shoppers to complete their purchases using PayPal, Pay Later, credit card and debit card payments 
* Accept once off payments
* Offer a simplified and secure checkout experience
* View or Manage orders received via PayPal buttons from your WordPress admin dashboard
* Quick settings configurations
* Enable debug to troubleshoot various issues (e.g. orders not getting updated)
* Switch your store to PayPal sandbox mode for testing
* Compatible with the latest version of WordPress
* Compatible with any WordPress theme
* Sell in any currency supported by PayPal

## Usage

Checkout for PayPal uses PayPal REST API to add smart payment buttons to your website. To Generate REST API credentials for the sandbox and live environments:

* Log in to the PayPal Developer Dashboard with your PayPal account credentials.
* On **My Apps & Credentials**, use the toggle to switch between live and sandbox testing apps (If you are creating a Sandbox app, you will also need to select a test business account that will act as the API caller).
* Navigate to the **REST API apps** section and click **Create App**.
* Type a name for your app and click **Create App**. The app details page opens and displays your credentials.
* Copy and save the client ID and secret for your app.
* Review your app details and save your app.

Once the plugin is installed go to the settings menu to configure some default options (Checkout for PayPal -> Settings).

* Client ID: The client ID for your PayPal REST API app
* Currency Code: The default currency of the payment
* Return URL: The page URL to which the customer will be redirected after a successful payment.

In order to create a smart payment button insert the shortcode like the following:

```[checkout_for_paypal item_description="My cool product" amount="1.00"]```

Replace the values with your item description and amount.

## PayPal Button Parameters

You can use additional parameters to customize your PayPal buttons.

* **amount** - The price of the item (e.g. ```amount="4.95"```).
* **item_description** - Description of the item.
* **currency** - The currency of the payment (e.g. ```currency="USD"```).
* **return_url** - The URL to which the user will be redirected after the payment (e.g. ```return_url="https://example.com/thank-you/"```).

## Documentation

For detailed documentation please visit the [PayPal Plugin](https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/) page.
