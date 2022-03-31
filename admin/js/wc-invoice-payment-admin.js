// Implements script internationalization
const { __, _x, _n, sprintf } = wp.i18n;

/**
 * Adds a new line in the charges options box
 * 
 * @return void
 */
function lkn_wcip_add_amount_row() {
    let priceLines = document.getElementsByClassName('price-row-wrap');
    let lineQtd = priceLines.length;
    let rowExists = document.getElementsByClassName('price-row-' + lineQtd)[0];

    if(rowExists) {
        lineQtd++;
    }

    // Get the element where the inputs will be added to
    let container = document.getElementById('wcip-invoice-price-row');
    let inputRow = document.createElement('div');
    inputRow.classList.add('price-row-wrap');
    inputRow.classList.add('price-row-' + lineQtd);

    // Append a node with a random text
    container.appendChild(inputRow);
    
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
    '    </div>';
}

/**
 * Remove line in the charges options box
 * 
 * @param {String} id 
 * 
 * @return void
 */
function lkn_wcip_remove_amount_row(id) {
    let priceLines = document.getElementsByClassName('price-row-wrap');
    let lineQtd = priceLines.length;
    if(lineQtd > 1) {
        let inputRow = document.getElementsByClassName('price-row-' + id)[0];
        inputRow.remove();
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
function lkn_wcip_filter_amount_input(val, row) {
    let filteredVal = val.replace(/[^0-9.,]/g, '').replace(/(\..*?)\..*/g, '$1');
    let inputAmount = document.getElementById('lkn_wcip_amount_invoice_' + row);
    inputAmount.value = filteredVal;
}

/**
 * Notifies before the deletion of a invoice
 * 
 * @return void
 */
function lkn_wcip_delete_invoice () {
    if(confirm(__('Are you sure you want to delete the invoice?','wc-invoice-payment')) === true) {
        window.location.href += '&lkn_wcip_delete=true';
    }
}