/* Exemplo de como implementar a funcionalidade em outras páginas customizadas */

/* Se você quiser adicionar essa funcionalidade em outras páginas do seu plugin,
   siga os passos abaixo: */

// 1. No PHP, enfileirar os scripts necessários:
function custom_page_enqueue_scripts() {
    // Scripts básicos do WooCommerce
    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_script('select2');
    wp_enqueue_style('woocommerce_admin_styles');
    
    // Seu script personalizado
    wp_enqueue_script(
        'custom-product-search', 
        plugin_dir_url(__FILE__) . 'js/custom-page-product-search.js', 
        array('jquery', 'select2', 'wc-enhanced-select'), 
        '1.0.0', 
        true
    );
    
    // Dados localizados
    wp_localize_script(
        'custom-product-search',
        'customProductSearch',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'searchNonce' => wp_create_nonce('search-products'),
            'productNonce' => wp_create_nonce('lkn-wcip-product-data'),
            'addProducts' => __('Adicionar produto(s)', 'your-textdomain'),
            'searchProducts' => __('Pesquisar Produtos', 'your-textdomain'),
            // ... outros textos
        )
    );
}

// 2. HTML do botão na sua página customizada:
?>
<div class="your-form-section">
    <!-- Seus campos existentes aqui -->
    
    <div class="add-products-section">
        <button type="button" class="button add-order-item" id="custom-add-products-btn">
            <?php _e('Adicionar produto(s)', 'your-textdomain'); ?>
        </button>
        <button type="button" class="btn btn-add-line" onclick="your_add_line_function()">
            <?php _e('Add line', 'your-textdomain'); ?>
        </button>
    </div>
</div>

<?php
// 3. JavaScript customizado (baseado no arquivo wc-invoice-payment-product-search.js):

?>
<script>
jQuery(document).ready(function($) {
    // Adapte a função addProductButton() para sua página:
    function addCustomProductButton() {
        $('#custom-add-products-btn').on('click', function(e) {
            e.preventDefault();
            // Usar a mesma função do modal original
            if (window.lknWcipProductSearch) {
                window.lknWcipProductSearch.openModal();
            }
        });
    }
    
    // Adapte a função addProductsToInvoice() para sua estrutura de formulário:
    function addProductsToCustomForm() {
        selectedProducts.forEach(function(product) {
            const totalPrice = (parseFloat(product.price) * product.quantity).toFixed(2);
            const productName = `${product.name} (x${product.quantity})`;
            
            // Substitua por sua função de adicionar linha
            your_add_line_function();
            
            // Adapte para seus campos específicos
            const lastNameInput = $('.your-name-input').last();
            const lastPriceInput = $('.your-price-input').last();
            
            if (lastNameInput.length && lastPriceInput.length) {
                lastNameInput.val(productName);
                lastPriceInput.val(totalPrice);
            }
        });
    }
    
    addCustomProductButton();
});
</script>

<?php
/* 
ESTRUTURA RECOMENDADA PARA SUA PÁGINA CUSTOMIZADA:

1. Formulário com campos de produto:
   - Nome do produto/serviço
   - Valor/preço
   - Botão de remover linha

2. Botões de ação:
   - "Adicionar produto(s)" (usa o modal)
   - "Add line" (adiciona linha vazia)

3. Modal (será criado automaticamente pelo script):
   - Campo de pesquisa
   - Lista de produtos selecionados
   - Controles de quantidade
   - Botões cancelar/adicionar

EXEMPLO DE CAMPOS HTML:

<div class="product-lines-container">
    <div class="product-line">
        <input type="text" name="product_name[]" class="your-name-input" placeholder="Nome do produto">
        <input type="number" name="product_price[]" class="your-price-input" placeholder="Preço" step="0.01">
        <button type="button" class="remove-line">Remover</button>
    </div>
</div>

FUNÇÃO JAVASCRIPT PARA ADICIONAR LINHA:

function your_add_line_function() {
    const container = $('.product-lines-container');
    const newLine = `
        <div class="product-line">
            <input type="text" name="product_name[]" class="your-name-input" placeholder="Nome do produto">
            <input type="number" name="product_price[]" class="your-price-input" placeholder="Preço" step="0.01">
            <button type="button" class="remove-line">Remover</button>
        </div>
    `;
    container.append(newLine);
}

*/
