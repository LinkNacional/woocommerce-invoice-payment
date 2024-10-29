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
    '        <label>' + phpattributes.name + '</label>' +
    '        <input name="lkn_wcip_name_invoice_' + lineQtd + '" type="text" id="lkn_wcip_name_invoice_' + lineQtd + '"  class="regular-text" required>' +
    '    </div>' +
    '    <div class="input-row-wrap">' +
    '        <label>' + phpattributes.amount + '</label>' +
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
  if (confirm(phpattributes.deleteConfirm) === true) {
    lkn_wcip_cancel_subscription(true)
    window.location.href += '&lkn_wcip_delete=true'
  }
}

function lkn_get_wp_base_url () {
  const href = window.location.href
  const index = href.indexOf('/wp-admin')
  const homeUrl = href.substring(0, index)
  return homeUrl
}

function lkn_wcip_generate_invoice_pdf (invoiceId, key) {
  const loadingIcon = document.querySelector('.dashicons-image-rotate')
  const downloadButtons = document.querySelectorAll('.lkn_wcip_generate_pdf_btn')
  downloadButtons.forEach((downloadButton, i) => {
    if (i == key) {
      if (!downloadButton?.disabled) {
        if (downloadButton) {
          downloadButton.innerHTML = phpattributes.downloading
          downloadButton.disabled = true
        }

        if (loadingIcon) {
          loadingIcon.style.display = 'block'
        }
        fetch(`${lkn_get_wp_base_url()}/wp-json/wc-invoice-payment/v1/generate-pdf?invoice_id=${invoiceId}`, {
          method: 'GET',
          headers: {
            'content-type': 'application/json',
            'X-WP-Nonce': document.getElementById('wcip_rest_nonce').value
          },
          cache: 'no-store'
        })
          .then(res => {
            if (!res.ok) {
              throw new Error()
            }

            return res.blob() // Alterado para res.blob() para obter a resposta como Blob
          })
          .then(blob => {
            const url = window.URL.createObjectURL(blob)
            const link = document.createElement('a')

            link.href = url
            link.setAttribute('download', phpattributes.invoice + '-' + invoiceId + '.pdf')
            document.body.appendChild(link)

            link.click()

            link.parentNode.removeChild(link)

            if (loadingIcon) {
              loadingIcon.style.display = 'none'
            }

            if (downloadButton) {
              downloadButton.innerHTML = phpattributes.downloadInvoice
              downloadButton.disabled = false
            }
          })
          .catch(error => {
            window.alert(phpattributes.pdfError)
            console.error(error)
            if (loadingIcon) {
              loadingIcon.style.display = 'none'
            }
          })
      }
    }
  })
}

