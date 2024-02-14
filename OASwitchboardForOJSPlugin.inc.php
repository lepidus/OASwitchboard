<?php
/**
 * @file plugins/generic/OASwitchboardForOJS/OASwitchboardForOJSPlugin.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class OASwitchboardForOJSPlugin
 * @ingroup plugins_generic_OASwitchboardForOJS
 *
 * @brief OASwitchboardForOJS plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class OASwitchboardForOJSPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.OASwitchboardForOJS.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.OASwitchboardForOJS.description');
    }

}
