<?php

import('plugins.generic.OASwitchboardForOJS.messages.json.ToJson');

class P1Pio
{
    use ToJson;

    public function getMessage()
    {
        return $this->getJson();
    }
}