function base64toBlob (base64Data) {
  const contentType = 'application/pdf'
  const sliceSize = 512
  const byteCharacters = atob(base64Data)
  const byteArrays = []

  for (let offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    const slice = byteCharacters.slice(offset, offset + sliceSize)

    const byteNumbers = new Array(slice.length)
    for (let i = 0; i < slice.length; i++) {
      byteNumbers[i] = slice.charCodeAt(i)
    }

    const byteArray = new Uint8Array(byteNumbers)
    byteArrays.push(byteArray)
  }

  return new Blob(byteArrays, { type: contentType })
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
  btnGenerateInvoicePdf.forEach((btn, i) => {
    btn.addEventListener('click', () => { lkn_wcip_generate_invoice_pdf(btn.dataset.invoiceId, i) })
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

/**
 * TinyMCE toolbar options doc: https://www.tiny.cloud/docs/advanced/available-toolbar-buttons/
 */
function startTinyMce (elementId, btnSubmitId) {
  wp.editor.initialize(elementId, {
    tinymce: {
      toolbar1: 'bold italic underline forecolor backcolor fontsizeselect link',
      content_style: 'body { font-family: Arial, sans-serif; }',
      style_formats: [{
        title: 'Underline',
        inline: 'u'
      }],
      height: 150
    },
    quicktags: false
  })

  const btnSubmit = document.getElementById(btnSubmitId)
  const footerNotesTextarea = document.getElementById(elementId)

  btnSubmit.addEventListener('click', () => {
    footerNotesTextarea.innerHTML = wp.editor.getContent(elementId)
  })
}

function lkn_wcip_display_modal () {
  const modal = document.querySelector('#lkn-wcip-share-modal')
  modal.style.display = modal.style.display ? '' : 'none'
}

function lkn_wcip_open_popup (platform, invoiceLink) {
  const url = encodeURIComponent(invoiceLink)
  let popupUrl = ''
  const width = 600
  const height = 400
  const left = (window.innerWidth - width) / 2
  const top = (window.innerHeight - height) / 2

  switch (platform) {
    case 'whatsapp':
      popupUrl = 'https://wa.me/?text=' + url
      break
    case 'twitter':
      popupUrl = 'https://twitter.com/messages/compose?text=' + url
      break
  }

  const popupParams = 'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes'
  window.open(popupUrl, platform + 'Window', popupParams)
}

function lkn_wcip_copy_link () {
  const linkInput = document.querySelector('#lkn-wcip-copy-input')
  linkInput.select()
  document.execCommand('copy')
  navigator.clipboard.writeText(linkInput.value)
}

function lkn_wcip_cancel_subscription (deleteSubscription = false) {
  const invoiceId = document.querySelector('.lkn_wcip_cancel_subscription_btn')?.getAttribute('data-invoice-id')
  const wcipRestNonce = document.querySelector('#wcip_rest_nonce')?.value
  const data = {
    action: 'cancel_subscription',
    invoice_id: invoiceId,
    wcip_rest_nonce: wcipRestNonce
  }
  if (deleteSubscription) {
    if (invoiceId) {
      jQuery.post(ajaxurl, data, function (response) {

      })
    }
  } else if (confirm(phpattributes.cancelConfirm)) {
    jQuery.post(ajaxurl, data, function (response) {
      window.location.reload()
    })
  }
}

// Função para adicionar e remover display none dos campos dependendo se a fatura é uma assinatura
function lkn_wcip_display_subscription_inputs () {
  const subscription = document.querySelector('#lkn_wcip_subscription_product')
  const intervalElementSubscription = document.querySelector('#lkn_wcip_subscription_interval')
  const limitCheckboxElementSubscription = document.querySelector('#lkn_wcip_subscription_limit_checkbox_div')
  const subscriptionLimit = document.querySelector('#lkn_wcip_subscription_limit_checkbox')
  const intervalElementSubscriptionLimit = document.querySelector('.lkn_wcip_subscription_limit_field')

  intervalElementSubscription.style.display = 'none'
  intervalElementSubscriptionLimit.style.display = 'none'
  limitCheckboxElementSubscription.style.display = 'none'

  const queryString = window.location.search
  const urlParams = new URLSearchParams(queryString)
  const invoiceChecked = urlParams.get('invoiceChecked')
  if (invoiceChecked) {
    intervalElementSubscription.style.display = ''
    limitCheckboxElementSubscription.style.display = ''
  }

  subscription.addEventListener('change', function () {
    if (subscription.checked) {
      intervalElementSubscription.style.display = ''
      limitCheckboxElementSubscription.style.display = ''
    } else {
      intervalElementSubscription.style.display = 'none'
      limitCheckboxElementSubscription.style.display = 'none'
    }
  })

  subscriptionLimit.addEventListener('change', function () {
    if (subscriptionLimit.checked) {
      intervalElementSubscriptionLimit.style.display = ''
    } else {
      intervalElementSubscriptionLimit.style.display = 'none'
    }
  })
}
jQuery(document).ready(function ($) {
  $('.woocommerce-help-tip').on('mouseover', function () {
    $(this).attr('title', $(this).attr('aria-label'))
  })
})
