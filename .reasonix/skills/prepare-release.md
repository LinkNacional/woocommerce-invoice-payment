---
name: prepare-release
description: Prepara release do woocommerce-invoice-payment: atualiza README.txt, CHANGELOG.md, cabeçalho PHP, constante e os DEPLOY_TAG dos workflows. Changelog vem dos highlights do usuário (git log é só dica).
---

# prepare-release (woocommerce-invoice-payment)

Atualiza **todos** os arquivos que contêm o número de versão para uma nova release do plugin **Link Invoice Payment for WooCommerce** (repo `woocommerce-invoice-payment`, slug wp.org `invoice-payment-for-woocommerce`).

## Parâmetros (via `arguments`)

O usuário pode passar: `"version=2.13.3 tested_up=7.0 php=8.2 highlights=..."`. Se algum valor faltar, pergunte.

- **version** — nova versão (Stable tag). Bump **minor** para adição de recurso/refatoração; **patch** só para correção.
- **tested_up** — versão do WP testada (Tested up to). Atual: `7.0`.
- **php** — versão mínima do PHP (Requires PHP). Atual: `8.2`.
- **highlights** — resumo da versão (itens de mudança). É a **fonte primária** do changelog. Se vazio, pergunte ao usuário o que mudou — **não invente** a partir do `git log`.

## Fluxo de execução

### 1. Coletar valores

```bash
grep -m1 -E "^\s*\*\s*Version:" wc-invoice-payment.php
grep -m1 -E "^\s*\*\s*Requires PHP:" wc-invoice-payment.php
grep -m1 -E "^\s*\*\s*Requires at least:" wc-invoice-payment.php
grep -m1 -i "^Tested up to:" README.txt
grep -m1 -i "^Stable tag:" README.txt
grep -n "WC_PAYMENT_INVOICE_VERSION" wc-invoice-payment.php
grep -rn "DEPLOY_TAG" .github/workflows/*.yml
date +%d/%m/%Y
```

### 2. Changelog (anti-redundância)

O `git log` é **apenas uma dica** (o range costuma estar dessincronizado) — os bullets saem do que o usuário confirmou como novo, **descartando** qualquer item já presente nas entradas anteriores do changelog.

```bash
# dica opcional
LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null)
[ -z "$LAST_TAG" ] && git log -n 12 --oneline || git log ${LAST_TAG}..HEAD --oneline
```

### 3. Atualizar TODOS os arquivos com versão

A versão aparece em **7 locais** espalhados por **5 arquivos**:

#### 3a. `README.txt` (wp.org, changelog em **inglês**, data `MM/DD/YYYY`)

- `Stable tag:` → nova versão
- `Tested up to:` / `Requires PHP:` / `Requires at least:` se alterados
- Adicionar entrada no topo do `== Changelog ==` (formato do repo: `= VERSION = *MM/DD/YYYY*`), com uma linha em branco antes da anterior:
  ```
  = VERSION = *MM/DD/YYYY*
  * Item (a partir dos highlights, NÃO do git log)

  = VERSAO_ANTERIOR = *MM/DD/YYYY*
  ```
- Adicionar a **mesma** entrada no topo do `== Upgrade Notice ==` (≤ 300 caracteres, formato `= VERSION =`).

#### 3b. `CHANGELOG.md` (português, data `DD/MM/YYYY`)

- Adicionar entrada no topo:
  ```
  # VERSION - DD/MM/YYYY
  * Item
  ```

#### 3c. `wc-invoice-payment.php` (raiz)

- `* Version:           VERSION` (cabeçalho)
- `define('WC_PAYMENT_INVOICE_VERSION', 'VERSION');`

#### 3d. `.github/workflows/main.yml`

- `DEPLOY_TAG: "VERSION"`

#### 3e. `.github/workflows/wordpressRelease.yml`

- `DEPLOY_TAG: "VERSION"`

## NÃO alterar (pegadinhas do woocommerce-invoice-payment)

- `README.md` → **não tem** número de versão nem seção de changelog (só descrição/uso/dev notes). Não criar seção de changelog nele.
- `languages/*.po` / `*.mo` → não são versão e não vão no pacote (só o `.pot` viaja). Regenerar traduções é a skill `i18n`, nunca editar à mão.
- `Admin/js/*.js` e `Public/js/*.js` têm `version` própria nos `wp_enqueue_*` (ex.: `'1.5.0'`) — é cache-busting de asset, **não** a versão do plugin. Só mexer se o asset daquela versão mudou.
- `composer.lock` → não carrega versão do plugin.
- `Includes/WcPaymentInvoice.php` → o `$this->version` ali é histórico e **não** é bumpado (a versão real é `WC_PAYMENT_INVOICE_VERSION`).

## 4. Preparar o pacote (`.distignore` + staging do zip)

O zip do release e o deploy wp.org montam `build/invoice-payment-for-woocommerce/` copiando **Admin, Includes, languages (*.pot), Public, *.php, *.txt + vendor**. Conferir:

- `tests/`, `phpunit.xml.dist`, `.reasonix/`, `.github/`, `composer.json`, `composer.lock`, `package.json` **não** entram no zip.
- `languages/` leva **só** o `*.pot` (`*.po`/`*.mo`/`*.json` vivem no git, mas não no pacote).

Traduções (ficam no git, **nunca** no pacote — são importadas no translate.wordpress.org):

- `languages/wc-invoice-payment-pt_BR.po/.mo` — strings do código (skill `i18n`:
  `~/.reasonix/skills/i18n/scripts/lkn-i18n.sh <plugin> pt_BR full|apply|compile`).
- `languages/wc-invoice-payment-readme-pt_BR.po` — tradução do `readme.txt` (export do
  GlotPress: `https://translate.wordpress.org/projects/wp-plugins/invoice-payment-for-woocommerce/stable-readme/pt-br/default/export-translations/?format=po`).
  **Se o readme mudar** (inclusive os links de WooCommerce/WordPress), os `msgid`
  mudam: atualize as entradas correspondentes antes da release.

## 5. Validação final

Grep da versão **antiga** (esperado: só entradas antigas de README.txt/CHANGELOG.md):

```bash
grep -rn "VERSAO_ANTIGA" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" . \
  | grep -v vendor | grep -v node_modules
```

Grep da versão **nova** — deve retornar `README.txt` (3x: stable tag + upgrade notice + changelog), `CHANGELOG.md` (1x), `.php` (2x: header + constante) e 2 workflows (2x) = **8 matches**:

```bash
grep -rn "NOVA_VERSAO" --include="*.php" --include="*.md" --include="*.txt" --include="*.yml" . \
  | grep -v vendor | grep -v node_modules
```

Validar também o corpo da release que o workflow vai publicar:

```bash
bash .github/scripts/generate-release-body.sh | head -20
bash .github/scripts/generate-release-body.sh | tail -12   # bullets da versão nova
```

## 6. Fechar

Rodar a suíte (`composer test` — ver `tests/README.md`) e então seguir para a skill **open-pr**.
