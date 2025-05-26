# Link Invoice Payment for WooCommerce
The [Link Invoice Payment](https://www.linknacional.com.br/wordpress/woocommerce/faturas/) plugin is a powerful and free extension for WooCommerce, designed to simplify online billing — whether for one-time or recurring invoices.

## Description
The **Link Invoice Payment** plugin is a powerful and free extension for **WooCommerce**, designed to simplify online billing — whether for one-time or recurring invoices. With [Link Invoice Payment](https://www.linknacional.com.br/wordpress/woocommerce/faturas/) plugin for WooCommerce, you can easily generate both **one-time** and **recurring** invoices and send them to your customers via email, WhatsApp, or social networks — complete with a secure payment link. One of its biggest advantages is the ability to offer multiple payment options to settle the invoice.

Now enhanced with even more advanced features, **Link Invoice Payment** is the perfect tool for flexible and professional invoicing in your WooCommerce store.

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

And much more!

Plugin at WordPress marketplace: https://wordpress.org/plugins/lkn-wc-gateway-cielo/

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

# How to integrate with subscription processing

This document explains how to use the `lkn_process_subscription_{paymentMethod}` filter to process subscriptions in your system.

## Filter Name

The filter name should include the payment method ID. For example, if the payment method is `creditCard`, the filter should be named `lkn_process_subscription_creditCard`.

## Filter Parameters

The `lkn_process_subscription_{paymentMethod}` filter receives three parameters:

1. **New Subscription Invoice ID** (`$newOrderId`): This is the ID of the new invoice generated for the subscription.
2. **Customer ID** (`$customerId`): This is the ID of the customer associated with the subscription and invoice.
3. **Retry** (`$retry`): This parameter is a boolean that indicates whether the current call is a retry.

## Expected Filter Return

The `lkn_process_subscription_{paymentMethod}` filter should return an associative array with the following elements:

1. **status**: A boolean value indicating whether the subscription processing was successful. If `true`, the system understands that the payment was completed and calls the `payment_complete()` method to finalize the process.
2. **makeRetry**: A boolean value that signals whether a new attempt to process the subscription should be made. This parameter is used when the processing was not successful, but the application logic allows for a retry.
3. **nextCronHours**: A numeric value (integer) that defines the interval, in hours, for the next processing attempt. If `makeRetry` is `true` and `nextCronHours` is greater than 0, the system will schedule a new execution of the filter after this interval using the `wp_schedule_single_event` function.

## Processing Logic

### Example of filter usage

```php
add_filter('lkn_process_subscription_genericPayment', 'process_subscription_generic_payment', 10, 3);

function process_subscription_generic_payment($newOrderId, $customerId, $retry) {
    // Get the subscription order
    $order = wc_get_order($newOrderId);
    
    if (!$order) {
        return [
            'status' => false,
            'makeRetry' => false,
            'nextCronHours' => 0
        ];
    }

    // Simulate the call to the payment gateway API
    $paymentResponse = process_payment_with_gateway($order);

    if ($paymentResponse['success']) {
        return [
            'status' => true, // Successful payment
            'makeRetry' => false,
            'nextCronHours' => 0
        ];
    } else {
        return [
            'status' => false, // Payment failed
            'makeRetry' => true, // Retry
            'nextCronHours' => 6 // Try again in 6 hours
        ];
    }
}
```

### Automatic Status Update

The subscription status is automatically updated only if the success status (`$status`) is `true` and it is not a retry. If it is a retry, the method itself must change the status.
