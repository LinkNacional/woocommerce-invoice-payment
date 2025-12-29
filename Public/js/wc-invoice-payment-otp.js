/**
 * JavaScript para formulário OTP Email
 * @package WcInvoicePayment
 */

jQuery(document).ready(function($) {
    let currentEmail = '';

    // Enviar código
    $('#lkn-otp-email-form').on('submit', function(e) {
        e.preventDefault();
        
        const email = $('#lkn_otp_email').val();
        currentEmail = email;
        
        $('#lkn-otp-messages').html('<div class="woocommerce-info">Enviando código...</div>');
        
        wp.apiFetch({
            path: '/invoice_payments/send_otp_code',
            method: 'POST',
            data: {
                email: email
            }
        }).then(function(response) {
            if (response.success) {
                $('#lkn-otp-email-form').children().eq(1).hide();
                $('#lkn-otp-email-form input').prop('disabled', true);
                $('#lkn-otp-code-form').show();
                
                // Atualiza a label e a dica do campo de código
                const label = $('#lkn-otp-code-form label[for="lkn_otp_code"]');
                const hint = $('#lkn-otp-code-form small');
                
                label.html('Código enviado para seu email. <span class="required">*</span>');
                
                // Calcula horário de expiração (5 minutos a partir de agora) no horário local
                const expirationTime = new Date();
                expirationTime.setMinutes(expirationTime.getMinutes() + 5);
                const expirationTimeString = expirationTime.toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                hint.html('Digite o código de 6 dígitos enviado para o seu email, expira às ' + expirationTimeString + '.<br><a href="#" id="lkn-otp-back">Voltar e cancelar.</a>');
                
                $('#lkn-otp-messages').html('<div class="woocommerce-message">' + response.message + '</div>');
                $('#lkn_otp_code').focus();
            } else {
                $('#lkn-otp-messages').html('<div class="woocommerce-error">' + response.message + '</div>');
            }
        }).catch(function(error) {
            console.error('Error:', error);
            $('#lkn-otp-messages').html('<div class="woocommerce-error">Erro de conexão.</div>');
        });
    });

    // Verificar código
    $('#lkn-otp-code-form').on('submit', function(e) {
        e.preventDefault();
        
        const code = $('#lkn_otp_code').val();
        
        $('#lkn-otp-messages').html('<div class="woocommerce-info">Verificando código...</div>');
        
        wp.apiFetch({
            path: '/invoice_payments/verify_otp_code',
            method: 'POST',
            data: {
                email: currentEmail,
                code: code
            }
        }).then(function(response) {
            if (response.success) {
                $('#lkn-otp-messages').html('<div class="woocommerce-message">' + response.message + '</div>');
                // Redireciona após sucesso
                setTimeout(function() {
                    window.location.href = response.redirect || wcInvoicePaymentOtp.dashboardUrl;
                }, 1000);
            } else {
                $('#lkn-otp-messages').html('<div class="woocommerce-error">' + response.message + '</div>');
            }
        }).catch(function(error) {
            if(error.success == false){
                $('#lkn-otp-messages').html('<div class="woocommerce-error">' + error.message + '</div>');
            }else{
                console.log(error)
                $('#lkn-otp-messages').html('<div class="woocommerce-error">Erro de conexão.</div>');
            }
        });
    });

    // Link voltar e cancelar (event delegation pois é criado dinamicamente)
    $(document).on('click', '#lkn-otp-back', function(e) {
        e.preventDefault();
        if (confirm('Tem certeza que deseja voltar?')) {
            $('#lkn-otp-code-form').hide();
            $('#lkn-otp-email-form').children().eq(1).show();
            $('#lkn-otp-email-form input').prop('disabled', false);
            $('#lkn_otp_code').val('');
            $('#lkn-otp-messages').empty();
            
            // Restaura a label e dica originais
            const label = $('#lkn-otp-code-form label[for="lkn_otp_code"]');
            const hint = $('#lkn-otp-code-form small');
            
            label.html('Código de acesso <span class="required">*</span>');
            hint.html('Digite o código de 6 dígitos enviado para seu email<br><a href="#" id="lkn-otp-back">Voltar e cancelar.</a>');
        }
    });

    // Auto-focus no campo de código
    $(document).on('input', '#lkn_otp_code', function() {
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
    });

    // Se o modo OTP é 'login_only', remove os elementos de login padrão do WooCommerce
    if (wcInvoicePaymentOtp.get_otp_mode == 'register_and_login') {
        $('#customer_login').remove();
    }
    if(wcInvoicePaymentOtp.get_otp_mode == 'login_only'){
        // Pega o primeiro filho de #customer_login
        const firstChild = $('#customer_login').children().first();
        
        // Aplica display block no primeiro filho
        firstChild.css('display', 'block');
        
        // Remove todos os filhos do primeiro elemento
        firstChild.empty();
        
        // Move o elemento .lkn-otp-auth-wrapper para dentro do primeiro filho
        $('.lkn-otp-auth-wrapper').appendTo(firstChild);
        
        // Pega o segundo filho de #customer_login e aplica display block
        const secondChild = $('#customer_login').children().eq(1);
        secondChild.css('display', 'block');
    }
});
