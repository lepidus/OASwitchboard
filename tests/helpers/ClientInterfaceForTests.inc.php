<?php

class ClientInterfaceForTests
{
    public function request($method, $uri, array $options = [])
    {
        return "This Request must be mocked";
    }
}
