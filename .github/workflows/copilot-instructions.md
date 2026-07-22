# Invoice Payment for WooCommerce — Development Guidelines

## Plugin Identity

- **Plugin name:** Invoice Payment for WooCommerce
- **Text domain:** `wc-invoice-payment`
- **PHP namespace:** `LknWc\WcInvoicePayment`
- **PSR-4 roots:** `Includes/` → `LknWc\WcInvoicePayment\Includes\`, `Admin/` → `LknWc\WcInvoicePayment\Admin\`, `Public/` → `LknWc\WcInvoicePayment\PublicView\`
- **Version constant:** `WC_PAYMENT_INVOICE_VERSION`
- **Path constants:** `WC_PAYMENT_INVOICE_ROOT_DIR`, `WC_PAYMENT_INVOICE_ROOT_URL`, `WC_PAYMENT_INVOICE_TRANSLATION_PATH`
- **Author:** Link Nacional
- **Required plugin:** WooCommerce
- **Stack:** WordPress 6.8+, PHP 8.2+, WooCommerce (latest stable), TailwindCSS 3

## Naming Conventions

Use unique prefixes consistently to avoid conflicts with other plugins:

| Type | Convention | Example |
|------|-----------|---------|
| PHP classes | `WcPaymentInvoice*` | `WcPaymentInvoiceSettings` |
| Admin/utility | `LknWcip*` | `LknWcipListTable` |
| WC product class | `WC_Product_*` | `WC_Product_Donation` |
| Options / meta keys | `lkn_wcip_*` | `lkn_wcip_quote_mode` |
| Custom hooks | `lkn_wcip_*` | `lkn_wcip_cron_hook` |
| AJAX actions | `lkn_wcip_*` | `wp_ajax_lkn_wcip_get_product_data` |
| JS/CSS handles | `wc-invoice-payment-*` | `wc-invoice-payment-admin` |

## Architecture

### Loader Pattern (`WcPaymentInvoiceLoader`)
The loader collects all `add_action` / `add_filter` calls into internal arrays and registers them with WordPress only when `run()` is called.

> **Critical constraint:** `run()` is called **once** at plugin boot (before any WordPress hook fires). Any `$loader->add_action()` or `$loader->add_filter()` call that happens **inside a hook callback** (e.g., inside `woocommerce_loaded`, `init`, etc.) will **never be registered with WordPress**. In those cases, use the native WordPress API directly:
> ```php
> // ✅ Correct — inside a hook callback
> add_action('woocommerce_settings_tabs_' . $id, array($this, 'render'));
> add_filter('woocommerce_settings_tabs_array', array($this, 'add_tab'), 50);
>
> // ❌ Wrong — loader already ran, this has no effect
> $this->loader->add_action('woocommerce_settings_tabs_' . $id, $this, 'render');
> ```

### Boot Sequence
```
wc-invoice-payment.php loaded → run_wc_payment_invoice()
  → new WcPaymentInvoice() → load_dependencies() → define_*_hooks()
  → $loader->run()   ← all hooks registered with WordPress here
```
After `run()`, any remaining registrations must use native `add_action()`/`add_filter()`.

### WordPress / WooCommerce Hook Order
When working with hook timing, follow this sequence:
```
plugins_loaded
  → woocommerce_loaded   (WC classes available, e.g. WC_Product_Simple)
  → init                 (textdomain loaded here)
  → woocommerce_init     (WC cart/session fully ready)
  → wp_loaded
