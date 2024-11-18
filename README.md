# Open Access Switchboard Plugin

[![OJS compatibility](https://img.shields.io/badge/ojs-3.4.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_4_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/releases)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)
[![Number of downloads](https://img.shields.io/github/downloads/lepidus/OASwitchboard/total)](https://github.com/lepidus/OASwitchboard/releases)

This plugin enables **[OJS](https://pkp.sfu.ca/software/ojs/)** journals to automatically send **P1-PIO** type messages to the **[Open Access Switchboard](https://www.oaswitchboard.org/)** API at the moment of article publication.

The list of metadata fields included in the message can be found [here](#what-metadata-fields-are-included-in-the-message).

# Table of Contents
1. [Open Access Switchboard Plugin](#open-access-switchboard-plugin)
2. [Version support](#version-support)
3. [Plugin Installation](#plugin-installation)
4. [Requirements for usage](#requirements-for-usage)
    - [Journal Requirements](#journal-requirements)
    - [Publication Requirements](#publication-requirements)
5. [Usage](#usage)
    - [Demonstration video](#demonstration-video)
6. [What metadata fields are included in the message?](#what-metadata-fields-are-included-in-the-message)
7. [Credits](#credits)
8. [License](#license)

## Version support

The `main` branch of this repository is compatible with OJS 3.4.0.x.

A version compatible with OJS 3.3.0.x is available in the [`stable-3_3_0`](https://github.com/lepidus/OASwitchboard/tree/stable-3_3_0) branch.

- Plugin version `v1.x.x.x` is compatible with OJS 3.3.0.x
- Plugin version `v2.x.x.x` is compatible with OJS 3.4.0.x

You can find the latest version of the plugin compatible with your OJS version in the [Releases page](https://github.com/lepidus/OASwitchboard/releases).

## Plugin Installation

1. Go to *Settings -> Website -> Plugins -> Plugin Gallery*. Click on **OA Switchboard Plugin** and then click on *Install*.

2. After installing the plugin, go to the plugin Settings, and follow the [Usage instructions](#usage).

## Requirements for usage

Make sure to fulfill these requirements so that the P1-PIO Message can be sent to OASwitchboard at the moment of article publication.

### Journal Requirements

1. **api_key_secret**

The OJS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that (see [this post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

This is required to use the API credentials provided, that are stored encrypted in the OJS database.

2. **ISSN**

The Journal must have at least one ISSN configured, either digital or print.

### Publication Requirements

* All authors of the article must have an **affiliation** set.
* The publication must have a **DOI associated** to it.
* The authors need to have **family name** besides the given name.

It's recommended that at least one author of the article has a **ROR ID** associated with their affiliation (requires the ROR plugin), in order for the message to be sent to the associated affiliation. The ROR usage instructions for OJS are described in the [plugin's README](https://github.com/withanage/ror?tab=readme-ov-file#user-documentation).

**Funding information**: In order to include funding information in the message, the journal must be using the [Funding plugin](https://github.com/ajnyga/funding/tree/master)
to provide that information for the article.

## Usage

* First of all, make sure you have met all [requirements for properly sending the P1-PIO messages](#requirements-for-usage).

* After installing the plugin, go to the plugin Settings, and enter your credentials for accessing the OASwitchboard API.
  * You may need different credentials for the *sandbox* API.
* Before publishing the article, the status of the submission is displayed so that the message is sent successfully or not, you can ignore them or edit the article to meet the requirements of the plugin.
* In the moment of the publication of an article, a P1-PIO type Message will be sent to OASwitchboard via API, if all publication requirements are met.
  * Upon success, you should see a green notification on the top-right corner of the screen.

### Demonstration video

This is a demonstration video to guide you through the installation and basic usage of the plugin.

[![Video Demo](https://img.shields.io/badge/Video%20Demo-Click%20Here-blue?logo=video)](https://vimeo.com/997938301/c62617794b)

## What metadata fields are included in the message?

The metadata retrieved from OJS and sent to OA Switchboard is listed below in the collapsible element.

<details>
<summary>Click here to see the list </summary>

- About the **Publication**:
  - Title
  - Type
  - DOI
  - Submission ID
  - Submission date
  - Acceptance date
  - Publication date
  - Manuscript ID
  - VoR (Version of Record)
    - Type of journal publication
    - License
- About each **Author**:
  - Given name
  - Family name
  - ORCID
  - Email
  - Position in listing order
  - Is corresponding author
  - Affiliated institution
    - Name
    - ROR ID
- About each **Funder**: (if available with Funding plugin)
  - Name
  - Identifier
- About the **Journal**:
  - Title
  - ID (can be ISSN or eISSN)
  - ISSN
  - eISSN
- Timing in the workflow that the message is sent.

</details>

## Credits

This plugin was developed to [OA Switchboard](https://www.oaswitchboard.org/).

Developed by [Lepidus Tecnologia](https://github.com/lepidus).

## License

This plugin is licensed under the GNU General Public License v3.0 - [See the License file.](/LICENSE)

Copyright (c) 2024 Lepidus Tecnologia.
Copyright (c) 2024 Stichting OA Switchboard
