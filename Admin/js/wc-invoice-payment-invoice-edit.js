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
  if (!confirm(wcip_i18n.confirmApproveQuote || 'Are you sure you want to approve this quote?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button.value;
  button.value = wcip_i18n.approving || 'Approving...';
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
        lkn_wcip_send_quote_email(quoteId, true, function() {
          location.reload();
        });
      } else {
        alert(wcip_i18n.error + ': ' + response.data);
        button.value = originalText;
        button.disabled = false;
      }
    },
    error: function() {
      alert(wcip_i18n.serverError || 'Server communication error');
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
  if (!confirm(wcip_i18n.confirmCreateInvoice || 'Are you sure you want to create an invoice for this quote?')) {
    return;
  }

  // Find the button that was clicked and update its state
  const button = document.querySelector(`input[onclick="lkn_wcip_create_invoice(${quoteId})"]`);
  if (button) {
    button.disabled = true;
    button.value = wcip_i18n.creatingInvoice || 'Creating Invoice...';
  }

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
        alert(wcip_i18n.error + ': ' + response.data);
        if (button) {
          button.disabled = false;
          button.value = wcip_i18n.createInvoice || 'Create Invoice';
        }
      }
    },
    error: function() {
      alert(wcip_i18n.serverError || 'Server communication error');
      if (button) {
        button.disabled = false;
        button.value = wcip_i18n.createInvoice || 'Create Invoice';
      }
    }
  });
}

/**
 * Function to send quote email to customer via AJAX
 * @param {number} quoteId - The ID of the quote to send email for
 */
function lkn_wcip_send_quote_email(quoteId, skipConfirm = false, callback = null) {
  if (!skipConfirm && !confirm(wcip_i18n.confirmSendQuoteEmail || 'Are you sure you want to send the quote to the customer\'s email?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button ? button.value : '';
  if (button) {
    button.value = wcip_i18n.sending || 'Sending...';
    button.disabled = true;
  }

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
        if (button) {
          button.value = originalText;
          button.disabled = false;
        }
        // Execute callback if provided
        if (callback && typeof callback === 'function') {
          callback();
        }
      } else {
        alert(wcip_i18n.error + ': ' + response.data);
        if (button) {
          button.value = originalText;
          button.disabled = false;
        }
      }
    },
    error: function() {
      alert(wcip_i18n.serverError || 'Server communication error');
      if (button) {
        button.value = originalText;
        button.disabled = false;
      }
    }
  });
}

/**
 * Function to approve quote without sending email via AJAX
 * @param {number} quoteId - The ID of the quote to approve
 */
function lkn_wcip_approve_quote_only(quoteId) {
  if (!confirm(wcip_i18n.confirmApproveQuoteOnly || 'Are you sure you want to approve this quote?')) {
    return;
  }

  // Show loading state
  const button = event.target;
  const originalText = button.value;
  button.value = wcip_i18n.approving || 'Approving...';
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
        alert(wcip_i18n.error + ': ' + response.data);
        button.value = originalText;
        button.disabled = false;
      }
    },
    error: function() {
      alert(wcip_i18n.serverError || 'Server communication error');
      button.value = originalText;
      button.disabled = false;
    }
  });
}