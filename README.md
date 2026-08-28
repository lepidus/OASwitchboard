**English** | [Português Brasileiro](/docs/README-pt_BR.md) | [Español](/docs/README-es.md)

# Open Access Switchboard Plugin

[![OJS compatibility](https://img.shields.io/badge/ojs-3.5.0.x-brightgreen)](https://github.com/pkp/ojs/tree/stable-3_5_0)
[![GitHub release](https://img.shields.io/github/v/release/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/releases)
[![License type](https://img.shields.io/github/license/lepidus/OASwitchboard)](https://github.com/lepidus/OASwitchboard/blob/main/LICENSE)
[![Number of downloads](https://img.shields.io/github/downloads/lepidus/OASwitchboard/total)](https://github.com/lepidus/OASwitchboard/releases)

This plugin connects journals running [OJS](https://pkp.sfu.ca/software/ojs/) to the [OA Switchboard](https://www.oaswitchboard.org/), the shared infrastructure that exchanges scholarly communications metadata among publishers, institutions and funders. When an article is published, its [publication metadata](#what-metadata-is-included) is extracted automatically and pushed to the relevant institutions and research funders as a standardised P1-PIO message.

**Announcement:** [OA Switchboard OJS plug-in: Supporting diamond journals to increase the visibility of their OA output among research funders, libraries, and consortia](https://www.oaswitchboard.org/ojs-plugin).

## How it works

When an article is published, the plugin checks whether it carries the metadata the OA Switchboard needs. If it does, the message is built and delivered at that moment. If it does not, the article is published as usual, simply without a message.

Either way, the **OA Switchboard** tab in the submission workflow shows where things stand: what is still missing before publication, and what was sent after it.

## Getting started

### What your OJS installation needs

Two standard OJS settings, normally already in place:

- **Background jobs** running, so messages are delivered right after publication ([PKP Administrator's Guide](https://docs.pkp.sfu.ca/admin-guide/)).
- **`api_key_secret`** configured, so your credentials are stored encrypted ([how to set one](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

Both are one-time, installation-wide settings your system administrator can set up.

### 1. Join the OA Switchboard

Join the OA Switchboard as a participant by signing the Service Agreement, and you will be provided with a *userID* and *password* to use the plugin. To find out more, get in touch with the [OA Switchboard](https://www.oaswitchboard.org/).

### 2. Install the plugin

Go to *Settings → Website → Plugins → Plugin Gallery*, find **OA Switchboard Plugin**, click *Install*, and enable it.

> [!TIP]
> If the gallery has no release for your OJS version, download the `.tar.gz` from the [Releases page](https://github.com/lepidus/OASwitchboard/releases) and use **Upload a new plugin**.

### 3. Add your credentials

Open the plugin's *Settings* and enter your OA Switchboard userID and password. They are checked when you save, so you know right away if something is wrong.

> [!NOTE]
> If a warning appears instead of the form, the `api_key_secret` is missing. [How to set one](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008).

### 4. Get your journal ready

A message is sent only when this metadata is in place:

- **Journal:** at least one ISSN, print or digital.
- **Article:** an assigned DOI.
- **Every author:** family name and affiliation.

**Recommended:** a **ROR ID** on at least one author's affiliation. It is what lets the OA Switchboard route the message to that institution.

**Optional:** with the [Funding plugin](https://github.com/ajnyga/funding/tree/stable-3_5_0) installed, funders recorded for an article are included automatically.

## Day-to-day use

Everything happens in the **OA Switchboard** tab of a submission.

**Before publishing,** the tab tells you whether the article is ready, and lists anything missing so you can fix the metadata first. You can publish either way.

<img src="docs/images/workflow-tab-requirements.png" width="700" alt="OA Switchboard tab listing the requirements an article still does not meet before publication.">

**After publishing,** it shows what happened:

| Status | Meaning |
| --- | --- |
| **Sent** | The OA Switchboard received the message. |
| **Queued** | The message is on its way. |
| **Failed** | Something went wrong. The reason is shown, with a **Try again** button. |
| **Not sent** | The article did not meet the requirements when it was published. |

<img src="docs/images/workflow-tab-sent.png" width="700" alt="OA Switchboard tab confirming the P1 message was successfully sent, with the date of the last update.">

## What metadata is included?

Everything sent is metadata already in OJS; nothing new is collected.

<details>
<summary>Click here to see the full list</summary>

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

## Demonstration video

[![Video Demo](https://img.shields.io/badge/Video%20Demo-Click%20Here-blue?logo=video)](https://vimeo.com/997938301/c62617794b)

*Recorded on OJS 3.3: the steps are the same, but the interface has changed and the workflow tab is newer than the video.*

## Troubleshooting

<details>
<summary><strong>The send failed</strong></summary>

Usually expired credentials or a temporary outage. Re-enter your credentials if needed, then use **Try again**. Further detail is recorded in your OJS server logs.

</details>

<details>
<summary><strong>The tab says the plugin is not configured</strong></summary>

The credentials have not been saved yet, or your journal has no ISSN.

</details>

<details>
<summary><strong>A message stayed queued</strong></summary>

Ask your system administrator to check that background jobs are running.

</details>

## Getting help

- **The plugin:** open an issue in this repository.
- **Your OA Switchboard account or messages:** contact the [OA Switchboard](https://www.oaswitchboard.org/).
- **OJS itself:** ask on the [PKP Community Forum](https://forum.pkp.sfu.ca/).

## Version support

Each OJS release line has its own branch of this repository. This one targets **OJS 3.5.0.x**.

| Plugin version | OJS version | Branch |
| --- | --- | --- |
| `v3.x.x.x` | 3.5.0.x | [`main`](https://github.com/lepidus/OASwitchboard/tree/main) (this branch) |
| `v2.x.x.x` | 3.4.0.x | [`stable-3_4_0`](https://github.com/lepidus/OASwitchboard/tree/stable-3_4_0) |
| `v1.x.x.x` | 3.3.0.x | [`stable-3_3_0`](https://github.com/lepidus/OASwitchboard/tree/stable-3_3_0) |

## Credits

This plugin was developed as open source software for the [OA Switchboard](https://www.oaswitchboard.org/) by [Lepidus Tecnologia](https://lepidus.com.br/), with [Openjournals.nl](http://openjournals.nl/) as testing partner. The development was made possible by funding from the [Max Planck Digital Library (MPDL)](https://www.mpdl.mpg.de/en/).

Developed by [Lepidus Tecnologia](https://github.com/lepidus).

## License

This plugin is licensed under the [GNU General Public License v3.0](/LICENSE).

Copyright (c) 2024 Lepidus Tecnologia.
Copyright (c) 2024 Stichting OA Switchboard
