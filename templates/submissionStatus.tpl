<h2>{translate key="plugins.generic.OASwitchboard.workflowTab.title"}</h2>

{if $pluginIsNotConfiguredInfo}
    {$pluginIsNotConfiguredInfo}
{else}
    {if $submissionIsAlreadySendMessage}
        {$submissionIsAlreadySendMessage}
    {/if}
    
    {if $submissionRequirementsIsPending}
        {$submissionRequirementsIsPending}
    {/if}
{/if}