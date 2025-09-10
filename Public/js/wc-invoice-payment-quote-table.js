document.addEventListener('DOMContentLoaded', function () {

  textsElements = document.querySelector('.wp-block-woocommerce-order-confirmation-status.wc-block-order-confirmation-status.alignwide.has-font-size.has-large-font-size')
  if(textsElements && textsElements.querySelector('h1') && textsElements.querySelector('p')){
    textsElements.querySelector('h1').innerHTML = 'Orçamento recebido.'
    textsElements.querySelector('p').innerHTML = 'Obrigado. Seu orçamento foi recebido.'
  }
  
  // Verifica se existe um status de cotação válido
  if (wcInvoicePaymentQuoteTableVariables && wcInvoicePaymentQuoteTableVariables.quoteStatus) {
    modifyQuoteTable();
    modifyWooCommerceOrderTable();
    
    // Verifica se o parâmetro displayQuoteNotice existe na URL
    const urlParams = new URLSearchParams(window.location.search);
    const displayQuoteNotice = urlParams.get('displayQuoteNotice');
    
    if(wcInvoicePaymentQuoteTableVariables.quoteStatus == 'quote-approved' && displayQuoteNotice === 'true'){
      //Adicionar alerta de sucesso do woocomerce
      const successNotice = document.createElement('div');
      successNotice.className = 'wc-block-components-notice-banner is-success';
      successNotice.style.setProperty('margin', '0px', 'important');
      successNotice.setAttribute('role', 'alert');
      successNotice.setAttribute('tabindex', '-1');
      successNotice.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
          <path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"></path>
        </svg>
        <div class="wc-block-components-notice-banner__content">
          Orçamento aprovado com sucesso!
        </div>
      `;
      
      // Insere o aviso no início do body ou em um container apropriado
      const targetContainer = document.querySelector('.woocommerce') || document.querySelector('main') || document.body;
      if (targetContainer) {
        targetContainer.insertBefore(successNotice, targetContainer.firstChild);
      }
    }
  }

  // Função para modificar a estrutura da tabela baseada no status da cotação
  function modifyQuoteTable() {
    const table = document.querySelector('.wc-block-order-confirmation-totals__table');
    if (!table) return;

    const tfoot = table.querySelector('tfoot');
    if (!tfoot) return;

    // Verifica se a linha de "Ações" já existe para evitar duplicação
    const existingActionsRow = Array.from(tfoot.querySelectorAll('tr')).find(row => {
      const label = row.querySelector('.wc-block-order-confirmation-totals__label');
      return label && label.textContent.trim() === 'Ações:';
    });

    quoteDetailsAfterCheckoutElement = document.querySelector('.wp-block-heading')
    quoteDetailsKeyElement = document.querySelector('.wc-block-order-confirmation-summary-list-item__key')
    if(quoteDetailsAfterCheckoutElement){
      quoteDetailsAfterCheckoutElement.innerHTML = wcInvoicePaymentQuoteTableVariables.quoteDetailsText || 'Quote Details'
      quoteDetailsKeyElement.innerHTML = `Orçamento #:`;
    }

    if (existingActionsRow) {
      // Remove a linha existente para recriá-la com os botões corretos
      existingActionsRow.remove();
    }

    // Gera os botões baseado no status da cotação
    const actionButtons = generateActionButtons(wcInvoicePaymentQuoteTableVariables.quoteStatus);
    
    if (actionButtons) {
      // Cria a nova linha de "Ações"
      const actionsRow = document.createElement('tr');
      actionsRow.innerHTML = `
        <th class="wc-block-order-confirmation-totals__label" scope="row">Ações:</th>
        <td class="wc-block-order-confirmation-totals__total">
          ${actionButtons}
        </td>
      `;

      // Insere a linha de "Ações" após a linha "Total:" (na última posição)
      tfoot.appendChild(actionsRow);
    }
  }

  // Função para gerar os botões de ação baseado no status
  function generateActionButtons(quoteStatus) {
    const variables = wcInvoicePaymentQuoteTableVariables;
    let buttons = '';

    switch (quoteStatus) {
      case 'quote-approved':
        // Status: Aprovado - Botões Pagar e Cancelar
        if (variables.paymentPaymentUrl) {
          buttons += `<a href="${variables.paymentPaymentUrl}" 
                         class="woocommerce-button wp-element-button button pay order-actions-button" 
                         aria-label="Pagar pedido" style="margin-right: 10px;">Pagar</a>`;
        }
        if (variables.cancelUrl) {
          buttons += `<a href="${variables.cancelUrl}" 
                         class="woocommerce-button wp-element-button button cancel order-actions-button" 
                         aria-label="Cancelar pedido" 
                         onclick="return confirm(wcInvoicePaymentQuoteTableVariables.confirmCancel || 'Are you sure you want to cancel this quote?')">
                         ${wcInvoicePaymentQuoteTableVariables.cancelText || 'Cancel'}</a>`;
        }
        break;

      case 'quote-awaiting':
        // Status: Pendente - Botões Aceitar e Cancelar
        if (variables.approvalQuoteUrl) {
          buttons += `<a href="${variables.approvalQuoteUrl}" 
                         class="woocommerce-button wp-element-button button accept order-actions-button" 
                         aria-label="Aceitar cotação" style="margin-right: 10px;" 
                         onclick="return confirm(wcInvoicePaymentQuoteTableVariables.confirmApprove || 'Are you sure you want to approve this quote?')">
                         ${wcInvoicePaymentQuoteTableVariables.approveText || 'Approve'}</a>`;
        }
        if (variables.cancelUrl) {
          buttons += `<a href="${variables.cancelUrl}" 
                         class="woocommerce-button wp-element-button button cancel order-actions-button" 
                         aria-label="Cancelar pedido" 
                         onclick="return confirm(wcInvoicePaymentQuoteTableVariables.confirmCancel || 'Are you sure you want to cancel this quote?')">
                         ${wcInvoicePaymentQuoteTableVariables.cancelText || 'Cancel'}</a>`;
        }
        break;
    }

    return buttons;
  }

  // Função para modificar a tabela de pedidos do WooCommerce (woocommerce-table)
  function modifyWooCommerceOrderTable() {
    const table = document.querySelector('.woocommerce-table.woocommerce-table--order-details.shop_table.order_details');
    if (!table) return;
    document.querySelector('.woocommerce-MyAccount-content p')?.remove()
    quoteDetailsElement = document.querySelector('.woocommerce-order-details__title')
    quoteTitleElement = document.querySelector('.wp-block-post-title')
    if(quoteDetailsElement){
      quoteDetailsElement.innerHTML = 'Detalhes do orç2amento'
    }
    
    if(quoteTitleElement){
      quoteTitleElement.innerHTML = `Orçamento #${wcInvoicePaymentQuoteTableVariables.quoteOrderId}`
    }

    // Procura pelos elementos tfoot da tabela
    const tfootElements = table.querySelectorAll('tfoot');
    if (!tfootElements.length) return;

    // Remove qualquer linha de ações existente de todos os tfoot
    tfootElements.forEach(tfoot => {
      const existingActionsRow = Array.from(tfoot.querySelectorAll('tr')).find(row => {
        const heading = row.querySelector('.order-actions--heading');
        return heading && heading.textContent.trim() === 'Ações:';
      });
      
      if (existingActionsRow) {
        existingActionsRow.remove();
      }
    });

    // Gera os botões baseado no status da cotação
    const actionButtons = generateActionButtons(wcInvoicePaymentQuoteTableVariables.quoteStatus);
    
    if (actionButtons) {
      // Cria a nova linha de "Ações"
      const actionsRow = document.createElement('tr');
      actionsRow.innerHTML = `
        <th class="order-actions--heading">Ações:</th>
        <td>
          ${actionButtons}
        </td>
      `;

      // Adiciona a linha de ações no primeiro tfoot encontrado
      tfootElements[0].insertBefore(actionsRow, tfootElements[0].firstChild);
    }
  }
})