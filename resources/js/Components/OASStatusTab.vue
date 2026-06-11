<template>
    <div class="oas-status">
        <h2>{{ t('plugins.generic.OASwitchboard.workflowTab.title') }}</h2>

        <div v-if="isLoading" class="oas-status__loading">
            {{ t('common.loading') }}
        </div>

        <template v-else-if="status">
            <pkp-notification
                v-if="!status.pluginConfigured"
                type="information"
            >
                {{ t('plugins.generic.OASwitchboard.pluginIsNotConfigured') }}
            </pkp-notification>

            <template v-else>
                <pkp-notification
                    v-if="status.readyToSend"
                    :type="status.hasRor ? 'success' : 'information'"
                >
                    <p>{{ capitalize(t('plugins.generic.OASwitchboard.postRequirementsSuccess')) }}</p>
                    <p v-if="!status.hasRor">
                        <br />
                        {{ t('plugins.generic.OASwitchboard.rorRecommendation') }}
                    </p>
                </pkp-notification>

                <pkp-notification v-else type="information">
                    <p>
                        {{ capitalize(t('plugins.generic.OASwitchboard.postRequirementsError.introductionText')) }}
                    </p>
                    <ul class="oas-status__errors">
                        <li v-for="(msg, i) in status.missingFields" :key="i">{{ t(msg) }}</li>
                    </ul>
                    <p v-if="!status.hasRor">
                        {{ t('plugins.generic.OASwitchboard.rorRecommendation') }}
                    </p>
                    <p>
                        {{ t('plugins.generic.OASwitchboard.postRequirementsError.conclusionText') }}
                    </p>
                </pkp-notification>
            </template>
        </template>
    </div>
</template>

<script setup>
import {computed, watch} from 'vue';

const {useFetch} = pkp.modules.useFetch;
const {useUrl} = pkp.modules.useUrl;
const {useLocalize} = pkp.modules.useLocalize;

const {t, tk} = useLocalize();

// Keys arrive dynamically from the status API (missingFields); tk() makes
// them visible to the i18nExtractKeys build step without translating here.
tk('plugins.generic.OASwitchboard.postRequirementsError.affiliation');
tk('plugins.generic.OASwitchboard.postRequirementsError.doi');
tk('plugins.generic.OASwitchboard.postRequirementsError.familyName');
tk('plugins.generic.OASwitchboard.postRequirementsError.issn');

const props = defineProps({
    submission: {type: Object, required: true},
});

const submissionId = computed(() => props.submission?.id);

const {apiUrl} = useUrl(
    computed(() => `_submissions/${submissionId.value}/oaSwitchboardStatus`),
);

const {data: status, fetch: fetchStatus, isLoading} = useFetch(apiUrl);

function capitalize(value) {
    if (!value) return '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

watch(
    submissionId,
    (id) => {
        if (id) {
            fetchStatus();
        }
    },
    {immediate: true},
);
</script>

<style scoped>
.oas-status {
    padding: var(--spacing-4);
}
.oas-status h2 {
    margin-bottom: var(--spacing-4);
}
.oas-status__errors {
    margin: var(--spacing-2) 0 var(--spacing-2) var(--spacing-6);
    list-style: disc;
}
.oas-status__loading {
    color: var(--text-color-heading);
}
</style>
