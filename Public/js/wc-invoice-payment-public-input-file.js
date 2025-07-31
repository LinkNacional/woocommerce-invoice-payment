(function ($) {
  'use strict'
  
  // Função para inicializar o upload de mídia
  function initializeMediaUploader() {
    const logoField = $('#lkn_wcip_template_logo_url')
    
    if (!logoField.length) {
      console.log('Campo logo não encontrado')
      return
    }

    // Cria o botão se não existir
    if (!$('#lkn_wcip_template_logo_url_btn').length) {
      const uploadButton = $('<button type="button" id="lkn_wcip_template_logo_url_btn" class="button" data-media-uploader-target="#lkn_wcip_template_logo_url">Selecionar Imagem</button>')
      const descDiv = $('<div id="lkn_wcip_template_logo_desc" style="margin-top: 5px; font-style: italic; color: #666;"></div>')
      
      // Adiciona o botão após o campo
      logoField.after(uploadButton)
      uploadButton.after(descDiv)
    }

    // Mostra para o usuário somente o nome do arquivo que ele selecionou
    const initialFileName = logoField.val().match(/\/([^\/?#]+)[^\/]*$/)
    if (initialFileName) {
      $('#lkn_wcip_template_logo_desc').html(initialFileName[1])
    }

    logoField.on('change', function () {
      const fileName = $(this).val().match(/\/([^\/?#]+)[^\/]*$/)
      if (fileName) {
        $('#lkn_wcip_template_logo_desc').html(fileName[1])
      }
    })

    let metaImageFrame
    $('body').off('click.mediaUploader').on('click.mediaUploader', '#lkn_wcip_template_logo_url_btn', function (e) {
      e.preventDefault()
      const field = $(this).data('media-uploader-target')
      
      metaImageFrame = wp.media.frames.metaImageFrame = wp.media({
        button: { text: 'Use this file' },
        multiple: false
      })
      
      metaImageFrame.on('select', function () {
        const media_attachment = metaImageFrame.state().get('selection').first().toJSON()
        $(field).val(media_attachment.url)
        $(field).trigger('change') // Trigger change event manually
      })
      
      metaImageFrame.open()
    })
  }

  // Observer para monitorar quando o elemento aparecer no DOM
  function observeForElement() {
    // Verifica se o campo já existe
    if ($('#lkn_wcip_template_logo_url').length) {
      initializeMediaUploader()
      return
    }

    // Cria o observer para monitorar mudanças no DOM
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) { // Element node
            // Verifica se o elemento foi adicionado diretamente
            if ($(node).is('#lkn_wcip_template_logo_url') || 
                $(node).find('#lkn_wcip_template_logo_url').length) {
              initializeMediaUploader()
              observer.disconnect() // Para o observer após encontrar o elemento
            }
          }
        })
      })
    })

    // Inicia a observação
    observer.observe(document.body, {
      childList: true,
      subtree: true
    })

    // Timeout de segurança para parar o observer após 30 segundos
    setTimeout(function () {
      observer.disconnect()
    }, 30000)
  }

  $(document).ready(function () {
    observeForElement()
  })
})(jQuery)