```
- Load classes that extend WC types (e.g., `WC_Product_Donation`) on `woocommerce_loaded`.
- Register custom product types via `woocommerce_product_class` filter (requires `accepted_args = 2`).
- Call `manage_quote_gateway_status` on `woocommerce_init`, not `init`.

## Features & Modules

| Module | Main class | Notes |
|--------|-----------|-------|
| Core invoice/payment | `WcPaymentInvoice` | Orchestrates all hooks |
| Admin UI | `WcPaymentInvoiceAdmin` | Settings pages, list tables |
| Settings tabs | `WcPaymentInvoiceSettings` | WooCommerce settings integration |
| Subscriptions | `WcPaymentInvoiceSubscription` | Recurring invoice logic |
| Donations | `WcPaymentInvoiceDonation` | Custom `donation` product type |
| Quotes | `WcPaymentInvoiceQuote` + `WcPaymentInvoiceQuoteGateway` | Quote-to-order flow |
| Partial payments | `WcPaymentInvoicePartial` | Split payment support |
| OTP email | `WcPaymentInvoiceOtpEmail` | One-time password via email |
| WhatsApp button | `WcPaymentInvoiceWhatsAppButton` | Customer contact shortcut |
| REST endpoints | `WcPaymentInvoiceLoaderRest` | REST API routes |
| PDF generation | `WcPaymentInvoicePdfTemplates` | Uses dompdf |

## Code Standards

**PHP**
- Follow WordPress Coding Standards (snake_case functions, PascalCase classes)
- PHP 8.2+ features allowed (match, enums, readonly, named args)
- Nonce verification on every form submission: `wp_verify_nonce()`
- Sanitize all inputs: `sanitize_text_field()`, `sanitize_email()`, `absint()`, etc.
- Escape all outputs: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Internationalize every user-facing string: `__('text', 'wc-invoice-payment')`, `_e()`, `_n()`

**JavaScript / CSS**
- ESNext syntax; ESLint with airbnb-base config
- TailwindCSS for styling (build via `npm run build:css`)
- Admin JS handles: `wc-invoice-payment-admin`, `wc-invoice-payment-invoice-edit`, etc.

## Build Commands

```bash
# Install PHP dependencies (includes dompdf)
composer install

# Install JS/CSS dependencies
npm install

# Build TailwindCSS (watch mode)
npm run build:css
# → reads Public/css/tailwind.css → outputs Public/css/style.css

# Static analysis
vendor/bin/phan
```

> There is no `npm run build` or `composer test` script — do not suggest them.

## Database & Options

- Use `get_option()` / `update_option()` with the `lkn_wcip_*` prefix
- Only call `update_option()` when the value has actually changed (compare before writing)
- Use `$wpdb` prepared statements for any custom SQL
- Cleanup routines are in `uninstall.php`

## Error Handling & Performance

- Use `WP_Error` for recoverable errors; log with `wc_get_logger()` when WooCommerce is active
- Cache expensive operations with WordPress transients
- Avoid calling `update_option()` on every request — check current value first
- Register admin-only assets only on admin pages (`is_admin()`, correct screen check)

## Architecture & SOLID Principles

**Single Responsibility Principle (SRP)**
- Each class has one reason to change — separate concerns between classes: `WcPaymentInvoiceAdmin` para lógica de admin, `WcPaymentInvoicePublic` para frontend, `WcPaymentInvoiceActivator` para instalação, `WcPaymentInvoiceSettings` para configurações, etc.
- Functions should do one thing well — split large functions into smaller, focused ones

**Open/Closed Principle (OCP)**
- Extend functionality through hooks and filters, not by modifying existing classes
- Use WordPress action/filter hooks: `add_action()`, `add_filter()`, `apply_filters()`, `do_action()`
- Create custom hooks with the `lkn_wcip_` prefix for extensibility: `do_action('lkn_wcip_after_invoice_save', $invoice_id)`

**Liskov Substitution Principle (LSP)**
- Child classes must be substitutable for parent classes without breaking functionality
- `WC_Product_Donation` extends `WC_Product_Simple` — it must behave as a valid WC product in all contexts (cart, session, REST)

**Interface Segregation Principle (ISP)**
- Create small, focused interfaces rather than large monolithic ones
- Separate admin interfaces from public interfaces

**Dependency Inversion Principle (DIP)**
- Depend on abstractions, not concrete implementations
- Use dependency injection where possible — the loader, WC classes, and PDF renderer are injected rather than instantiated inline