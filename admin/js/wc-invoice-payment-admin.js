// Implements script internationalization
const { __, _x, _n, sprintf } = wp.i18n

/**
 * Adds a new line in the charges options box
 *
 * @return void
 */
function lkn_wcip_add_amount_row () {
  const priceLines = document.getElementsByClassName('price-row-wrap')
  let lineQtd = priceLines.length
  const rowExists = document.getElementsByClassName('price-row-' + lineQtd)[0]

  if (rowExists) {
    lineQtd++
  }

  // Get the element where the inputs will be added to
  const container = document.getElementById('wcip-invoice-price-row')
  const inputRow = document.createElement('div')
  inputRow.classList.add('price-row-wrap')
  inputRow.classList.add('price-row-' + lineQtd)

  // Append a node with a random text
  container.appendChild(inputRow)

  inputRow.innerHTML =
        '    <div class="input-row-wrap">' +
        '        <label>' + __('Name', 'wc-invoice-payment') + '</label>' +
        '        <input name="lkn_wcip_name_invoice_' + lineQtd + '" type="text" id="lkn_wcip_name_invoice_' + lineQtd + '"  class="regular-text" required>' +
        '    </div>' +
        '    <div class="input-row-wrap">' +
        '        <label>' + __('Amount', 'wc-invoice-payment') + '</label>' +
        '        <input name="lkn_wcip_amount_invoice_' + lineQtd + '" type="tel" id="lkn_wcip_amount_invoice_' + lineQtd + '" class="regular-text lkn_wcip_amount_input" oninput="lkn_wcip_filter_amount_input(this.value, ' + lineQtd + ')" required>' +
        '    </div>' +
        '    <div class="input-row-wrap">' +
        '        <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_amount_row(' + lineQtd + ')"><span class="dashicons dashicons-trash"></span></button>' +
        '    </div>'
}

/**
 * Remove line in the charges options box
 *
 * @param {String} id
 *
 * @return void
 */
function lkn_wcip_remove_amount_row (id) {
  const priceLines = document.getElementsByClassName('price-row-wrap')
  const lineQtd = priceLines.length
  if (lineQtd > 1) {
    const inputRow = document.getElementsByClassName('price-row-' + id)[0]
    inputRow.remove()
  }
}

/**
 * Filter the input in the amount input to allow only numbers and comma and dot
 *
 * @param {String} val
 * @param {String} row
 *
 * @return void
 */
function lkn_wcip_filter_amount_input (val, row) {
  const filteredVal = val.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1')
  const inputAmount = document.getElementById('lkn_wcip_amount_invoice_' + row)
  inputAmount.value = filteredVal
}

/**
 * Notifies before the deletion of a invoice
 *
 * @return void
 */
function lkn_wcip_delete_invoice () {
  if (confirm(__('Are you sure you want to delete the invoice?', 'wc-invoice-payment')) === true) {
    window.location.href += '&lkn_wcip_delete=true'
  }
}

function lkn_get_wp_base_url () {
  const href = window.location.href
  const index = href.indexOf('/wp-admin')
  const homeUrl = href.substring(0, index)
  return homeUrl
}

function lkn_wcip_generate_invoice_pdf (invoiceId) {
  fetch(`${lkn_get_wp_base_url()}/wp-json/wc-invoice-payment/v1/generate-pdf?invoice_id=${invoiceId}`, {
    method: 'GET',
    headers: {
      'content-type': 'application/json',
      'X-WP-Nonce': document.getElementById('wcip_rest_nonce').value
    }
  })
    .then(res => {
      if (!res.ok) {
        throw new Error()
      }

      return res.blob()
    })
    .then(blob => {
      const url = window.URL.createObjectURL(new Blob([blob]))
      const link = document.createElement('a')

      link.href = url
      link.setAttribute('download', __('Invoice', 'wc-invoice-payment') + '-' + invoiceId + '.pdf')
      document.body.appendChild(link)

      link.click()

      link.parentNode.removeChild(link)
    })
    .catch(error => {
      window.alert(__('Unable to generate the PDF. Please, contact support.', 'wc-invoice-payment'))
      console.error(error)
    })
}

/**
   *
   * @param {HTMLSelectElement} selectTpl
   * @param {HTMLImageElement} imgPreview
   */
function handlePreviewPdfTemplate (selectTpl, imgPreview) {
  const optionSelectedTemplate = selectTpl.options[selectTpl.selectedIndex]
  imgPreview.src = optionSelectedTemplate.dataset.previewUrl

  selectTpl.addEventListener('change', event => {
    const optionSelectedTemplate = selectTpl.options[selectTpl.selectedIndex]

    if (!optionSelectedTemplate.dataset.previewUrl) {
      imgPreview.style.display = 'none'

      return
    }

    imgPreview.src = optionSelectedTemplate.dataset.previewUrl
  })

  selectTpl.addEventListener('mouseover', event => {
    const optionSelectedTemplate = selectTpl.options[selectTpl.selectedIndex]

    if (!optionSelectedTemplate.dataset.previewUrl) {
      return
    }

    imgPreview.style.display = 'flex'
  })

  selectTpl.parentElement.parentElement.addEventListener('mouseleave', event => {
    imgPreview.style.display = 'none'
  })
}

document.addEventListener('DOMContentLoaded', () => {
  const btnGenerateInvoicePdf = document.querySelectorAll('.lkn_wcip_generate_pdf_btn') ?? []

  btnGenerateInvoicePdf.forEach(btn => {
    btn.addEventListener('click', () => lkn_wcip_generate_invoice_pdf(btn.dataset.invoiceId))
  })

  const selectGlobalTemplate = document.getElementById('lkn_wcip_payment_global_template')

  if (selectGlobalTemplate) {
    handlePreviewPdfTemplate(selectGlobalTemplate, document.getElementById('lkn-wcip-preview-img'))
  }

  const selectInvoiceTemplate = document.getElementById('lkn_wcip_select_invoice_template')

  if (selectInvoiceTemplate) {
    handlePreviewPdfTemplate(selectInvoiceTemplate, document.getElementById('lkn-wcip-preview-img'))
  }
})

function startTinyMce (elementId, btnSubmitId) {
  wp.editor.initialize(elementId, {
    tinymce: {
      toolbar1: 'bold italic underline forecolor backcolor',
      content_style: 'body { font-family: Arial, sans-serif; }',
      style_formats: [{
        title: 'Underline',
        inline: 'u'
      }]
    },
    quicktags: true,
    innerHeight
  })

  const btnSubmit = document.getElementById(btnSubmitId)
  const footerNotesTextarea = document.getElementById(elementId)

  btnSubmit.addEventListener('click', () => {
    footerNotesTextarea.innerHTML = wp.editor.getContent(elementId)
  })
}
