# Funcionalidade de Pesquisa e Adição de Produtos

Este documento descreve a nova funcionalidade implementada no plugin WooCommerce Invoice Payment que permite pesquisar e adicionar produtos do WooCommerce diretamente na criação/edição de faturas.

## Funcionalidades Implementadas

### 1. Botão "Adicionar produto(s)"
- Localizado próximo ao botão "Add line" nas páginas de criação e edição de faturas
- Estilo similar ao botão do WooCommerce para manter consistência visual
- Abre um modal de pesquisa de produtos

### 2. Modal de Pesquisa de Produtos
- Interface intuitiva com campo de pesquisa usando Select2
- Pesquisa em tempo real (após 3 caracteres digitados)
- Suporte a produtos simples e variações
- Limite de 10 produtos selecionados por vez

### 3. Seleção e Configuração de Produtos
- Lista de produtos selecionados com informações detalhadas:
  - Nome do produto
  - Preço formatado
  - Campo para quantidade
  - Botão para remover da seleção
- Validação de quantidade (mínimo 1, máximo 9999)

### 4. Integração com Formulário de Fatura
- Produtos selecionados são automaticamente adicionados ao formulário
- Nome inclui a quantidade (ex: "Produto X (x2)")
- Preço calculado automaticamente (preço unitário × quantidade)
- Adicionados como novas linhas no formulário existente

## Arquivos Modificados/Criados

### JavaScript
- `Admin/js/wc-invoice-payment-product-search.js` - Script principal da funcionalidade

### CSS
- `Admin/css/wc-invoice-payment-admin.css` - Estilos do modal e componentes

### PHP
- `Admin/WcPaymentInvoiceAdmin.php` - Carregamento de scripts e endpoint AJAX
- `Includes/WcPaymentInvoice.php` - Registro de hooks AJAX

## Como Usar

1. **Acessar página de criação/edição de fatura:**
   - Navegue para "Faturas" > "Add invoice" ou edite uma fatura existente

2. **Abrir modal de produtos:**
   - Clique no botão "Adicionar produto(s)" localizado próximo ao botão "Add line"

3. **Pesquisar produtos:**
   - Digite pelo menos 3 caracteres no campo de pesquisa
   - Selecione os produtos desejados da lista de resultados

4. **Configurar quantidades:**
   - Ajuste a quantidade de cada produto selecionado
   - Use o botão de lixeira para remover produtos indesejados

5. **Adicionar à fatura:**
   - Clique em "Add to Invoice" para adicionar os produtos ao formulário
   - Os produtos aparecerão como novas linhas no formulário principal

## Recursos Técnicos

### Segurança
- Verificação de nonce em todas as requisições AJAX
- Sanitização de dados de entrada
- Verificação de permissões do usuário

### Performance
- Busca com cache habilitado
- Limite de resultados (20 por busca)
- Debounce na pesquisa para evitar muitas requisições

### Compatibilidade
- Integra com o sistema nativo de pesquisa do WooCommerce
- Compatível com produtos simples e variações
- Responsivo para diferentes tamanhos de tela

### Internacionalização
- Todas as strings são traduzíveis
- Suporte ao sistema de traduções do WordPress

## Hooks AJAX Registrados

- `wp_ajax_lkn_wcip_get_product_data` - Busca dados detalhados do produto
- `wp_ajax_nopriv_lkn_wcip_get_product_data` - Versão para usuários não logados

## Dependências

### JavaScript
- jQuery
- Select2 (do WooCommerce)
- WooCommerce Enhanced Select

### PHP
- WooCommerce ativo
- Função `wc_get_product()` disponível
- Sistema de pesquisa de produtos do WooCommerce

## Troubleshooting

### Modal não abre
- Verifique se os scripts estão carregados corretamente
- Confirme que está em uma página de criação/edição de fatura

### Pesquisa não retorna resultados
- Verifique se o WooCommerce está ativo
- Confirme que existem produtos publicados
- Verifique se digitou pelo menos 3 caracteres

### Produtos não são adicionados ao formulário
- Verifique erros no console do navegador
- Confirme que a função `lkn_wcip_add_amount_row()` existe
- Verifique se os campos do formulário estão presentes

## Customizações Possíveis

### Limite de produtos
Altere a variável `maximumSelectionLength` no JavaScript:
```javascript
maximumSelectionLength: 10, // Altere este valor
```

### Limite de caracteres para pesquisa
Altere a variável `minimumInputLength` no JavaScript:
```javascript
minimumInputLength: 3, // Altere este valor
```

### Estilos do modal
Modifique as classes CSS no arquivo `wc-invoice-payment-admin.css`:
- `.lkn-wcip-modal` - Container principal
- `.lkn-wcip-modal-content` - Conteúdo do modal
- `.lkn-wcip-selected-product` - Item de produto selecionado

Esta funcionalidade proporciona uma experiência mais fluida e integrada para adicionar produtos às faturas, aproveitando todo o sistema nativo do WooCommerce.
