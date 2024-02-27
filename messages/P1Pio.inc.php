<?php

import('plugins.generic.OASwitchboardForOJS.messages.data.P1PioDataFormat');

class P1Pio
{
    use P1PioDataFormat;

    public function getMessage(): array
    {
        return $this->getFilledP1Pio();
    }
}
