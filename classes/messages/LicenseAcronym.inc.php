<?php

trait LicenseAcronym
{
    private function getLicenseAcronym($licenseURL)
    {
        $licenseAcronymMap = array(
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/4.0[/]?|' => 'CC BY-NC-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/4.0[/]?|' => 'CC BY-NC',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/4.0[/]?|' => 'CC BY-NC-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/4.0[/]?|' => 'CC BY-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by/4.0[/]?|' => 'CC BY',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/4.0[/]?|' => 'CC BY-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-nd/3.0[/]?|' => 'CC BY-NC-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc/3.0[/]?|' => 'CC BY-NC',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nc-sa/3.0[/]?|' => 'CC BY-NC-SA',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-nd/3.0[/]?|' => 'CC BY-ND',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by/3.0[/]?|' => 'CC BY',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-sa/3.0[/]?|' => 'CC BY-SA',
            '|http[s]?://(www\.)?creativecommons.org/publicdomain/zero/1.0[/]?|' => 'CC0',
            '|http[s]?://(www\.)?creativecommons.org/licenses/by-other/4.0[/]?|' => 'CC BY-other',
            '|http[s]?://(www\.)?creativecommons.org/licenses/non-commercial/4.0[/]?|' => 'non-CC',
        );

        foreach ($licenseAcronymMap as $pattern => $acronym) {
            if (preg_match($pattern, $licenseURL ?? '')) {
                return $acronym;
            }
        }
        return 'not specified';
    }
}
