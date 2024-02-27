<?php

import('plugins.generic.OASwitchboardForOJS.classes.messages.P1PioDataFormat');

class P1Pio
{
    use P1PioDataFormat;

    public function getContent(): array
    {
        return $this->getSampleP1Pio();
    }
}
