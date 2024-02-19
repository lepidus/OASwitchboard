<?php

import('lib.pkp.classes.form.Form');

class OASwitchboardForOJSForm extends Form
{
    private $plugin;
    private $contextId;

    public function __construct($plugin, $contextId)
    {
        $this->plugin = $plugin;
        $this->contextId = $contextId;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
    }

    public function fetch($request, $template = null, $display = false)
    {
        return parent::fetch($request);
    }

    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
    }
}
