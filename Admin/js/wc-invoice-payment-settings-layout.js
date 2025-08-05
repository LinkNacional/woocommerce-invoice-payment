(function ($) {
  $(window).load(function () {
    //Se o parametro url for tab=wc_payment_subscription_settings
    if (window.location.href.indexOf('tab=wc_payment_subscription_settings') !== -1) {
      // Encontra todas as linhas que contêm input type="number"
      const numberRows = Array.from(document.querySelectorAll('tr')).filter(tr => 
        tr.querySelector('input[type="number"]')
      )

      numberRows.forEach(numberRow => {
        const selectRow = numberRow.nextElementSibling
        
        // Verifica se a próxima linha tem um select
        if (selectRow && selectRow.querySelector('select')) {
          const numberTd = numberRow.querySelector('td')
          const selectTd = selectRow.querySelector('td')

          if (numberTd && selectTd) {
            // Move o <select> e o .select2 para dentro do <td> da primeira <tr>
            const select = selectTd.querySelector('select')
            const select2Container = selectTd.querySelector('.select2')
            const description = numberTd.querySelector('p.description')
            
            // Aplica estilos com !important usando setProperty
            if (select2Container) {
              select2Container.style.setProperty('min-width', '200px', 'important')
              select2Container.style.setProperty('max-width', '200px', 'important')
              select2Container.style.setProperty('width', '200px', 'important')
              console.log(select2Container.style)
            }

            // Cria um div para conter os campos em flex
            const flexDiv = document.createElement('div')
            flexDiv.style.display = 'flex'
            
            // Remove o display flex do td
            numberTd.style.display = ''
            
            // Move os campos para dentro do flexDiv
            const numberInput = numberTd.querySelector('input[type="number"]')
            if (numberInput) flexDiv.appendChild(numberInput)
            if (select) flexDiv.appendChild(select)
            if (select2Container) flexDiv.appendChild(select2Container)
            
            // Adiciona o flexDiv no início do td
            numberTd.insertBefore(flexDiv, numberTd.firstChild)
            
            // Garante que a descrição fique após o flexDiv
            if (description) {
              numberTd.appendChild(description)
            }

            // Remove a <tr> do select
            selectRow.remove()
          }
        }
      })
    }
    // Selecionar os elementos
    const mainForm = document.querySelector('#mainform')
    const fistH1 = mainForm.querySelector('h1')
    const submitP = mainForm.querySelector('p.submit')
    const tables = mainForm.querySelectorAll('table')

    if (mainForm && fistH1 && submitP && tables) {

      // Aplicar estilos aos formulários
      document.querySelectorAll('.form-table > tbody > tr').forEach(tr => {
        const label = tr.querySelector('th')
        const helpTip = tr.querySelector('.woocommerce-help-tip')
        const forminp = tr.querySelector('.forminp')
        const legend = tr.querySelector('.forminp legend')
        const fieldset = tr.querySelector('.forminp fieldset')
        const titledesc = tr.querySelector('.titledesc')

        if (titledesc && label) {
          label.style.fontSize = '20px'
          label.style.color = '#121519'
          titledesc.style.verticalAlign = 'middle'
        }

        if (label && helpTip && titledesc) {
          const helpText = helpTip.getAttribute('aria-label')
          if (helpText) {
            const p = document.createElement('p')
            p.textContent = helpText
            p.style.margin = '5px 0 10px'
            p.style.fontSize = '13px'
            p.style.color = '#343B45'
            label.after(p)
          }
          helpTip.remove()
        }

        // Aplicar layout para todos os campos (com ou sem fieldset)
        if (forminp && label) {
          const titleText = label.textContent.trim()

          // Se existe fieldset, usar a lógica existente
          if (legend && fieldset) {
            // Cria divs para header e body
            const headerDiv = document.createElement('div')
            headerDiv.className = 'lkn-header-cart'
            headerDiv.style.minHeight = '44px'

            const bodyDiv = document.createElement('div')
            bodyDiv.className = 'lkn-body-cart'
            bodyDiv.style.display = 'flex'
            bodyDiv.style.flexDirection = 'column'
            bodyDiv.style.alignItems = 'start'
            bodyDiv.style.justifyContent = 'center'
            bodyDiv.style.minHeight = '120px'
            bodyDiv.style.paddingLeft = '4px'
            bodyDiv.style.color = '#2C3338'

            // Cria título interno
            const titleInside = document.createElement('div')
            titleInside.textContent = titleText
            titleInside.style.fontWeight = 'bold'
            titleInside.style.fontSize = '16px'
            titleInside.style.margin = '6px 4px'

            // Cria descrição vazia
            const descBlock = document.createElement('div')
            descBlock.className = 'description-title'

            // Cria linha divisória
            const divider = document.createElement('div')
            divider.style.borderTop = '1px solid #ccc'
            divider.style.margin = '8px 0'
            divider.style.width = '100%'

            // Monta o header
            headerDiv.appendChild(titleInside)
            headerDiv.appendChild(descBlock)
            headerDiv.appendChild(divider)

            // Move os elementos existentes do fieldset para body (preservando-os)
            const childrenToMove = Array.from(fieldset.childNodes).filter(node => node !== legend)
            childrenToMove.forEach(node => bodyDiv.appendChild(node))

            // Remove br desnecessários
            const brElements = bodyDiv.querySelectorAll('br')
            brElements.forEach(br => br.remove())

            // Garante que existe um parágrafo de descrição
            const pElement = bodyDiv.querySelector('p')
            if (!pElement) {
              const p = document.createElement('p')
              p.classList.add('description')
              p.style.marginTop = '8px'
              bodyDiv.appendChild(p)
            }

            // Estiliza inputs
            const inputElement = bodyDiv.querySelector('input[type="text"], input[type="number"], input[type="password"], select, textarea')
            if (inputElement) {
              inputElement.style.minWidth = '200px'
              inputElement.style.width = '100%'
              inputElement.style.maxWidth = '400px'
            }

            // Tratamento especial para checkboxes
            const checkboxInput = bodyDiv.querySelector('input[type="checkbox"]')
            const checkboxLabel = bodyDiv.querySelector('label')

            if (checkboxLabel && checkboxInput) {
              const input = checkboxLabel.querySelector('input') || checkboxInput
              const nameAttr = input?.getAttribute('name')
              const checked = input?.checked

              // Oculta o checkbox original
              if (input) {
                input.style.display = 'none'
                if (checkboxLabel) checkboxLabel.style.display = 'none'

                // Cria os rádios para Invoices
                const radioYes = document.createElement('label')
                radioYes.innerHTML = `
                  <input type="radio" name="${nameAttr}-control" value="1" ${checked ? 'checked' : ''}>
                    Habilitar
                `

                const radioNo = document.createElement('label')
                radioNo.innerHTML = `
                  <input type="radio" name="${nameAttr}-control" value="0" ${!checked ? 'checked' : ''}>
                    Desabilitar
                `

                const radioYesInput = radioYes.querySelector('input')
                const radioNoInput = radioNo.querySelector('input')

                // Vincula os eventos para controlar o checkbox oculto
                radioYesInput.addEventListener('change', () => {
                  if (radioYesInput.checked) input.checked = true
                })

                radioNoInput.addEventListener('change', () => {
                  if (radioNoInput.checked) input.checked = false
                })

                // Adiciona os radios
                bodyDiv.insertBefore(radioNo, bodyDiv.firstChild)
                bodyDiv.insertBefore(radioYes, bodyDiv.firstChild)
              }
            }

            // Move o legend para o header (preservando-o)
            headerDiv.insertBefore(legend, headerDiv.firstChild)

            // Adiciona os novos containers ao fieldset (sem limpar)
            fieldset.appendChild(headerDiv)
            fieldset.appendChild(bodyDiv)

            // Aplica estilos ao fieldset
            fieldset.style.display = 'flex'
            fieldset.style.flexDirection = 'column'
            fieldset.style.width = '100%'
            fieldset.style.flex = '1'
          } else {
            // Para campos sem fieldset, criar a estrutura de layout também
            // Cria o container principal
            const fieldContainer = document.createElement('div')
            fieldContainer.style.display = 'flex'
            fieldContainer.style.flexDirection = 'column'
            fieldContainer.style.width = '100%'
            fieldContainer.style.flex = '1'

            // Cria divs para header e body
            const headerDiv = document.createElement('div')
            headerDiv.className = 'lkn-header-cart'
            headerDiv.style.minHeight = '44px'

            const bodyDiv = document.createElement('div')
            bodyDiv.className = 'lkn-body-cart'
            bodyDiv.style.display = 'flex'
            bodyDiv.style.flexDirection = 'column'
            bodyDiv.style.alignItems = 'start'
            bodyDiv.style.justifyContent = 'center'
            bodyDiv.style.minHeight = '120px'
            bodyDiv.style.paddingLeft = '4px'
            bodyDiv.style.color = '#2C3338'

            // Cria título interno
            const titleInside = document.createElement('div')
            titleInside.textContent = titleText
            titleInside.style.fontWeight = 'bold'
            titleInside.style.fontSize = '16px'
            titleInside.style.margin = '6px 4px'

            // Cria descrição vazia
            const descBlock = document.createElement('div')
            descBlock.className = 'description-title'

            // Cria linha divisória
            const divider = document.createElement('div')
            divider.style.borderTop = '1px solid #ccc'
            divider.style.margin = '8px 0'
            divider.style.width = '100%'

            // Monta o header
            headerDiv.appendChild(titleInside)
            headerDiv.appendChild(descBlock)
            headerDiv.appendChild(divider)

            // Move os elementos existentes para o body (preservando-os)
            const childrenToMove = Array.from(forminp.childNodes)
            childrenToMove.forEach(node => bodyDiv.appendChild(node))

            // Remove br desnecessários
            const brElements = bodyDiv.querySelectorAll('br')
            brElements.forEach(br => br.remove())

            // Garante que existe um parágrafo de descrição
            const pElement = bodyDiv.querySelector('p')
            if (!pElement) {
              const p = document.createElement('p')
              p.classList.add('description')
              p.style.marginTop = '8px'
              bodyDiv.appendChild(p)
            }

            // Estiliza inputs
            const inputElement = bodyDiv.querySelector('input[type="text"], input[type="number"], input[type="password"], select, textarea')
            if (inputElement) {
              inputElement.style.minWidth = '200px'
              inputElement.style.width = '100%'
              inputElement.style.maxWidth = '400px'
            }

            // Tratamento especial para checkboxes
            const checkboxInput = bodyDiv.querySelector('input[type="checkbox"]')
            const checkboxLabel = bodyDiv.querySelector('label')

            if (checkboxLabel && checkboxInput) {
              const input = checkboxLabel.querySelector('input') || checkboxInput
              const nameAttr = input?.getAttribute('name')
              const checked = input?.checked

              // Oculta o checkbox original
              if (input) {
                input.style.display = 'none'
                if (checkboxLabel) checkboxLabel.style.display = 'none'

                // Cria os rádios para Invoices
                const radioYes = document.createElement('label')
                radioYes.innerHTML = `
                  <input type="radio" name="${nameAttr}-control" value="1" ${checked ? 'checked' : ''}>
                    Habilitar
                `

                const radioNo = document.createElement('label')
                radioNo.innerHTML = `
                  <input type="radio" name="${nameAttr}-control" value="0" ${!checked ? 'checked' : ''}>
                    Desabilitar
                `

                const radioYesInput = radioYes.querySelector('input')
                const radioNoInput = radioNo.querySelector('input')

                // Vincula os eventos para controlar o checkbox oculto
                radioYesInput.addEventListener('change', () => {
                  if (radioYesInput.checked) input.checked = true
                })

                radioNoInput.addEventListener('change', () => {
                  if (radioNoInput.checked) input.checked = false
                })

                // Adiciona os radios
                bodyDiv.insertBefore(radioNo, bodyDiv.firstChild)
                bodyDiv.insertBefore(radioYes, bodyDiv.firstChild)
              }
            }

            // Monta a estrutura final
            fieldContainer.appendChild(headerDiv)
            fieldContainer.appendChild(bodyDiv)

            // Adiciona a nova estrutura ao forminp (sem limpar)
            forminp.appendChild(fieldContainer)

            if (forminp.querySelector('.wp-editor-wrap')) {
              let id = forminp.querySelector('textarea').id
              tinymce.execCommand('mceRemoveEditor', true, id)
              forminp.querySelector('.mce-tinymce.mce-container.mce-panel')?.remove()
              tinymce.execCommand('mceAddEditor', true, id)
            }
          }

          // Estiliza o forminp (aplicado para todos os campos)
          forminp.style.display = 'flex'
          forminp.style.flexDirection = 'column'
          forminp.style.alignItems = 'flex-start'
          forminp.style.backgroundColor = 'white'
          forminp.style.padding = '10px 30px'
          forminp.style.borderRadius = '4px'
          forminp.style.boxSizing = 'border-box'
          forminp.style.border = '1px solid #DFDFDF'
          forminp.style.width = '100%'
        }
      })

      // Observer para Select2
      const observer = new MutationObserver(function () {
        const selects = document.querySelectorAll('.select2.select2-container')
        if (selects.length > 0) {
          selects.forEach(select => {
            select.style.setProperty('min-width', '200px', 'important')
            select.style.width = '100%'
            select.style.maxWidth = '400px'
          })
          observer.disconnect()
        }
      })

      observer.observe(document.body, { childList: true, subtree: true })
    }

    const message = $('<p id="footer-left" class="alignleft"></p>')
    
    message.html('Saiba mais sobre nossos plugins, suporte e manutenção 24h para WordPress na <a href="https://www.linknacional.com.br/wordpress/plugins/" target="_blank">Link Nacional</a> | Avaliar esse plugin <a href="https://wordpress.org/support/plugin/wc-invoice-payment/reviews/?filter=5#postform" target="_blank" class="give-rating-link" style="text-decoration:none;" data-rated="Obrigado :)">★★★★★</a>')

    message.css({
      'text-align': 'center',
      padding: '10px 0px',
      'font-size': '13px',
      color: '#666'
    })

    $('#lknWcInvoicesSettingsLayoutDiv').append(message).css('display', 'table')
    document.dispatchEvent(new Event('lknWcInvoicesFinishedAdminLayout'))

    
  })
})(jQuery)