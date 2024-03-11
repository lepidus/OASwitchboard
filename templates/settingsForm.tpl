{**
 * templates/settingsForm.tpl
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 *}

<script>
    $(function() {ldelim}
    $('#OASwitchboardSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<div id="OASwitchboardSettings">
    <form class="pkp_form" id="OASwitchboardSettingsForm" method="post"
        action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
        {csrf}

        <div id="description">
        {if $hasCredentials}
            <p>{translate key="plugins.generic.OASwitchboard.settings.description.hasCredentials" username=$username|escape}</p>
        {else}
            <p>{translate key="plugins.generic.OASwitchboard.settings.description"}</p>
        {/if}
        </div>
        {include file="controllers/notification/inPlaceNotification.tpl" notificationId="OASwitchboardSettingsFormNotification"}

        {fbvFormArea id="authForm"}
            {fbvFormSection for="sandBoxAPI" title="plugins.generic.OASwitchboard.settings.useApiForTests" list="true"}
                {fbvElement type="checkbox" name="isSandBoxAPI" label="plugins.generic.OASwitchboard.settings.useApiForTests.option.label" id="isSandBoxAPI" checked=$isSandBoxAPI}
            {/fbvFormSection}

            {fbvFormSection label="plugins.generic.OASwitchboard.settings.username" required=true}
                {fbvElement type="text" id="OASUsername" value=$username|escape size=$fbvStyles.size.MEDIUM}
            {/fbvFormSection}

            {fbvFormSection label="plugins.generic.OASwitchboard.settings.password" required=true}
                {fbvElement type="text" password="true" id="OASPassword" value=$password|escape size=$fbvStyles.size.MEDIUM}
            {/fbvFormSection}

            {fbvFormButtons submitText="common.save"}

        {/fbvFormArea}
    </form>
</div>