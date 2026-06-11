**Português Brasileiro** | [English](/README.md) | [Español](/docs/README-es.md)

# Plugin Open Access Switchboard

[![OJS compatibility](https://img.shields.io/badge/ojs-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/releases)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)
[![Number of downloads](https://img.shields.io/github/downloads/lepidus/OASwitchboard/total)](https://github.com/lepidus/OASwitchboard/releases)

Este plugin permite que revistas do **[OJS](https://pkp.sfu.ca/software/ojs/)** enviem automaticamente mensagens do tipo **P1-PIO** para a API do **[Open Access Switchboard](https://www.oaswitchboard.org/)** no momento da publicação de um artigo.

**Anúncio:** [OA Switchboard OJS plug-in: Supporting diamond journals to increase the visibility of their OA output among research funders, libraries, and consortia](https://www.oaswitchboard.org/ojs-plugin).


# Sumário
1. [Plugin Open Access Switchboard](#plugin-open-access-switchboard)
2. [Suporte de versões](#suporte-de-versões)
3. [Instalação do Plugin](#instalação-do-plugin)
4. [Requisitos para uso](#requisitos-para-uso)
    - [Requisitos da Revista](#requisitos-da-revista)
    - [Requisitos da Publicação](#requisitos-da-publicação)
5. [Uso](#uso)
    - [Vídeo de demonstração](#vídeo-de-demonstração)
6. [Quais campos de metadados são incluídos na mensagem?](#quais-campos-de-metadados-são-incluídos-na-mensagem)
7. [Créditos](#créditos)
8. [Licença](#licença)

## Suporte de versões

Este ramo do repositório é compatível com o OJS 3.4.0.x.

Versões compatíveis com outras versões estáveis do OJS estão disponíveis em outras branches.

- A versão `v1.x.x.x` do plugin é compatível com o OJS 3.3.0.x
- A versão `v2.x.x.x` do plugin é compatível com o OJS 3.4.0.x
- A versão `v3.x.x.x` do plugin é compatível com o OJS 3.5.0.x

Você pode encontrar a versão mais recente do plugin compatível com a sua versão do OJS na [página de Releases](https://github.com/lepidus/OASwitchboard/releases).

## Instalação do Plugin

1. Vá para *Configurações -> Website -> Plugins -> Galeria de Plugins*. Clique em **OA Switchboard Plugin** e então clique em *Instalar*.

2. Após instalar o plugin, vá até as Configurações do plugin e siga as [instruções de Uso](#uso).

## Requisitos para uso

Certifique-se de atender a estes requisitos para que a mensagem P1-PIO possa ser enviada ao OASwitchboard no momento da publicação do artigo.

### Requisitos da Revista

1. **api_key_secret**

A instância do OJS deve ter a configuração `api_key_secret` definida; você pode contatar o administrador do sistema para fazer isso (veja [este post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Isso é necessário para utilizar as credenciais de API fornecidas, que são armazenadas de forma criptografada no banco de dados do OJS.

2. **ISSN**

A Revista deve ter pelo menos um ISSN configurado, seja digital ou impresso.

### Requisitos da Publicação

* Todos os autores do artigo devem ter uma **afiliação** definida.
* A publicação deve ter um **DOI associado** a ela.
* Os autores precisam ter **sobrenome** (family name) além do nome.

Recomenda-se que pelo menos um autor do artigo tenha um **ROR ID** associado à sua afiliação (requer o plugin ROR), para que a mensagem seja enviada à afiliação associada. As instruções de uso do ROR para o OJS estão descritas no [README do plugin ROR](https://github.com/withanage/ror?tab=readme-ov-file#user-documentation).

**Informações de financiamento**: Para incluir informações de financiamento na mensagem, a revista deve estar utilizando o [plugin Funding](https://github.com/ajnyga/funding/tree/master) para fornecer essa informação sobre o artigo.

## Uso

* Em primeiro lugar, certifique-se de que todos os [requisitos para o envio adequado das mensagens P1-PIO](#requisitos-para-uso) foram atendidos.

* Após instalar o plugin, vá até as Configurações do plugin e insira suas credenciais de acesso à API do OASwitchboard.
  * Você pode precisar de credenciais diferentes para a API de *sandbox*.
* Antes de publicar o artigo, o status da submissão é exibido indicando se a mensagem será enviada com sucesso ou não; você pode ignorá-los ou editar o artigo para atender aos requisitos do plugin.
* No momento da publicação de um artigo, uma mensagem do tipo P1-PIO será enviada ao OASwitchboard via API, se todos os requisitos da publicação forem atendidos.
  * Em caso de sucesso, você verá uma notificação verde no canto superior direito da tela.

### Vídeo de demonstração

Este é um vídeo demonstrativo para guiá-lo pela instalação e uso básico do plugin.

[![Video Demo](https://img.shields.io/badge/Video%20Demo-Click%20Here-blue?logo=video)](https://vimeo.com/997938301/c62617794b)

## Quais campos de metadados são incluídos na mensagem?

Os metadados obtidos do OJS e enviados ao OA Switchboard estão listados abaixo no elemento recolhível.

<details>
<summary>Clique aqui para ver a lista</summary>

- Sobre a **Publicação**:
  - Título
  - Tipo
  - DOI
  - ID da Submissão
  - Data de submissão
  - Data de aceitação
  - Data de publicação
  - ID do Manuscrito
  - VoR (Version of Record)
    - Tipo de publicação da revista
    - Licença
- Sobre cada **Autor**:
  - Nome
  - Sobrenome
  - ORCID
  - E-mail
  - Posição na ordem de listagem
  - Se é autor correspondente
  - Instituição afiliada
    - Nome
    - ROR ID
- Sobre cada **Financiador**: (se disponível através do plugin Funding)
  - Nome
  - Identificador
- Sobre a **Revista**:
  - Título
  - ID (pode ser ISSN ou eISSN)
  - ISSN
  - eISSN
- Momento do fluxo de trabalho em que a mensagem é enviada.

</details>

## Créditos

Este plugin foi desenvolvido em código aberto para o [OA Switchboard](https://www.oaswitchboard.org/) pela [Lepidus Tecnologia](https://lepidus.com.br/) tendo a [Openjournals.nl](http://openjournals.nl/) como parceira de testes. O desenvolvimento foi viabilizado por financiamento da [Max Planck Digital Library (MPDL)](https://www.mpdl.mpg.de/en/).

Desenvolvido pela [Lepidus Tecnologia](https://github.com/lepidus).

## Licença

Este plugin está licenciado sob a [GNU General Public License v3.0](/LICENSE).

Copyright (c) 2024 Lepidus Tecnologia.  
Copyright (c) 2024 Stichting OA Switchboard
