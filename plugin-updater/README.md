# plugin-updater

Está é uma biblioteca PHP com o objetivo de atualizar automaticamente plugins que não são hospedados no site oficial do WordPress. Ela é dividida em duas pastas 'plugin-updater' corresponde a biblioteca que precisa ser importada pelo plugin, 'API' que corresponde as configurações do servidor de downloads.

## Modo de uso

Antes de adicionar o bloco de código ao seu plugin é importante que o servidor já esteja configurado para receber as requisições do mesmo, verifique a pasta API para mais informações.

* Primeiro é necessário que a biblioteca de atualizações esteja na raíz do plugin;
* Após isso é necessário que o seguinte bloco de código seja inserido no arquivo principal do seu plugin:

```php
	require_once __DIR__ . '/plugin-updater/plugin-update-checker.php';


	function lkn_give_unique_plugin_slug_updater() {
	    return new Lkn_Puc_Plugin_UpdateChecker(
		'https://api.linknacional.com.br/linknac_dev/link_api_update.php?slug=give-visa',
		__DIR__ . '/give-unique_plugin_slug.php',//(caso o plugin não precise de compatibilidade com ioncube utilize: __FILE__), //Full path to the main plugin file or functions.php.
		'give-unique_plugin_slug'
	    );
	}

lkn_give_unique_plugin_slug_updater();
```
Pronto agora o plugin-updater está devidamente configurado.
