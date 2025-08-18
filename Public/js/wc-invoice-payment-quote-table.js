document.addEventListener('DOMContentLoaded', function () {
  // Função para modificar a tabela de cotação
  console.log(wcInvoicePaymentQuoteTableVariables)

  textsElements = document.querySelector('.wp-block-woocommerce-order-confirmation-status.wc-block-order-confirmation-status.alignwide.has-font-size.has-large-font-size')
  if(textsElements && textsElements.querySelector('h1') && textsElements.querySelector('p')){
    textsElements.querySelector('h1').innerHTML = 'Orçamento recebido.'
    textsElements.querySelector('p').innerHTML = 'Obrigado. Seu orçamento foi recebido.'
  }
  
  // Verifica se existe um status de cotação válido
  if (wcInvoicePaymentQuoteTableVariables && wcInvoicePaymentQuoteTableVariables.quoteStatus) {
    modifyQuoteTable();
    modifyWooCommerceOrderTable();
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
                         aria-label="Cancelar pedido">Cancelar</a>`;
        }
        break;

      case 'quote-awaiting':
        // Status: Pendente - Botões Aceitar e Cancelar
        if (variables.approvalQuoteUrl) {
          buttons += `<a href="${variables.approvalQuoteUrl}" 
                         class="woocommerce-button wp-element-button button accept order-actions-button" 
                         aria-label="Aceitar cotação" style="margin-right: 10px;">Aprovar</a>`;
        }
        if (variables.cancelUrl) {
          buttons += `<a href="${variables.cancelUrl}" 
                         class="woocommerce-button wp-element-button button cancel order-actions-button" 
                         aria-label="Cancelar pedido">Cancelar</a>`;
        }
        break;
    }

    return buttons;
  }

  // Função para modificar a tabela de pedidos do WooCommerce (woocommerce-table)
  function modifyWooCommerceOrderTable() {
    const table = document.querySelector('.woocommerce-table.woocommerce-table--order-details.shop_table.order_details');
    if (!table) return;

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