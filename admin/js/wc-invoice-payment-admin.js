document.addEventListener('DOMContentLoaded', function () {
    console.log('página carregada!');
}, false);


function lkn_wcip_add_charge_row() {
    let priceLines = document.getElementsByClassName('price-row-wrap');
    let lineQtd = priceLines.length;

    // Get the element where the inputs will be added to
    let container = document.getElementById('wcip-invoice-price-row');
    let inputRow = document.createElement('div');
    inputRow.classList.add('price-row-wrap');

    // Append a node with a random text
    container.appendChild(inputRow);
    
    inputRow.innerHTML = 
    '    <div class="input-row-wrap">' +
    '        <button type="button" class="btn btn-delete" onclick="lkn_wcip_remove_charge_row(' + lineQtd + ')"><span class="dashicons dashicons-trash"></span></button>' +
    '    </div>' +
    '    <div class="input-row-wrap">' +
    '        <label>Name</label>' +
    '        <input name="lkn_wcip_name_invoice_' + lineQtd + '" type="text" id="lkn_wcip_name_invoice_' + lineQtd + '"  class="regular-text">' +
    '    </div>' +
    '    <div class="input-row-wrap">' +
    '        <label>Charge</label>' +
    '        <input name="lkn_wcip_charge_invoice_' + lineQtd + '" type="tel" id="lkn_wcip_charge_invoice_' + lineQtd + '" class="regular-text">' +
    '    </div>';
}

function lkn_wcip_remove_charge_row(id) {
    console.log('delete line');
    let inputRow = document.getElementsByClassName('price-row-wrap')[id];
    inputRow.remove();
}