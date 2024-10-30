# Open Access Switchboard Plugin

[![OJS compatibility](https://img.shields.io/badge/ojs-3.3.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_3_0)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)

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
6. [What metadata fields are included in the message?](#what-metadata-fields-are-included-in-the-message)
7. [Credits](#credits)
8. [License](#license)


## Version support

This branch is compatible with OJS 3.3.0.x.

A version compatible with OJS 3.4.0.x is available in the [`main`](https://github.com/lepidus/OASwitchboard/tree/main) branch.

- Plugin version `v1.x.x.x` is compatible with OJS 3.3.0.x
- Plugin version `v2.x.x.x` is compatible with OJS 3.4.0.x 

You can find the latest version of the plugin compatible with your OJS version in the [Releases page](https://github.com/lepidus/OASwitchboard/releases).

## Plugin Installation

1. To download the plugin, go to the [Releases page](https://github.com/lepidus/OASwitchboard/releases) and download the tar.gz package of the latest release compatible with your website.

2. Enter the administration area of ​​your OJS website through the *Dashboard*.

    Navigate to `Settings` > `Website` > `Plugins` > `Upload a new plugin` and select the file **`OASwitchboard.tar.gz`**.

Click Save and the plugin will be installed on your website.

## Requirements for usage

Make sure to fulfill these requirements so that the P1-PIO Message can be sent to OASwitchboard in the moment of article publication.

### Journal Requirements

1. **api_key_secret**

The OJS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that (see [this post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

This is required to use the API credentials provided, that are stored encrypted in the OJS database.

2. **ISSN**

The Journal must have at least one ISSN configured, either digital or print.

3. **DOI Plugin enabled and configured**

The DOI Plugin must be active and properly configured in the journal.

### Publication Requirements

* All authors of the article must have an **affiliation** set
* The publication must have a **DOI associated** to it.
* The authors need to have **family name** besides the given name.

It's recommended that at least one author of the article has a **ROR ID** associated with their affiliation (requires the ROR plugin), in order for the message to be sent to the associated affiliation.

**Funding information**: In order to include funding information in the message, the journal must be using the [Funding plugin](https://github.com/ajnyga/funding/tree/master)
to provide that information for the article.

## Usage

* First of all, make sure you have met all [requirements for properly sending the P1-PIO messages](#requirements-for-usage).

* After installing the plugin, go to the plugin Settings, and enter your credentials for accessing the OASwitchboard API.
  * You may need different credentials for the *sandbox* API.

* Before publishing the article, the status of the submission is displayed so that the message is sent successfully or not, you can ignore them or edit the article to meet the requirements of the plugin.

* In the moment of the publication of an article, a P1-PIO Message will be sent to OASwitchboard via API, if all publication requirements are met.
  * In success, you should see a green notification.

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

This plugin was conceived and sponsored by [OA Switchboard](https://www.oaswitchboard.org/).

Developed by [Lepidus Tecnologia](https://github.com/lepidus).

## License

This plugin is licensed under the GNU General Public License v3.0 - [See the License file.](/LICENSE)

Copyright (c) 2024 Lepidus Tecnologia.
