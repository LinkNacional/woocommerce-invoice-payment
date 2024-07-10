// eslint-disable-next-line no-undef
jQuery(document).ready(function ($) {
  const $topLevelMenu = $('#toplevel_page_wc-invoice-payment')
  $topLevelMenu.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment menu-top-last')

  const $topLevelLink = $topLevelMenu.find('a').first()
  $topLevelLink.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment menu-top-last')
  $topLevelLink.attr('aria-haspopup', 'false')

  const $subMenu = $('#toplevel_page_wc-invoice-payment').find('.wp-submenu')
  $subMenu.css('min-width', 'auto')
  $subMenu.css('border-left', '0')

  //Define o link para as faturas listadas
  const $listInvoices = $('#lknListGeneratedInvoices');
  const currentUrl = window.location.href;

  $listInvoices.find('a').each(function() {
      const $link = $(this);
      const invoiceId = $link.text().trim();
      const url = new URL(currentUrl);

      url.searchParams.set('page', 'edit-invoice');
      url.searchParams.set('invoice', invoiceId);

      $link.attr('href', url.toString());
  });
})
