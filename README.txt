=== Invoice Payment for WooCommerce ===
Contributors: linknacional
Donate link: https://www.linknacional.com/wordpress/plugins/
Tags: woocommerce, invoice, payment
Requires at least: 5.7
Tested up to: 6.5
Stable tag: 1.4.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Invoice payment generation and management for WooCommerce.

== Description ==
The free [Invoice Issue plugin](https://www.linknacional.com/wordpress/plugins/) is an extension for  WooCommerce, which came to facilitate billing over the internet. With it, you can generate new invoices and send them to your customers via email, with a payment link. Another great advantage is that it can offer different payment options for paying off the invoice.

**Dependencies**

Invoice Payment for WooCommerce plugin is dependent on WooCommerce plugin, please make sure WooCommerce is installed and properly configured before starting Invoice Payment for WooCommerce installation.

**User instructions**

1. Search the WordPress sidebar for 'Invoice Payment for WooCommerce';

2. In the plugin options look for 'Add invoice';

3. Fill in the customer data, currency, payment method and add the charges;

4. If you want to send the invoice to the customer's email, select the 'Send invoice to customer' option in the invoice actions;

5. Click save;

You have created your first invoice with the Invoice Payment for WooCommerce plugin.

== Installation ==

1. Look in the sidebar for the WordPress plugins area;

2. In installed plugins look for the 'add new' option in the header;

3. Click on the 'submit plugin' option in the page title and upload the woocommerce-invoice-payment-main.zip plugin;

4. Click on the 'install now' button and then activate the installed plugin;

The Invoice Payment for WooCommerce plugin is now live and working.

== Usage ==

= Product Settings =

1. Within the product editing or creating page, enable the subscription option to configure recurring invoices.
2. Specify the frequency of invoice generation according to your preferences.

= Invoices Settings =

1. In the WordPress sidebar, find and click on "Invoices".
2. Configure default settings such as PDF template, logo URL, footer, sender details, and text preceding the payment link.
3. To manually add an invoice, visit the "Add Invoice" page, where both standard and recurring invoices can be created.

= Manual Invoice Creation =

1. Visit the "Add Invoice" page to manually create invoices.
2. Choose between standard or recurring invoices, input necessary details, and save.

= Subscription and Invoice Lists =

1. To list subscriptions click on "Subscriptions" in the WordPress sidebar.
2. To list invoices click on "Invoices" in the WordPress sidebar.


== Frequently Asked Questions ==

= What is the plugin license? =

* This plugin is released under a GPL license.

= What is needed to use this plugin? =

* WooCommerce version 4.0 or latter installed and active.

== Screenshots ==

1. Add new invoice utilizing our convenient invoice builder;

2. Get an URL for your client to pay;

3. See all generated invoices and their status.

== Changelog ==

= 1.4.0 = *22/04/2024*
* Adjust escape variables and request methods to enhance security
* Add modal for sharing invoice link
* Add products with recurring subscriptions
* Add multiple payment methods option

= 1.3.2 = *14/02/2024*
* Substitution of echo to esc_html_e or esc_attr_e, adjust to comply with wordpress regulations

= 1.3.1 = *06/11/23*
* add cache no-store attribute to the PDF generation request

= 1.3.0 = *01/11/23*
* Add default footer setting
* Add text_before_payment_link setting
* Add setting for sender details
* Adjust existing templates to handle the new settings
* Add new template

= 1.2.1 = *20/10/23*
* Adjust to get logo with curl, adjust to work in directory installed wordpress.

= 1.2.0 = *18/10/23*
* Add PDF generation for invoices

= 1.1.4 = *07/06/23*
* Fix invoices table error when the invoice order is deleted.

= 1.1.3 = *14/03/23*
* Payment methods bug correction;

= 1.1.2 = *10/03/23*
* Bug corrections;

= 1.1.1 = *10/03/23*
* Links update;
* Setting title change;
* Addition of description;
* Dev Container configuration.

= 1.1.0 = *02/09/22*
* Implemented invoice due date;
* On invoice payment page load the defined payment method is open;
* Users with shop_manager permission can generate and edit invoices;
* Optimized JS and CSS load.

= 1.0.0 = *01/06/22*
* Plugin launch.

== Upgrade Notice ==

= 1.0.0 =
* Plugin launch.
