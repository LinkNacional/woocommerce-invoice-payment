# 2.3.4 - 29/05/2025
* Correção de erro fatal ao editar página.

# 2.3.3 - 27/05/2025
* Alteração em descrição.

# 2.3.2 - 27/05/2025
* Alteração em descrição e correção em icones.

# 2.3.1 - 27/05/2025
* Adição de blueprint na página do wordpress.

# 2.3.0 - 14/02/2025
* Adição de campo para pesquisar email do usuário;
* Adição de hook para processar assinatura automaticamente;
* Adição de função para forçar registro do cliente no checkout.

# 2.2.1 - 31/01/2025
* Correção de compatibilidade com o plugin "Payment Gateway Based Fees for WooCommerce";
* Atualizando link da descrição do plugin.

# 2.2.0 - 14/12/2024
* Adição de referência de asssinatura na página de fatura;
* Adição de configuração para definir idioma do PDF da fatura;
* Correção de exclusão de eventos cron.

# 2.0.1 - 18/11/2024
* Correção de erro para métodos de pagamentos que exigem o país do pedido.

# 2.0.0 - 12/11/2024
* Refatoração no carregamento das classes (Padrão PSR-4);
* Correção de vulnerabilidades.

# 1.7.2 - 30/10/2024
* Correção de erros em PDFs com imagens;
* Correção de erros de traduções.

# 1.7.1 - 04/07/2024
* Corrigir bug de quebra de linha nas informações extra da fatura;
* Troca de texto nas configurações de verificação de email.

# 1.7.0 - 04/07/2024
* Adição de configuração para definir limite de faturas geradas por assinatura;
* Adição de card para exibir IDs de faturas geradas pela assinatura;
* Adição de coluna "Assinatura" na tabela de faturas;
* Adição de configuração que permite ao administrador definir o tempo de antecedência com que a fatura será gerada;
* Alteração na configuração de imagem do PDF para utilizar o modal do Wordpress.

# 1.6.0 - 06/06/2024
* Adicionar Feedback visual ao clicar no botão de baixar faturas; 
* Adicionar alerta na criação de assinaturas; 
* Adicionar botão para criar fatura na página de listar faturas;
* Adicionar botão para criar assinatura na página de listar assinaturas;
* Corrigir listagem de moedas nas configurações da fatura;
* Corrigir bug ao clicar na seção de editar faturas; 
* Corrigir bug no select de templates mostrando a mesma imagem;
* Corrigir função para adicionar e excluir eventos cron;
* Corrigir label de habilitar página de login de faturas.

# 1.5.0 - 08/05/2024
* Adicionar a data de vencimento ao PDF da fatura.
* Adicionar configuração para habilitar e desabilitar a verificação de e-mail.
* Corrigir a geração de PDF.
* Corrigir submenu Editar Fatura.

# 1.4.0 - 04/04/2024
* Ajustar variáveis de escape e métodos de requisição para melhorar a segurança;
* Adicionar modal para compartilhar link da fatura;
* Adicionar produtos com assinaturas recorrentes;
* Adicionar opção de múltiplos métodos de pagamento.

# 1.3.2 - 14/02/2024
* Substituição de echo para esc_html_e ou esc_attr_e, ajuste para cumprir com as regulamentações do WordPress.

# 1.3.1 - 06/11/2023
* Adicionar atributo cache no-store à requisição de geração de PDF.

# 1.3.0 - 01/11/2023
* Adicionar configuração de rodapé padrão;
* Adicionar configuração text_before_payment_link;
* Adicionar configuração para detalhes do remetente;
* Ajustar templates existentes para lidar com as novas configurações;
* Adicionar novo template.

# 1.2.1 - 20/10/2023
* Ajustar para obter logo com curl, ajustar para funcionar no WordPress instalado em diretório.

# 1.2.0 - 18/10/2023
* Adicionar geração de PDF para faturas.

# 1.1.4 - 07/06/2023
* Corrigir erro na tabela de faturas quando a ordem da fatura é deletada.

# 1.1.3 - 14/03/2023
* Correção de bug nos métodos de pagamento;

# 1.1.2 - 10/03/2023
* Correções de bugs;

# 1.1.1 - 10/03/2023
* Atualização de links;
* Mudança no título da configuração;
* Adição de descrição;
* Configuração do Dev Container.

# 1.1.0 - 02/09/2022
* Implementada data de vencimento da fatura;
* Na página de pagamento da fatura, o método de pagamento definido é aberto;
* Usuários com permissão shop_manager podem gerar e editar faturas;
* Otimização do carregamento de JS e CSS.

# 1.0.0 - 01/06/2022
* Lançamento do plugin.