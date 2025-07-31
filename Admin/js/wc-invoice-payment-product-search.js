/**
 * WooCommerce Product Search Modal for Invoice Payment Plugin
 * Integrates WooCommerce's native product search functionality
 */

jQuery(document).ready(function($) {
    'use strict';

    let productSearchModal = null;
    let searchResults = [];
    let selectedProducts = [];

    /**
     * Initialize the product search modal
     */
    function initProductSearchModal() {
        if (productSearchModal) {
            return;
        }

        // Create modal HTML
        const modalHTML = `
            <div id="lkn-wcip-product-search-modal" class="lkn-wcip-modal" style="display: none;">
                <div class="lkn-wcip-modal-content">
                    <div class="lkn-wcip-modal-header">
                        <h2>${wcipProductSearch.addProducts}</h2>
                        <span class="lkn-wcip-modal-close">&times;</span>
                    </div>
                    <div class="lkn-wcip-modal-body">
                        <div class="lkn-wcip-product-search-container">
                            <label for="lkn-wcip-product-search">${wcipProductSearch.searchProducts}</label>
                            <select id="lkn-wcip-product-search" class="wc-product-search" 
                                    style="width: 100%;" multiple="multiple" 
                                    data-placeholder="${wcipProductSearch.searchProductsPlaceholder}">
                            </select>
                        </div>
                        <div id="lkn-wcip-selected-products" class="lkn-wcip-selected-products">
                            <h3>${wcipProductSearch.selectedProducts}</h3>
                            <div id="lkn-wcip-products-list"></div>
                        </div>
                    </div>
                    <div class="lkn-wcip-modal-footer">
                        <button type="button" class="button" id="lkn-wcip-cancel-products">${wcipProductSearch.cancel}</button>
                        <button type="button" class="button-primary" id="lkn-wcip-add-products">${wcipProductSearch.addToInvoice}</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);
        productSearchModal = $('#lkn-wcip-product-search-modal');

        initializeProductSearch();
        bindModalEvents();
    }

    /**
     * Initialize WooCommerce product search select2
     */
    function initializeProductSearch() {
        $('#lkn-wcip-product-search').select2({
            ajax: {
                url: wcipProductSearch.ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term,
                        action: 'woocommerce_json_search_products_and_variations',
                        security: wcipProductSearch.searchNonce,
                        exclude: [],
                        include: [],
                        limit: 20
                    };
                },
                processResults: function(data) {
                    const terms = [];
                    if (data) {
                        $.each(data, function(id, text) {
                            terms.push({
                                id: id,
                                text: text
                            });
                        });
                    }
                    return {
                        results: terms
                    };
                },
                cache: true
            },
            minimumInputLength: 3,
            maximumSelectionLength: 10,
            placeholder: wcipProductSearch.searchProductsPlaceholder,
            allowClear: true
        });

        // Handle selection
        $('#lkn-wcip-product-search').on('select2:select', function(e) {
            const productData = e.params.data;
            addProductToList(productData.id, productData.text);
        });

        $('#lkn-wcip-product-search').on('select2:unselect', function(e) {
            const productData = e.params.data;
            removeProductFromList(productData.id);
        });
    }

    /**
     * Bind modal events
     */
    function bindModalEvents() {
        // Close modal
        $('.lkn-wcip-modal-close, #lkn-wcip-cancel-products').on('click', function() {
            closeModal();
        });

        // Close modal on outside click
        $(window).on('click', function(e) {
            if (e.target === productSearchModal[0]) {
                closeModal();
            }
        });

        // Add products to invoice
        $('#lkn-wcip-add-products').on('click', function() {
            addProductsToInvoice();
            closeModal();
        });

        // Remove product from selection
        $(document).on('click', '.lkn-wcip-remove-product', function() {
            const productId = $(this).data('product-id');
            removeProductFromList(productId);
            
            // Also remove from select2
            const currentValues = $('#lkn-wcip-product-search').val() || [];
            const index = currentValues.indexOf(productId.toString());
            if (index > -1) {
                currentValues.splice(index, 1);
                $('#lkn-wcip-product-search').val(currentValues).trigger('change');
            }
        });

        // Update quantity
        $(document).on('change', '.lkn-wcip-product-quantity', function() {
            const productId = $(this).data('product-id');
            const quantity = parseInt($(this).val()) || 1;
            updateProductQuantity(productId, quantity);
        });
    }

    /**
     * Add product to selected list
     */
    function addProductToList(productId, productName) {
        if (selectedProducts.find(p => p.id == productId)) {
            return; // Already added
        }

        // Get product price via AJAX
        $.ajax({
            url: wcipProductSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'lkn_wcip_get_product_data',
                product_id: productId,
                security: wcipProductSearch.productNonce
            },
            success: function(response) {
                if (response.success) {
                    const product = {
                        id: productId,
                        name: productName,
                        price: response.data.price,
                        formatted_price: response.data.formatted_price,
                        quantity: 1
                    };

                    selectedProducts.push(product);
                    renderSelectedProducts();
                } else {
                    console.error('Error fetching product data:', response.data);
                }
            },
            error: function() {
                console.error('AJAX error fetching product data');
            }
        });
    }

    /**
     * Remove product from selected list
     */
    function removeProductFromList(productId) {
        selectedProducts = selectedProducts.filter(p => p.id != productId);
        renderSelectedProducts();
    }

    /**
     * Update product quantity
     */
    function updateProductQuantity(productId, quantity) {
        const product = selectedProducts.find(p => p.id == productId);
        if (product) {
            product.quantity = quantity;
            renderSelectedProducts();
        }
    }

    /**
     * Render selected products list
     */
    function renderSelectedProducts() {
        const container = $('#lkn-wcip-products-list');
        container.empty();

        if (selectedProducts.length === 0) {
            container.html(`<p class="lkn-wcip-no-products">${wcipProductSearch.noProductsSelected}</p>`);
            return;
        }

        selectedProducts.forEach(function(product) {
            const productHTML = `
                <div class="lkn-wcip-selected-product" data-product-id="${product.id}">
                    <div class="lkn-wcip-product-info">
                        <strong>${product.name}</strong>
                        <span class="lkn-wcip-product-price">${product.formatted_price}</span>
                    </div>
                    <div class="lkn-wcip-product-controls">
                        <label>${wcipProductSearch.quantity}:</label>
                        <input type="number" class="lkn-wcip-product-quantity" 
                               data-product-id="${product.id}" 
                               value="${product.quantity}" min="1" max="9999">
                        <button type="button" class="button lkn-wcip-remove-product" 
                                data-product-id="${product.id}">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
            container.append(productHTML);
        });
    }

    /**
     * Add selected products to invoice form
     */
    function addProductsToInvoice() {
        selectedProducts.forEach(function(product) {
            const totalPrice = (parseFloat(product.price) * product.quantity).toFixed(2).replace('.', ',');
            const productName = `${product.name} (x${product.quantity})`;
            
            // Add a new line to the invoice form
            lkn_wcip_add_amount_row();
            
            // Get the latest added row
            const priceLines = document.getElementsByClassName('price-row-wrap');
            const lastLine = priceLines[priceLines.length - 1];
            const lineNumber = lastLine.className.match(/price-row-(\d+)/)[1];
            
            // Fill the fields
            const nameInput = document.getElementById(`lkn_wcip_name_invoice_${lineNumber}`);
            const amountInput = document.getElementById(`lkn_wcip_amount_invoice_${lineNumber}`);
            
            if (nameInput && amountInput) {
                nameInput.value = productName;
                amountInput.value = totalPrice;
                
                // Add hidden fields to store product information for real WooCommerce items
                const hiddenProductId = document.createElement('input');
                hiddenProductId.type = 'hidden';
                hiddenProductId.name = `lkn_wcip_product_id_${lineNumber}`;
                hiddenProductId.value = product.id;
                
                const hiddenProductQty = document.createElement('input');
                hiddenProductQty.type = 'hidden';
                hiddenProductQty.name = `lkn_wcip_product_qty_${lineNumber}`;
                hiddenProductQty.value = product.quantity;
                
                const hiddenIsRealProduct = document.createElement('input');
                hiddenIsRealProduct.type = 'hidden';
                hiddenIsRealProduct.name = `lkn_wcip_is_real_product_${lineNumber}`;
                hiddenIsRealProduct.value = '1';
                
                // Add the hidden fields to the form
                lastLine.appendChild(hiddenProductId);
                lastLine.appendChild(hiddenProductQty);
                lastLine.appendChild(hiddenIsRealProduct);
            }
        });

        // Clear selected products
        selectedProducts = [];
        renderSelectedProducts();
        $('#lkn-wcip-product-search').val(null).trigger('change');
    }

    /**
     * Open the modal
     */
    function openModal() {
        initProductSearchModal();
        productSearchModal.show();
        
        // Reset search
        $('#lkn-wcip-product-search').val(null).trigger('change');
        selectedProducts = [];
        renderSelectedProducts();
    }

    /**
     * Close the modal
     */
    function closeModal() {
        if (productSearchModal) {
            productSearchModal.hide();
        }
    }

    /**
     * Add the "Add Product(s)" button to the invoice form
     */
    function addProductButton() {
        // Find the "Add line" button and add our button after it
        const addLineButton = $('.btn.btn-add-line');
        if (addLineButton.length > 0) {
            const productButton = `
                <button type="button" class="button add-order-item" id="lkn-wcip-add-products-btn">
                    ${wcipProductSearch.addProducts}
                </button>
            `;
            addLineButton.after(productButton);

            // Bind click event
            $('#lkn-wcip-add-products-btn').on('click', function(e) {
                e.preventDefault();
                openModal();
            });
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Only add button on invoice creation/edit pages
        if (window.location.href.includes('new-invoice') || window.location.href.includes('edit-invoice')) {
            addProductButton();
        }
    });

    // Expose functions globally if needed
    window.lknWcipProductSearch = {
        openModal: openModal,
        closeModal: closeModal
    };
});