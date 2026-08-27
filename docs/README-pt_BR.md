**Português Brasileiro** | [English](/README.md) | [Español](/docs/README-es.md)

# Plugin Open Access Switchboard

[![OJS compatibility](https://img.shields.io/badge/ojs-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/releases)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)
[![Number of downloads](https://img.shields.io/github/downloads/lepidus/OASwitchboard/total)](https://github.com/lepidus/OASwitchboard/releases)

Este plugin conecta revistas que usam o [OJS](https://pkp.sfu.ca/software/ojs/) ao [OA Switchboard](https://www.oaswitchboard.org/), a infraestrutura compartilhada que troca metadados de comunicação científica entre editoras, instituições e financiadores. Quando um artigo é publicado, seus [metadados de publicação](#quais-metadados-são-enviados) são extraídos automaticamente e enviados às instituições e aos financiadores de pesquisa relevantes como uma **mensagem P1-PIO** padronizada.

**Anúncio:** [OA Switchboard OJS plug-in: Supporting diamond journals to increase the visibility of their OA output among research funders, libraries, and consortia](https://www.oaswitchboard.org/ojs-plugin).

## Como funciona

Antes da publicação, o plugin verifica se o artigo tem os metadados que o OA Switchboard exige e mostra o resultado na aba **OA Switchboard**, dentro do fluxo de trabalho da submissão. Artigos com pendências podem ser publicados normalmente, apenas sem o envio da mensagem.

## Primeiros passos

### O que sua instalação do OJS precisa ter

Duas configurações comuns do OJS, normalmente já em vigor:

- **Tarefas em segundo plano** em execução, para que as mensagens saiam logo após a publicação ([Guia do Administrador da PKP](https://docs.pkp.sfu.ca/admin-guide/)).
- **`api_key_secret`** configurado, para que suas credenciais fiquem armazenadas de forma criptografada ([como defini-lo](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Ambas são configurações únicas, válidas para toda a instalação, que seu administrador de sistema pode providenciar.

### 1. Torne-se participante do OA Switchboard

Assine o Service Agreement no [site do OA Switchboard](https://www.oaswitchboard.org/) para se tornar participante e receber o userID e a senha que o plugin utiliza.

### 2. Instale o plugin

Acesse *Configurações → Website → Plugins → Galeria de plugins*, localize o **OA Switchboard Plugin**, clique em *Instalar* e habilite-o.

> [!TIP]
> Se a galeria não oferecer uma versão compatível com o seu OJS, baixe o `.tar.gz` na [página de releases](https://github.com/lepidus/OASwitchboard/releases) e use **Enviar novo plugin**.

### 3. Informe suas credenciais

Abra as *Configurações* do plugin e informe seu userID e sua senha do OA Switchboard. Eles são verificados no momento de salvar, então você descobre na hora se algo está errado.

> [!NOTE]
> Se aparecer um aviso no lugar do formulário, falta o `api_key_secret`. [Como defini-lo](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008).

### 4. Prepare sua revista

A mensagem só é enviada quando estes metadados estão presentes:

- **Revista:** ao menos um ISSN, impresso ou eletrônico.
- **Artigo:** um DOI atribuído.
- **Todos os autores:** sobrenome e afiliação.

**Recomendado:** um **ROR ID** na afiliação de ao menos um autor. É ele que permite ao OA Switchboard encaminhar a mensagem àquela instituição.

**Opcional:** com o [plugin Funding](https://github.com/ajnyga/funding/tree/stable-3_5_0) instalado, os financiadores registrados no artigo entram automaticamente na mensagem. Sem ele, as mensagens continuam sendo enviadas.

## No dia a dia

Tudo acontece na aba **OA Switchboard** da submissão.

**Antes de publicar,** a aba informa se o artigo está pronto e lista o que falta, para você ajustar os metadados antes. Você pode publicar de qualquer forma.

<img src="images/workflow-tab-requirements.png" width="700" alt="Aba OA Switchboard listando os requisitos que o artigo ainda não cumpre antes da publicação.">

**Depois de publicar,** ela mostra o que aconteceu:

| Situação | Significado |
| --- | --- |
| **Enviada** | O OA Switchboard recebeu a mensagem. |
| **Na fila** | A mensagem está a caminho. |
| **Falhou** | Algo deu errado. O motivo é exibido, junto com o botão **Tentar novamente**. |
| **Não enviada** | O artigo não cumpria os requisitos quando foi publicado. |

<img src="images/workflow-tab-sent.png" width="700" alt="Aba OA Switchboard confirmando que a mensagem P1 foi enviada com sucesso, com a data da última atualização.">

## Quais metadados são enviados?

Tudo o que é enviado já está no OJS; nada de novo é coletado.

<details>
<summary>Clique aqui para ver a lista completa</summary>

- Sobre a **Publicação**:
  - Título
  - Tipo
  - DOI
  - ID da submissão
  - Data de submissão
  - Data de aceite
  - Data de publicação
  - ID do manuscrito
  - VoR (Version of Record)
    - Tipo de publicação da revista
    - Licença
- Sobre cada **Autor**:
  - Nome
  - Sobrenome
  - ORCID
  - Posição na ordem de listagem
  - Se é autor correspondente
  - Instituição de afiliação
    - Nome
    - ROR ID
- Sobre cada **Financiador**: (quando disponível pelo plugin Funding)
  - Nome
  - Identificador
- Sobre a **Revista**:
  - Título
  - ID (pode ser ISSN ou eISSN)
  - ISSN
  - eISSN
- Momento do fluxo de trabalho em que a mensagem é enviada.

</details>

## Vídeo demonstrativo

[![Video Demo](https://img.shields.io/badge/Video%20Demo-Click%20Here-blue?logo=video)](https://vimeo.com/997938301/c62617794b)

*Gravado no OJS 3.3: os passos são os mesmos, mas a interface mudou e a aba no fluxo de trabalho é posterior ao vídeo.*

## Solução de problemas

<details>
<summary><strong>O envio falhou</strong></summary>

Normalmente credenciais expiradas ou instabilidade temporária. Informe as credenciais novamente, se for o caso, e use **Tentar novamente**. Mais detalhes ficam registrados nos logs do servidor do OJS.

</details>

<details>
<summary><strong>A aba diz que o plugin não está configurado</strong></summary>

As credenciais ainda não foram salvas, ou sua revista não tem ISSN.

</details>

<details>
<summary><strong>A mensagem ficou na fila</strong></summary>

Peça ao administrador do sistema para verificar se as tarefas em segundo plano estão em execução.

</details>

## Onde buscar ajuda

- **O plugin:** abra uma issue neste repositório.
- **Sua conta ou suas mensagens no OA Switchboard:** fale com o [OA Switchboard](https://www.oaswitchboard.org/).
- **O próprio OJS:** pergunte no [fórum da comunidade PKP](https://forum.pkp.sfu.ca/).

## Compatibilidade de versões

Cada linha de versão do OJS tem seu próprio ramo neste repositório. Este é o do **OJS 3.5.0.x**.

| Versão do plugin | Versão do OJS | Ramo |
| --- | --- | --- |
| `v3.x.x.x` | 3.5.0.x | [`main`](https://github.com/lepidus/OASwitchboard/tree/main) (este ramo) |
| `v2.x.x.x` | 3.4.0.x | [`stable-3_4_0`](https://github.com/lepidus/OASwitchboard/tree/stable-3_4_0) |
| `v1.x.x.x` | 3.3.0.x | [`stable-3_3_0`](https://github.com/lepidus/OASwitchboard/tree/stable-3_3_0) |

## Créditos

Este plugin foi desenvolvido como software livre para o [OA Switchboard](https://www.oaswitchboard.org/) pela [Lepidus Tecnologia](https://lepidus.com.br/), com a [Openjournals.nl](http://openjournals.nl/) como parceira de testes. O desenvolvimento foi viabilizado pelo financiamento da [Max Planck Digital Library (MPDL)](https://www.mpdl.mpg.de/en/).

Desenvolvido pela [Lepidus Tecnologia](https://github.com/lepidus).

## Licença

Este plugin é distribuído sob a [Licença Pública Geral GNU v3.0](/LICENSE).

Copyright (c) 2024 Lepidus Tecnologia.
Copyright (c) 2024 Stichting OA Switchboard
