(function ($) {
  'use strict'
  $(document).ready(function () {
    if ($('#lkn_wcip_template_logo_url_btn').length) { // checks if the button exists
      // Mostra para o usuário somente o nome do arquivo que ele selecionou
      const initialFileName = $('#lkn_wcip_template_logo_url').val().match(/\/([^\/?#]+)[^\/]*$/)
      if (initialFileName) {
        $('#lkn_wcip_template_logo_desc').html(initialFileName[1])
      }

      $('#lkn_wcip_template_logo_url').on('change', function () {
        $('#lkn_wcip_template_logo_desc').html($(this).val().match(/\/([^\/?#]+)[^\/]*$/)[1])
      })

      let metaImageFrame
      $('body').click(function (e) {
        const btn = e.target
        if (!btn || !$(btn).attr('data-media-uploader-target')) return
        const field = $(btn).data('media-uploader-target')
        e.preventDefault()
        metaImageFrame = wp.media.frames.metaImageFrame = wp.media({
          button: { text: 'Use this file' }
        })
        metaImageFrame.on('select', function () {
          const media_attachment = metaImageFrame.state().get('selection').first().toJSON()
          $(field).val(media_attachment.url)
          $(field).trigger('change') // Trigger change event manually
        })
        metaImageFrame.open()
      })
    }
  })
})(jQuery)
