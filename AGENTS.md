# AGENTS.md

Guia para agentes trabalhando no plugin `OASwitchboard`. Leia antes de tocar em qualquer código.

## Modo de trabalho

- Uma iteração por vez. Sem misturar escopo.
- Para novas funcionalidades, desenvolvimento em par com o humano. (voce deve ter uma SKILL pra isso)
- Sugira commits ao concluir pequenos passos, sempre passando nos testes de unidade (pelo menos). Humano fará os commits depois de revisar.

## Comandos (sempre da raiz do OJS, dois níveis acima daqui)

### PHPUnit — testes de unidade

```bash
TESTS=$(find -L plugins/generic/OASwitchboard -name tests -type d -maxdepth 1) \
  && lib/pkp/lib/vendor/bin/phpunit --no-coverage --configuration lib/pkp/tests/phpunit.xml $TESTS
```
- setUp e tearDown, test cases etc sempre seguindo as convenções da PKP.

### PHP CS Fixer — antes de considerar PHP pronto

```bash
php lib/pkp/lib/vendor/bin/php-cs-fixer fix \
  --config .php-cs-fixer.php --allow-risky=yes \
  plugins/generic/OASwitchboard/
```

PHP só está pronto quando: testes passam, e fixer rodou sem pendências.

### Cypress — sempre em lote, só o plugin

```bash
npx cypress run \
  --config 'baseUrl=http://localhost:8000,specPattern=plugins/generic/OASwitchboard/cypress/tests/**/*.cy.js' \
  --browser chrome
```

Os testes **não são idempotentes** — rodam em ordem contra banco fresco. **Nunca** rode um spec isolado fora dessa suíte, nem adicione specs ao `cypress.config.js` global.

## Banco de dados

As credenciais ficam no config.inc.php

Sempre que precisar, como para rodar testes cypress garantindo um banco fresco, renove o banco de dados com o dump da PKP (resultado da suíte cypress da aplicação).
-  Dataset pode ser baixado em `https://raw.githubusercontent.com/pkp/datasets/refs/heads/main/ojs/stable-3_5_0/mysql/database.sql`
- ou já existe como database.sql na raiz do OJS

## Nomenclatura

- arquivos de teste devem seguir o nome da classe sendo testada, com sufixo `Test` e extensão `.php`. Exemplo: `MyClassTest.php`
- métodos de teste devem começar com `test` seguido do comportamento esperado. Exemplo: `testShouldReturnTrueWhenConditionIsMet()`
- nomes de metodos e classes devem dizer o que fazem, não como fazem
- evite abreviações