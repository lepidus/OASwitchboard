<?php

import('plugins.generic.OASwitchboardForOJS.messages.data.P1PioData');

trait ToJson
{
    use P1PioData;

    public function getJson()
    {
        if ($this instanceof P1Pio) {
            return json_encode($this->getP1PioData());
        } else {
            throw new Exception("Error generating JSON: object is not an instance of class P1Pio.");
        }
    }
}
