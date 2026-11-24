=== Checkout for PayPal ===
Contributors: naa986
Donate link: https://noorsplugin.com/
Tags: paypal, membership, user, ecommerce, login
Requires at least: 5.5
Tested up to: 7.1
Stable tag: 1.0.50
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept PayPal payments and sell WordPress memberships with PayPal Checkout. Protect pages, manage members, and accept payments without WooCommerce.

== Description ==

[Checkout for PayPal](https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/) is a lightweight WordPress PayPal plugin and membership solution that lets you accept PayPal and credit card payments directly through PayPal's Checkout API (no shopping cart or WooCommerce required).

It gives you everything you need whether you want to add a simple PayPal Buy Now button for a single product, accept donations and service payments, or build a paid membership site with restricted content.

=== Checkout for PayPal Add-ons ===

* [Variable Price](https://noorsplugin.com/how-to-add-a-price-field-to-a-paypal-button/)
* [Contact Form 7 Integration](https://noorsplugin.com/checkout-for-paypal-integration-with-contact-form-7/)
* [Order Export](https://noorsplugin.com/checkout-for-paypal-order-export/)

=== PayPal Checkout & Credit Cards ===

Accept PayPal and credit/debit card payments securely through PayPal's Checkout/Orders API.

=== Built-in WordPress Membership System ===

Turn any product into a paid membership with automatic WordPress user account creation, role assignment, and isolated member management.

=== Content Protection & Paywall === 

Restrict access to entire pages/posts via the editor box.

=== Membership Expiration & Renewals ===

Set fixed-duration memberships (e.g., 30 days, 365 days) or lifetime access. Expired members automatically lose access and see customizable renewal messages.

=== Customizable Paywall Messages & Emails ===

Configure distinct messages for logged-out visitors, pending accounts, and expired memberships. You can also set automated emails for new users, existing users, and membership activations.

=== No WooCommerce or Shopping Cart Required ===

Add PayPal payment buttons and paid memberships without the bloat of a full ecommerce platform.

=== Product & Order Management ===

Create products with custom prices, button styles, and membership settings. View all PayPal orders and linked WordPress users from your dashboard.

=== PayPal Sandbox Support ===

Test your PayPal Checkout and membership flows safely in PayPal's sandbox environment before going live.

=== Responsive & Lightweight ===

Works seamlessly across desktop and mobile devices without slowing down your WordPress site.

=== How to Use the Plugin ===

1. Navigate to **Checkout for PayPal > Settings** and configure your PayPal API keys, currency, and return/cancel page URLs.
2. Open your checkout page and ensure it contains the `[coforpaypal_checkout]` shortcode.
3. Go to **Checkout for PayPal > Products** to create a new product.
4. Add a PayPal Buy Now button to any page or post using the shortcode: `[coforpaypal_product id="1"]` (replace `1` with your product ID).

=== How to Set Up Paid Memberships ===

1. Edit a product under **Checkout for PayPal > Products** and enable **Membership Product**.
2. Choose the WordPress user role for new members, set the membership duration in days (or `0` for lifetime access), and select the default status (`Active` or `Pending`).
3. Customize your paywall messages and membership emails under **Checkout for PayPal > Settings > Membership**.
4. Protect content by enabling **Membership Protection** in the page/post editor sidebar.
5. Manage members, statuses, and expiration dates anytime under **Checkout for PayPal > Members**.

= Documentation =

[https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/](https://noorsplugin.com/checkout-for-paypal-wordpress-plugin/) 

= Translation =

If you are a non-English speaker please help translate Checkout for PayPal into your language.

*Note: This is NOT an official PayPal product.*

== Installation ==

1. Go to the Add New plugins screen in your WordPress Dashboard
1. Click the upload tab
1. Browse for the plugin file (checkout-for-paypal.zip) on your computer
1. Click "Install Now" and then hit the activate button

== Frequently Asked Questions ==

= Do I need a business PayPal account? =

Yes. To use the PayPal Checkout API, you will need a free PayPal Business account.

= Can I test payments before going live? =

Yes, you can use PayPal's sandbox mode for safe testing.

= Does it work with WooCommerce? =

This plugin works independently without WooCommerce. WooCommerce has its own PayPal integrations.

= How do I protect pages or content for members only? =

You can restrict an entire page or post using the "Checkout for PayPal: Membership Protection" box in the WordPress editor.

= How do membership expirations work? =

When creating a membership product, you can set a duration. Once a member's duration passes, their status changes to Expired.

== Screenshots ==

1. PayPal Checkout
2. Orders Menu
3. Purchase Receipt Email Settings
4. Sale Notification Email Settings

== Upgrade Notice ==
none

== Changelog ==

= 1.0.50 =
* Added membership system.

= 1.0.49 =
* Added button image option for product.

= 1.0.48 =
* Added a product interface.

= 1.0.47 =
* Removed older integration.

= 1.0.46 =
* Changes to URL parameters.

= 1.0.45 =
* Changed order menu icon.

= 1.0.44 =
* Improvements to code.

= 1.0.43 =
* Save payment data after a new order is added.

= 1.0.42 =
* Added an option to edit order data shown in the table.

= 1.0.41 =
* Added the buyer country option to test checkout as a buyer from that country.

= 1.0.40 =
* Added security to api keys.

= 1.0.39 =
* Some improvements in security.

= 1.0.38 =
* Improvement to code that changes the layout, color and shape of the button.

= 1.0.37 =
* Fixed broken parameters in the return URL.

= 1.0.36 =
* Fixed a bug that caused an issue with test mode purchases.

= 1.0.35 =
* Fixed an issue with settings link.

= 1.0.34 =
* File naming changes.

= 1.0.33 =
* Some improvements in security reported by Wordfence.

= 1.0.32 =
* Some improvements in security reported by Wordfence.

= 1.0.31 =
* Added support for variable price description options.

= 1.0.30 =
* Changed a logical statement to get around a bug in some default themes.

= 1.0.29 =
* Added an option to disable the Orders API v2 notice.

= 1.0.28 =
* Updated the plugin to be compatible with PayPal Orders API Version v2.

= 1.0.27 =
* Made changes to the code that retrieve the plugin url and path.

= 1.0.26 =
* Added options to enable/disable funding sources.

= 1.0.25 =
* Added an email tag for the customer's phone number.
* Better debug logging.

= 1.0.24 =
* Additional check for the settings link.

= 1.0.23 =
* Added an option to set the locale for the buyer.

= 1.0.22 =
* Added a parameter to specify the shipping amount.
* Added email tags for the item total and shipping amount.
* Improved the checkout flow.

= 1.0.21 =
* Added an option to load PayPal scripts on every page.

= 1.0.20 =
* Added support for Contact Form 7 integration.

= 1.0.19 =
* Added an option to configure a payment cancellation page.

= 1.0.18 =
* Added support for dynamic buttons.

= 1.0.17 =
* Fixed an error in loading scripts when the rendered page does not exist.

= 1.0.16 =
* Added email settings.

= 1.0.15 =
* Added an option to show the Venmo button at checkout.

= 1.0.14 =
* Made some security related improvements suggested by wpscan.

= 1.0.13 =
* Added horizontal layout option to PayPal checkout button.

= 1.0.12 =
* Made some improvements in the plugin settings.

= 1.0.11 =
* Made item information available in the orders menu.

= 1.0.10 =
* Added pill shape as an available style to PayPal checkout button.

= 1.0.9 =
* Added support for the variable price add-on.

= 1.0.8 =
* Customer email address is now shown in the orders menu.

= 1.0.7 =
* Added Venmo as a payment option.

= 1.0.6 =
* Added the width parameter to customize the size of the PayPal button.

= 1.0.5 =
* Added the color parameter to customize the color of the PayPal button.

= 1.0.4 =
* The shortcode can be used in a custom field.

= 1.0.3 =
* Added the "no_shipping" parameter to disable shipping address collection.

= 1.0.2 =
* Added fix for a PayPal bug where specifying a different currency would trigger an error.

= 1.0.1 =
* First commit
