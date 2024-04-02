# Open Access Switchboard Plugin

This plugin enables **[OJS](https://pkp.sfu.ca/software/ojs/)** journals to automatically send **P1-PIO** type messages to the **[Open Access Switchboard](https://www.oaswitchboard.org/)** API at the moment of article publication.

> The current version of this plugin sends P1-PIO messages with only the mandatory data about the article.
For that reason, it may not be ready for a comprehensive general use.

## Compatibility

The latest release of this plugin is compatible with **OJS 3.3.0**

## Requirements

Make sure to fulfill these requirements so that the P1-PIO Message can be sent to OASwitchboard in the moment of article publication.

### Journal Requirements

1. **api_key_secret**

The OJS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that (see [this post](https://forum.pkp.sfu.ca/t/how-to-generate-a-api-key-secret-code-in-ojs-3/72008)).

This is required to use the API credentials provided, that are stored encrypted in the OJS database.

2. **ISSN**

The Journal must have at least one ISSN configured, either digital or print.

3. **ROR Plugin enabled**

The ROR Plugin must be installed and active in the journal. It can be installed from the plugin gallery.

4. **DOI Plugin enabled and configured**

The DOI Plugin must be active and properly configured in the journal.

### Publication Requirements

* All authors of the article must have an **affiliation** set
* The publication must have a **DOI associated** to it.
* The authors need to have **family name** besides the given name.

It's recommended that at least one author of the article has a **ROR ID** associated with their affiliation (requires the ROR plugin), in order for the message to be sent to the associated affiliation.

## Plugin Installation

1. To download the plugin, go to the [Releases page](https://github.com/lepidus/OASwitchboard/releases) and download the tar.gz package of the latest release compatible with your website.

2. Enter the administration area of ​​your OJS website through the *Dashboard*.

    Navigate to `Settings` > `Website` > `Plugins` > `Upload a new plugin` and select the file **`OASwitchboard.tar.gz`**.

Click Save and the plugin will be installed on your website.

## Usage

* First of all, make sure you have met all [requirements for properly sending the P1-PIO messages](#requirements).

* After installing the plugin, go to the plugin Settings, and enter your credentials for accessing the OASwitchboard API.
  * You may need different credentials for the *sandbox* API.

* In the moment of the publication of an article, a P1-PIO Message will be sent to OASwitchboard via API, if all publication requirements are met.
  * In success, you should see a green notification on screen reload.
  * If any problems block the message from being sent, such as publication requirements, you should see a red notification detailing the problem, and the information is persisted in the '*Activity Log*' of the publication.

## Credits

This plugin was conceived and sponsored by [OA Switchboard](https://www.oaswitchboard.org/).

Developed by [Lepidus Tecnologia](https://github.com/lepidus).

## License

This plugin is licensed under the GNU General Public License v3.0 - [See the License file.](/LICENSE)

Copyright (c) 2024 Lepidus Tecnologia.
