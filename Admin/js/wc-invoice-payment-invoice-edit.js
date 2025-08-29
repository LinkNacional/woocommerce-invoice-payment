// eslint-disable-next-line no-undef
jQuery(document).ready(function ($) {
  if (window.location.search.includes("page=edit-quote")) {
    const $quotesTopLevelMenu = $('#toplevel_page_wc-invoice-payment-quotes')
    $quotesTopLevelMenu.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment-quotes')

    const $quotesTopLevelLink = $quotesTopLevelMenu.find('a').first()
    $quotesTopLevelLink.addClass('wp-has-submenu wp-has-current-submenu wp-menu-open menu-top toplevel_page_wc-invoice-payment-quotes')
    $quotesTopLevelLink.attr('aria-haspopup', 'false')

    const $quotesSubMenu = $('#toplevel_page_wc-invoice-payment-quotes').find('.wp-submenu')
    $quotesSubMenu.css('min-width', 'auto')
    $quotesSubMenu.css('border-left', '0')

    const quotesElementBar = document.querySelector('.wp-has-submenu.wp-not-current-submenu.menu-top.toplevel_page_wc-invoice-payment-quotes.wp-has-current-submenu.wp-menu-open')
    if(quotesElementBar) {
      const firstQuoteItem = quotesElementBar.querySelector('.wp-submenu .wp-first-item a');
      if (firstQuoteItem) {
        firstQuoteItem.classList.add('current');
        firstQuoteItem.parentElement.classList.add('current');
      }
    }
  }else{

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
  }
})

/**
 * Function to approve quote via AJAX
 * @param {number} quoteId - The ID of the quote to approve
 */
function lkn_wcip_approve_quote(quoteId) {
  if (!confirm('Tem certeza que deseja aprovar este orçamento?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button.value;
  button.value = 'Aprovando...';
  button.disabled = true;

  // Get nonce from the page
  const nonce = document.getElementById('wcip_rest_nonce').value;

  // Make AJAX request
  jQuery.ajax({
    url: wcip_ajax.ajax_url,
    type: 'POST',
    data: {
      action: 'lkn_wcip_approve_quote',
      quote_id: quoteId,
      security: nonce
    },
    success: function(response) {
      if (response.success) {
        location.reload();
      } else {
        alert('Erro: ' + response.data);
        button.value = originalText;
        button.disabled = false;
      }
    },
    error: function() {
      alert('Erro na comunicação com o servidor');
      button.value = originalText;
      button.disabled = false;
    }
  });
}

/**
 * Function to create invoice from approved quote via AJAX
 * @param {number} quoteId - The ID of the quote to create invoice from
 */
function lkn_wcip_create_invoice(quoteId) {
  if (!confirm('Tem certeza que deseja gerar uma fatura para este orçamento?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button.value;
  button.value = 'Gerando Fatura...';
  button.disabled = true;

  // Get nonce from the page
  const nonce = document.getElementById('wcip_rest_nonce').value;

  // Make AJAX request
  jQuery.ajax({
    url: wcip_ajax.ajax_url,
    type: 'POST',
    data: {
      action: 'lkn_wcip_create_invoice',
      quote_id: quoteId,
      security: nonce
    },
    success: function(response) {
      if (response.success) {
        location.reload();
      } else {
        alert('Erro: ' + response.data);
        button.value = originalText;
        button.disabled = false;
      }
    },
    error: function() {
      alert('Erro na comunicação com o servidor');
      button.value = originalText;
      button.disabled = false;
    }
  });
}

/**
 * Function to send quote email to customer via AJAX
 * @param {number} quoteId - The ID of the quote to send email for
 */
function lkn_wcip_send_quote_email(quoteId) {
  if (!confirm('Tem certeza que deseja enviar o orçamento para o email do cliente?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button.value;
  button.value = 'Enviando...';
  button.disabled = true;

  // Get nonce from the page
  const nonce = document.getElementById('wcip_rest_nonce').value;

  // Make AJAX request
  jQuery.ajax({
    url: wcip_ajax.ajax_url,
    type: 'POST',
    data: {
      action: 'lkn_wcip_send_quote_email',
      quote_id: quoteId,
      security: nonce
    },
    success: function(response) {
      if (response.success) {
        // Show detailed success message
        button.value = originalText;
        button.disabled = false;
      } else {
        alert('Erro: ' + response.data);
        button.value = originalText;
        button.disabled = false;
      }
    },
    error: function() {
      alert('Erro na comunicação com o servidor');
      button.value = originalText;
      button.disabled = false;
    }
  });
}