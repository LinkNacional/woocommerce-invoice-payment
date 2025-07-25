=== Link Invoice Payment for WooCommerce ===
Contributors: linknacional
Donate link: https://www.linknacional.com/wordpress/plugins/
Tags: subscription, invoice, payment, recorrente, faturas
Requires at least: 5.7
Tested up to: 6.8
Stable tag: 2.5.1
Requires PHP: 7.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Link Invoice Payment plugin is a powerful extension for WooCommerce, designed to simplify online billing. Whether for one-time or recurring invoices.

== Description ==
The **Link Invoice Payment** plugin is a powerful and free extension for **WooCommerce**, designed to simplify online billing — whether for one-time or recurring invoices. With [Link Invoice Payment](https://www.linknacional.com.br/wordpress/woocommerce/faturas/) plugin for WooCommerce, you can easily generate both **one-time** and **recurring** invoices and send them to your customers via email, WhatsApp, or social networks — complete with a secure payment link. One of its biggest advantages is the ability to offer multiple payment options to settle the invoice.

Now enhanced with even more advanced features, **Link Invoice Payment** is the perfect tool for flexible and professional invoicing in your [WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/) store.

## ✅ Key Features

- **Recurring Invoices** – Automate billing for subscriptions or regular payments  
- **Subscription-type Products** – Easily manage recurring billing cycles  
- **PDF Invoices** – Generate professional downloadable and printable invoice documents  
- **Payment Links** – Instantly share payment URLs for quick customer access  
- **Multi-currency Invoices** – Issue invoices in different currencies based on customer location  
- **Due Date Management** – Set custom due dates for each invoice  
- **Multiple or Specific Payment Methods** – Choose which payment gateways appear per invoice  
- **Multi-language Support** – Display invoices in the language preferred by your customer  
- **Partial Order Payments in WooCommerce** – Allow customers to pay part of an order upfront using multiple payment methods
- **Payment Method Discount** – Set a percentage or fixed discount value for each payment type
- **Payment Method Fee** – Set a percentage or fixed value to charge an additional amount for each payment type

And much more!


**Dependencies**

Link Invoice Payment for WooCommerce plugin is dependent on WooCommerce plugin, please make sure WooCommerce is installed and properly configured before starting Invoice Payment for WooCommerce installation.

*External Libraries used*
* We use the PHP external library [DOMPDF](https://github.com/dompdf/dompdf) for generating PDF invoices.
* We use PHP [QR Code encoder](https://phpqrcode.sourceforge.net/) for generating the redirect URL page.
* We use JS Library [Tailwind](https://tailwindcss.com/) for additional CSS and HTML styling.

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

1. See all generated invoices and their status.
2. See all generated subscriptions.
3. Add new invoice utilizing our convenient invoice builder.
4. Customize the settings of your invoices.
5. Customize the settings of your subscriptions.
6. Edit details of your subscription.

== Changelog ==
= 2.5.1 = *07/23/2025*
* Add configuration to set fees or discounts for payment methods.

= 2.5.0 = *07/21/2025*
* Add configuration to set fees or discounts for payment methods.

= 2.4.3 = *07/11/2025*
* Fix for adding products to orders created with CartFlows.

= 2.4.2 = *07/03/2025*
* Fix script and CSS for partial payment.

= 2.4.1 = *07/02/2025*
* Fix partial payment script.

= 2.4.0 = *06/12/2025*
* Add partial payments for all payment methods.

= 2.3.4 = *05/29/2025*
* Fix fatal error when editing page.

= 2.3.3 = *05/27/2025*
* Update description.

= 2.3.2 = *05/27/2025*
* Update description and fixed icons.

= 2.3.1 = *05/27/2025*
* Add blueprint to the WordPress page.

= 2.3.0 = *02/14/2025*
* Add field to search for user email;
* Add hook to process subscription automatically;
* Add function to force customer registration at checkout.

= 2.2.1 = *31/01/2025*
* Compatibility fix with the "Payment Gateway Based Fees for WooCommerce" plugin;
* Updating plugin description link.

= 2.2.0 = *14/12/2024*
* Add subscription reference on the invoice page;
* Add setting to define the invoice PDF language;
* Fix cron event deletion.

= 2.0.1 = *18/11/2024*
* Bug fix for payment methods that require the order’s country.

= 2.0.0 = *12/11/2024*
* Complete refactor of class loading (PSR4);
* Fix vulnerabilities.

= 1.7.2 = *30/10/2024*
* Fix errors in PDFs with images;
* Fix translation errors.

= 1.7.1 = *04/07/2024*
* Fix line break bug in invoice extra information;
* Change text in email verification settings.

= 1.7.0 = *04/07/2024*
* Add setting to set a limit on invoices generated per subscription;
* Add card to display IDs of invoices generated by the subscription;
* Add "Subscription" column in the invoices table;
* Add configuration that allows the administrator to define the lead time for invoice generation;
* Change in PDF image setting to use the WordPress modal.

= 1.6.0 = *06/06/2024*
* Add visual feedback when clicking the download invoices button;
* Add alert on subscription creation;
* Add button to create invoice on the list invoices page;
* Add button to create subscription on the list subscriptions page;
* Fix currency listing in invoice settings;
* Fix bug when clicking on the edit invoices section;
* Fix bug in template select showing the same image;
* Fix function to add and delete cron events;
* Fix label to enable login page for invoices.

= 1.5.0 = *08/05/2024*
* Add the due date to the invoice PDF.
* Add configuration to enable and disable email verification.
* Fix PDF generation.
* Fix submenu Edit Invoice.

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
= 2.0.0 =
* Important security fixes released in this version.

= 1.0.0 =
* Plugin launch.
