# Invoice Payment for WooCommerce

[Invoice Payment for WooCommerce](https://www.linknacional.com.br/wordpress/woocommerce/faturas/) is an extension plugin for WooCommerce that aims to facilitate the management of invoices through the platform. It sends a new invoice email notification for the client and invoice creation with a direct payment link.

Plugin at WordPress marketplace 
https://wordpress.org/plugins/lkn-wc-gateway-cielo/

## Dependencies

Invoice Payment for WooCommerce plugin is dependent on WooCommerce plugin, please make sure WooCommerce is installed and properly configured before starting Invoice Payment for WooCommerce installation.


## Installation

1) Look in the sidebar for the WordPress plugins area;

2) In installed plugins look for the 'add new' option in the header;

3) Click on the 'submit plugin' option in the page title and upload the lkn-wc-invoice-payment.zip plugin;

4) Click on the 'install now' button and then activate the installed plugin;

The Invoice Payment for WooCommerce plugin is now live and working.

## Usage

### Product Settings

1) Within the product editing or creating page, enable the subscription option to configure recurring invoices.
2) Specify the frequency of invoice generation according to your preferences.

### Invoices Settings

1) In the WordPress sidebar, find and click on "Invoices".
2) Configure default settings such as PDF template, logo URL, footer, sender details, and text preceding the payment link.
3) To manually add an invoice, visit the "Add Invoice" page, where both standard and recurring invoices can be created.

### Manual Invoice Creation

1) Visit the "Add Invoice" page to manually create invoices.
2) Choose between standard or recurring invoices, input necessary details, and save.

### Subscription and Invoice Lists

1) To list subscriptions click on "Subscriptions" in the WordPress sidebar.
2) To list invoices click on "Invoices" in the WordPress sidebar.

## Development notes

HTML to PDF lib: https://github.com/dompdf/dompdf

Setup a WYSIWYG editor in a textarea: https://codex.wordpress.org/Javascript_Reference/wp.editor

QR Code lib: https://phpqrcode.sourceforge.net/#home
