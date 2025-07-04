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

  // Define o link para as faturas listadas
  const $listInvoices = $('#lknListGeneratedInvoices')
  const currentUrl = window.location.href

  $listInvoices.find('a').each(function () {
    const $link = $(this)
    const invoiceId = $link.text().trim()
    const url = new URL(currentUrl)

    url.searchParams.set('page', 'edit-invoice')
    url.searchParams.set('invoice', invoiceId)

    $link.attr('href', url.toString())
  })

  const $showSubscriptionInvoices = $('#lknShowSubscription')

  $showSubscriptionInvoices.find('a').each(function () {
    const $link = $(this)
    const invoiceId = $link.text().trim()
    const url = new URL(currentUrl)

    url.searchParams.set('page', 'edit-subscription')
    url.searchParams.set('invoice', invoiceId)

    $link.attr('href', url.toString())
  })

  if (window.location.search.includes("page=edit-subscription")) {
    const removeUserButton = document.querySelector(".select2-selection__clear");
    if (removeUserButton) {
        removeUserButton.remove();
    }
  }

  const elementBar = document.querySelector('.wp-has-submenu.wp-not-current-submenu.menu-top.toplevel_page_wc-invoice-payment.menu-top-last.wp-has-current-submenu.wp-menu-open')
  if(elementBar) {
    const firstItem = elementBar.querySelector('.wp-submenu .wp-first-item a');
    if (firstItem) {
      firstItem.classList.add('current');
      firstItem.parentElement.classList.add('current');
    }
  }
})
