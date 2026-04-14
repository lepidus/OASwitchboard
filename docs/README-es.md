**Español** | [English](/README.md) | [Português Brasileiro](/docs/README-pt_BR.md)

# Módulo Open Access Switchboard

[![OJS compatibility](https://img.shields.io/badge/ojs-3.3.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_3_0)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)

Este módulo permite que las revistas de **[OJS](https://pkp.sfu.ca/software/ojs/)** envíen automáticamente mensajes de tipo **P1-PIO** a la API de **[Open Access Switchboard](https://www.oaswitchboard.org/)** en el momento de la publicación del artículo.

**Anuncio:** [OA Switchboard OJS plug-in: Supporting diamond journals to increase the visibility of their OA output among research funders, libraries, and consortia](https://www.oaswitchboard.org/ojs-módulo).

# Tabla de Contenidos
1. [Módulo Open Access Switchboard](#módulo-open-access-switchboard)
2. [Soporte de versiones](#soporte-de-versiones)
3. [Instalación del Módulo](#instalación-del-módulo)
4. [Requisitos de uso](#requisitos-de-uso)
    - [Requisitos de la Revista](#requisitos-de-la-revista)
    - [Requisitos de la Publicación](#requisitos-de-la-publicación)
5. [Uso](#uso)
6. [¿Qué campos de metadatos se incluyen en el mensaje?](#qué-campos-de-metadatos-se-incluyen-en-el-mensaje)
7. [Créditos](#créditos)
8. [Licencia](#licencia)


## Soporte de versiones

Esta rama es compatible con OJS 3.3.0.x.

Una versión compatible con OJS 3.4.0.x está disponible en la rama [`main`](https://github.com/lepidus/OASwitchboard/tree/main).

- La versión `v1.x.x.x` del módulo es compatible con OJS 3.3.0.x
- La versión `v2.x.x.x` del módulo es compatible con OJS 3.4.0.x

Puede encontrar la versión más reciente del módulo compatible con su versión de OJS en la [página de Releases](https://github.com/lepidus/OASwitchboard/releases).

## Instalación del Módulo

1. Vaya a *Ajustes -> Sitio web -> Módulos -> Galería de módulos*. Haga clic en **OA Switchboard Módulo** y luego en *Instalar*.

2. Después de instalar el módulo, vaya a la configuración del módulo y siga las [instrucciones de Uso](#uso).

## Requisitos de uso

Asegúrese de cumplir con estos requisitos para que el mensaje P1-PIO pueda enviarse a OASwitchboard en el momento de la publicación del artículo.

### Requisitos de la Revista

1. **api_key_secret**

La instancia de OJS debe tener configurado el parámetro `api_key_secret`; puede contactar al administrador del sistema para hacerlo (vea [esta publicación](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Esto es necesario para utilizar las credenciales de la API proporcionadas, que se almacenan cifradas en la base de datos de OJS.

2. **ISSN**

La revista debe tener al menos un ISSN configurado, ya sea digital o impreso.

3. **Módulo DOI habilitado y configurado**

El módulo DOI debe estar activo y correctamente configurado en la revista.

### Requisitos de la Publicación

* Todos los autores del artículo deben tener una **afiliación** definida
* La publicación debe tener un **DOI asociado**.
* Los autores deben tener un **apellido** (family name) además del nombre.

Se recomienda que al menos un autor del artículo tenga un **ROR ID** asociado a su afiliación (requiere el módulo ROR), para que el mensaje sea enviado a la afiliación asociada. Las instrucciones de uso de ROR para OJS están descritas en el [README del módulo ROR](https://github.com/withanage/ror?tab=readme-ov-file#user-documentation).

**Información de financiación**: Para incluir información de financiación en el mensaje, la revista debe estar utilizando el [módulo Funding](https://github.com/ajnyga/funding/tree/master) para proporcionar esa información sobre el artículo.

## Uso

* Ante todo, asegúrese de haber cumplido todos los [requisitos para el envío correcto de los mensajes P1-PIO](#requisitos-de-uso).

* Después de instalar el módulo, vaya a la configuración del módulo e ingrese sus credenciales de acceso a la API de OASwitchboard.
  * Es posible que necesite credenciales diferentes para la API de *sandbox*.

* Antes de publicar el artículo, se muestra el estado del envío indicando si el mensaje será enviado con éxito o no; puede ignorarlos o editar el artículo para cumplir con los requisitos del módulo.

* En el momento de la publicación de un artículo, se enviará un mensaje P1-PIO a OASwitchboard vía API, si se cumplen todos los requisitos de la publicación.
  * En caso de éxito, verá una notificación verde.

## ¿Qué campos de metadatos se incluyen en el mensaje?

Los metadatos obtenidos de OJS y enviados a OA Switchboard se listan a continuación en el elemento desplegable.

<details>
<summary>Haga clic aquí para ver la lista</summary>

- Sobre la **Publicación**:
  - Título
  - Tipo
  - DOI
  - ID del Envío
  - Fecha de envío
  - Fecha de aceptación
  - Fecha de publicación
  - ID del Manuscrito
  - VoR (Version of Record)
    - Tipo de publicación de la revista
    - Licencia
- Sobre cada **Autor**:
  - Nombre
  - Apellido
  - ORCID
  - Correo electrónico
  - Posición en el orden de listado
  - Si es autor correspondiente
  - Institución afiliada
    - Nombre
    - ROR ID
- Sobre cada **Financiador**: (si está disponible a través del módulo Funding)
  - Nombre
  - Identificador
- Sobre la **Revista**:
  - Título
  - ID (puede ser ISSN o eISSN)
  - ISSN
  - eISSN
- Momento del flujo de trabajo en que se envía el mensaje.

</details>

## Créditos

Este módulo fue desarrollado en código abierto para [OA Switchboard](https://www.oaswitchboard.org/) por [Lepidus Tecnologia](https://lepidus.com.br/) con [Openjournals.nl](http://openjournals.nl/) como socio de pruebas. El desarrollo ha sido posible gracias a la financiación de la [Max Planck Digital Library (MPDL)](https://www.mpdl.mpg.de/en/).

Desarrollado por [Lepidus Tecnologia](https://github.com/lepidus).

## Licencia

Este módulo está licenciado bajo la [GNU General Public License v3.0](/LICENSE).

Copyright (c) 2024 Lepidus Tecnologia.  
Copyright (c) 2024 Stichting OA Switchboard
