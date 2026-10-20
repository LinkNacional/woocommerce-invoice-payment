# Testes — Invoice Payment for WooCommerce

Testes de **integração** (wp-phpunit) rodando contra um WordPress + WooCommerce reais,
no banco `local_tests` do Local WP.

## Dependências

```bash
composer install
```

`phpunit/phpunit`, `wp-phpunit/wp-phpunit` e `yoast/phpunit-polyfills` estão em
`require-dev` e são autoloadados por `autoload-dev` (`LknWc\WcInvoicePayment\Tests\` → `tests/`).

## Como rodar (Local WP / Linux)

O `php` do sistema normalmente não tem `mysqli`; use o binário CLI do Local com
`LD_LIBRARY_PATH`:

```bash
PHPDIR=/opt/Local/resources/extraResources/lightning-services/php-8.2.29+0
export PATH="$PHPDIR/bin/linux/bin:$PATH"
export LD_LIBRARY_PATH="$PHPDIR/bin/linux/shared-libs:$PHPDIR/bin/linux/lib"
export WP_TESTS_DB_SOCKET=/home/<usuario>/.config/Local/run/<id>/mysql/mysqld.sock

composer test
```

Um teste específico:

```bash
vendor/bin/phpunit --filter OtpEmailSecurityTest --no-coverage
```

## Estrutura

| Arquivo | Cobre |
| --- | --- |
| `OtpEmailSecurityTest.php` | Rotas públicas de OTP: tentativas, lockout, hash do código, throttle de reenvio, enumeração e registro no modo completo. |
| `SubscriptionVariableProductTest.php` | Assinaturas com produtos variáveis: cadência por variação, registro no pedido, agendamento (cron + backup), trava de geração e limite de faturas. |
| `TestCase.php` | Base com helpers (produto variável, variação, pedido, captura de `wp_mail`, reflection em métodos privados). |

## Avisos

- **Nunca** aponte para o banco do site de desenvolvimento: os testes recriam as tabelas
  (`wptests_`) e apagam os dados.
- `wp-tests-config.php` fica em `tests/` de propósito (o release copia `*.php` da raiz).
- HPOS fica desligado (`woocommerce_custom_orders_table_enabled = no`), então os pedidos
  usam o storage legado (posts).
- O plugin usa `current_time( 'timestamp' )` em várias comparações de data. Ao montar
  cenários com datas, use a mesma base (`current_time( 'timestamp' )`), não `time()`.
