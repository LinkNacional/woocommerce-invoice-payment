// eslint-disable-next-line no-undef
jQuery(document).ready(function($) {
  const $topLevelMenu = $('#toplevel_page_wc-invoice-payment')
  $topLevelMenu.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment menu-top-last')

  const $topLevelLink = $topLevelMenu.find('a').first()
  $topLevelLink.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment menu-top-last')
  $topLevelLink.attr('aria-haspopup', 'false')

  const $subMenu = $('#toplevel_page_wc-invoice-payment').find('.wp-submenu')
  $subMenu.css('min-width', 'auto')
  $subMenu.css('border-left', '0')
})
