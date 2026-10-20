---
name: open-pr
description: Abre PR de dev → main no woocommerce-invoice-payment no padrão Link Nacional (título VERSION - woocommerce-invoice-payment(resumo); corpo com metadados, Descrição, Instalação e CHANGELOG)
---

# open-pr (woocommerce-invoice-payment)

Abre um Pull Request de `dev` → `main` via `gh pr create`, no padrão Link Nacional, para o repositório `woocommerce-invoice-payment` (plugin **Link Invoice Payment for WooCommerce**, slug wp.org `invoice-payment-for-woocommerce`).

## Parâmetros (via `arguments`)

O usuário pode passar: `version=2.13.3 tested_up=7.0 summary="Assinaturas em produtos variáveis"`. Valores ausentes são extraídos do código.

- **version** — versão da release (Stable tag / cabeçalho PHP)
- **tested_up** — WP testado até
- **summary** — resumo CURTO usado no TÍTULO. Se ausente, derive da entrada mais recente do `CHANGELOG.md` (**não** do `git log`).

## Fluxo de execução

### 1. Extrair metadados

```bash
grep -m1 -E "^\s*\*\s*Version:" wc-invoice-payment.php
grep -m1 -E "^\s*\*\s*Requires PHP:" wc-invoice-payment.php
grep -m1 -E "^\s*\*\s*Requires at least:" wc-invoice-payment.php
grep -m1 -i "^Tested up to:" README.txt
grep -m1 -i "^Stable tag:" README.txt

# Nome do repositório GitHub (NÃO usar basename $PWD nem o slug do wp.org)
REPO_NAME=$(basename -s .git "$(git config --get remote.origin.url)")
# → woocommerce-invoice-payment
```

### 2. Ler o changelog da versão atual

⚠️ **Anti-redundância.** NÃO use `git log` — leia a entrada mais recente do `CHANGELOG.md` (português):

```bash
head -n 12 CHANGELOG.md
```

- **TÍTULO**: resuma os bullets em uma frase curta (≤ ~12 palavras).
- **CORPO (seção CHANGELOG: )**: copie os bullets do `CHANGELOG.md`, sem hash e sem reescrever.

O corpo do PR deve ser **idêntico** ao corpo da GitHub Release. A forma canônica de gerá-lo é o template versionado:

```bash
bash .github/scripts/generate-release-body.sh > /tmp/lkn-wcip-pr-body.md
cat /tmp/lkn-wcip-pr-body.md
```

O script lê `Tested up to` / `Requires PHP` / `Stable tag` do `README.txt` e os bullets da versão estável no `CHANGELOG.md`. Se você precisar montar à mão, use `.github/release-body-template.md` como gabarito (placeholders `${TESTED_UP}`, `${REQUIRES_PHP}`, `${STABLE_TAG}`, `${VERSION_SUMMARY}`).

### 3. Montar TÍTULO

Formato exato (obrigatório) — **sem espaço antes do parêntese**:

```
VERSION - REPO_NAME(RESUMO_CURTO)
```

Exemplo:

```
2.13.3 - woocommerce-invoice-payment(Assinaturas em produtos variáveis e correção de segurança no OTP)
```

### 4. Corpo (referência)

```markdown
# Link Invoice Payment for WooCommerce
Contribuidores: linknacional
Link: https://www.linknacional.com.br/wordpress/
Tags: subscription, invoice, payment, recorrente, faturas
Testado até: {TESTED_UP}
Versão estável: {VERSION}
Requer PHP: {PHP}
Licença: GPLv3 ou posterior
URI da Licença: https://www.gnu.org/licenses/gpl-3.0.html
Traduções: Português(Brasil) / Inglês

Extensão para o WooCommerce que simplifica a cobrança online: faturas avulsas ou recorrentes (assinaturas), com link de pagamento seguro.

## Descrição

{gabarito completo em .github/release-body-template.md — não invente seções novas}

## Instalação

{idem — ver .github/release-body-template.md}

## CHANGELOG:

{BULLETS copiados da entrada mais recente do CHANGELOG.md, no formato "* Item". NÃO inventar a partir do git log.}
```

### 5. Abrir o PR

Sempre `dev` → `main`:

```bash
gh pr create --base main --head dev \
  --title "VERSION - woocommerce-invoice-payment(RESUMO_CURTO)" \
  --body-file /tmp/lkn-wcip-pr-body.md
```

### 6. Confirmar

Mostre a URL retornada pelo `gh` e o comando usado. Se o PR já existir, o `gh` avisa — não force sem pedir.

Depois do merge, o workflow `.github/workflows/main.yml` gera a GitHub Release (draft) com o `.zip` do plugin e o **mesmo corpo** do PR (via `.github/scripts/generate-release-body.sh`).

## Regras

- **Nunca** edite arquivos do repo para abrir o PR (é só `gh pr create`). Bump de versão é a skill **prepare-release**.
- Título SEMPRE `VERSION - woocommerce-invoice-payment(resumo)`, **sem espaço antes do parêntese**.
- Corpo SEMPRE com cabeçalho de metadados, Descrição, Instalação e a seção `## CHANGELOG:`.
- Se `version`/`tested_up` divergirem entre o cabeçalho PHP e o `README.txt`, use o **cabeçalho PHP** e avise.
- Nunca inclua hashes de commit no corpo.
- Bullets do corpo SEMPRE da entrada mais recente do `CHANGELOG.md` — nunca do `git log`.
- `gh pr edit`/`gh pr view` pode falhar com "Projects (classic) is being deprecated" (GraphQL); nesse caso use a REST:
  ```bash
  gh api -X PATCH repos/LinkNacional/woocommerce-invoice-payment/pulls/N \
    -f title="..." -F body=@/tmp/lkn-wcip-pr-body.md
  ```
