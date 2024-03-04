# Open Access Switchboard For OJS

This plugin integrates OJS to the [Open Access Switchboard](https://www.oaswitchboard.org/) API.

It enables journals to automatically send **P1-PIO** type messages to OASwitchboard, in the moment of the publication of articles, containing public information.

## Compatibility

The latest release of this plugin is compatible with **OJS 3.3.0**

## Journal Requirements

Make sure to fulfill these requirements in your journal so that the P1-PIO Message can be sent to OASwitchboard in the moment of publication.

1. **api_key_secret**

The OJS instance must have the `api_key_secret` configuration set up, you may contact your system administrator to do that.
This is required to use the API credentials provided, that are stored encrypted in the OJS database.

2. **ROR Plugin enabled**

* The ROR Plugin must be installed and active in the journal.

* In a submission, all authors must have an Affiliation, and the first author must specify the ROR ID.

3. **DOI Plugin enabled and configured**

* The DOI Plugin must be enabled and properly configured in the journal.

* The publication must have a DOI associated to it.

4. **ISSN**

* The Journal must have at least one ISSN configured, either digital or print.


## Plugin Installation

1. To download the plugin, go to the [Releases page](https://github.com/lepidus/OASwitchboardForOJS/releases) and download the tar.gz package of the latest release compatible with your website.

2. Enter the administration area of ​​your OJS website through the *Dashboard*.

    Navigate to `Settings` > `Website` > `Plugins` > `Upload a new plugin` and select the file **`OASwitchboardForOJS.tar.gz`**.

Click Save and the plugin will be installed on your website.

## Usage

* First of all, make sure you have met all [requirements for properly sending P1-PIO messages from your journal](#journal-requirements).

* After installing the plugin, go to the plugin Settings, and enter your credentials for accessing the OASwitchboard API.

* In the moment of the publication of an article, a P1-PIO Message will be sent via the OASwitchboard API, using the access credentials.

## Credits

Developed by Lepidus Tecnologia.

## License

This plugin is licensed under the GNU General Public License v3.0 - [See the License file.](/LICENSE)

*Copyright (c) 2024 Lepidus Tecnologia*