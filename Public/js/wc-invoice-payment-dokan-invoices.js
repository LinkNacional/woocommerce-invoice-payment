/**
 * JavaScript para página de faturas do Dokan
 * Baseado no wc-invoice-payment-admin.js
 */

function lkn_get_wp_base_url() {
  const href = window.location.href
  const index = href.indexOf('/wp-admin')
  if (index !== -1) {
    return href.substring(0, index)
  }
  
  // Para URLs do frontend do Dokan
  const homeUrl = window.location.origin
  return homeUrl
}

/**
 * Gera e baixa o PDF da fatura
 *
 * @param {number} invoiceId ID da fatura
 * @param {number} key Índice do botão
 */
function lkn_wcip_generate_invoice_pdf(invoiceId, key) {
  const loadingIcon = document.querySelector('.dashicons-image-rotate')
  const downloadButtons = document.querySelectorAll('.lkn_wcip_generate_pdf_btn')
  
  downloadButtons.forEach((downloadButton, i) => {
    if (i == key) {
      if (!downloadButton?.disabled) {
        if (downloadButton) {
          downloadButton.innerHTML = lknWcipDokanVars.downloading || 'Baixando...'
          downloadButton.disabled = true
        }

        if (loadingIcon) {
          loadingIcon.style.display = 'block'
        }

        // Usar a API REST existente do plugin
        fetch(`${lkn_get_wp_base_url()}/wp-json/wc-invoice-payment/v1/generate-pdf?invoice_id=${invoiceId}`, {
          method: 'GET',
          headers: {
            'content-type': 'application/json',
            'X-WP-Nonce': lknWcipDokanVars.nonce
          },
          cache: 'no-store'
        })
          .then(res => {
            if (!res.ok) {
              throw new Error(`HTTP error! status: ${res.status}`)
            }
            return res.blob()
          })
          .then(blob => {
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = url
            link.setAttribute('download', `${lknWcipDokanVars.invoice}-${invoiceId}.pdf`)
            document.body.appendChild(link)

            link.click()

            link.parentNode.removeChild(link)
            window.URL.revokeObjectURL(url)

            if (loadingIcon) {
              loadingIcon.style.display = 'none'
            }

            if (downloadButton) {
              downloadButton.innerHTML = `<i class="fas fa-download"></i>`
              downloadButton.disabled = false
            }
          })
          .catch(error => {
            console.error('Erro ao baixar PDF:', error)
            window.alert(lknWcipDokanVars.pdfError || 'Erro ao gerar PDF da fatura')
            
            if (loadingIcon) {
              loadingIcon.style.display = 'none'
            }

            if (downloadButton) {
              downloadButton.innerHTML = `<i class="fas fa-download"></i>`
              downloadButton.disabled = false
            }
          })
      }
    }
  })
}

/**
 * Inicialização quando o DOM estiver carregado
 */
document.addEventListener('DOMContentLoaded', function() {
  // Funcionalidade de select all (igual ao Dokan)
  const selectAllCheckbox = document.getElementById('cb-select-all')
  const itemCheckboxes = document.querySelectorAll('.cb-select-items')

  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
      itemCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked
      })
    })
  }

  // Atualizar select all quando checkboxes individuais mudam
  itemCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      const total = itemCheckboxes.length
      const checked = document.querySelectorAll('.cb-select-items:checked').length
      
      if (selectAllCheckbox) {
        selectAllCheckbox.indeterminate = checked > 0 && checked < total
        selectAllCheckbox.checked = checked === total
      }
    })
  })

  // Inicializar botões de download de PDF
  const downloadButtons = document.querySelectorAll('.lkn_wcip_generate_pdf_btn')
  downloadButtons.forEach((btn, i) => {
    btn.addEventListener('click', function(e) {
      e.preventDefault()
      const invoiceId = this.dataset.invoiceId
      if (invoiceId) {
        lkn_wcip_generate_invoice_pdf(invoiceId, i)
      }
    })
  })

  // Tooltips do Dokan
  if (typeof jQuery !== 'undefined') {
    jQuery('.tips').tooltip()
  }
})

/**
 * Funções para adicionar/remover linhas de preço (para página de nova fatura)
 */
window.lkn_wcip_row_counter = 0

window.lkn_wcip_add_amount_row = function() {
  lkn_wcip_row_counter++
  const container = document.getElementById('wcip-invoice-price-row')
  
  if (!container) return
  
  const row = document.createElement('div')
  row.className = `price-row-wrap price-row-${lkn_wcip_row_counter}`
  
  row.innerHTML = `
    <div class="input-row-wrap">
      <label>${lknWcipDokanVars.itemName || 'Nome'}</label>
      <input name="lkn_wcip_name_invoice_${lkn_wcip_row_counter}" type="text" class="regular-text" required>
    </div>
    <div class="input-row-wrap">
      <label>${lknWcipDokanVars.itemAmount || 'Valor'}</label>
      <input name="lkn_wcip_amount_invoice_${lkn_wcip_row_counter}" type="tel" class="regular-text lkn_wcip_amount_input" oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');" required>
    </div>
    <div class="input-row-wrap">
      <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(${lkn_wcip_row_counter})">
        <span class="dashicons dashicons-trash"></span>
      </button>
    </div>
  `
  
  container.appendChild(row)
}

window.lkn_wcip_remove_amount_row = function(row_id) {
  const priceLines = document.getElementsByClassName('price-row-wrap')
  if (priceLines.length > 1) {
    const rowToRemove = document.querySelector(`.price-row-${row_id}`)
    if (rowToRemove) {
      rowToRemove.remove()
    }
  }
}

/**
 * Filtrar input de valor para permitir apenas números, vírgula e ponto
 *
 * @param {string} val Valor do input
 * @param {number} row Número da linha
 */
function lkn_wcip_filter_amount_input(val, row) {
  const filteredVal = val.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1')
  const inputAmount = document.getElementById(`lkn_wcip_amount_invoice_${row}`)
  if (inputAmount) {
    inputAmount.value = filteredVal
  }
}
