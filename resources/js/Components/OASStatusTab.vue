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
                <template v-if="sendStatus">
                    <pkp-notification :type="sendStatusNotificationType">
                        <p>{{ t(sendStatusMessageKey) }}</p>
                        <p v-if="sendStatus.error">{{ sendStatus.error }}</p>
                        <p v-if="sendStatus.updatedAt">
                            {{
                                t('plugins.generic.OASwitchboard.sendStatus.lastUpdate', {
                                    date: sendStatus.updatedAt,
                                })
                            }}
                        </p>
                    </pkp-notification>
                    <pkp-button
                        v-if="sendStatus.status === 'failed'"
                        class="oas-status__retry"
                        :is-disabled="isResending"
                        @click="resendMessage"
                    >
                        {{ t('plugins.generic.OASwitchboard.sendStatus.retry') }}
                    </pkp-button>
                </template>

                <template v-if="!sendStatus">
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
        </template>
    </div>
</template>

<script setup>
import {computed, onUnmounted, watch} from 'vue';

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

// Keys resolved dynamically from the send status returned by the API.
tk('plugins.generic.OASwitchboard.sendStatus.pending');
tk('plugins.generic.OASwitchboard.sendMessageWithSuccess');
tk('plugins.generic.OASwitchboard.sendMessageWithError');

const SEND_STATUS_POLL_INTERVAL = 5000;

const props = defineProps({
    submission: {type: Object, required: true},
});

const submissionId = computed(() => props.submission?.id);

const {apiUrl} = useUrl(
    computed(() => `_submissions/${submissionId.value}/oaSwitchboardStatus`),
);

const {data: status, fetch: fetchStatus, isLoading} = useFetch(apiUrl);

const {apiUrl: resendApiUrl} = useUrl(
    computed(() => `_submissions/${submissionId.value}/oaSwitchboardResend`),
);

const {fetch: postResend, isLoading: isResending} = useFetch(resendApiUrl, {
    method: 'POST',
});

const sendStatus = computed(() => status.value?.sendStatus);

const sendStatusMessageKey = computed(
    () =>
        ({
            pending: 'plugins.generic.OASwitchboard.sendStatus.pending',
            sent: 'plugins.generic.OASwitchboard.sendMessageWithSuccess',
            failed: 'plugins.generic.OASwitchboard.sendMessageWithError',
        })[sendStatus.value?.status] ?? '',
);

const sendStatusNotificationType = computed(
    () =>
        ({
            pending: 'information',
            sent: 'success',
            failed: 'warning',
        })[sendStatus.value?.status] ?? 'information',
);

async function resendMessage() {
    await postResend();
    await fetchStatus();
}

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

let sendStatusPollTimer = null;

function stopSendStatusPolling() {
    if (sendStatusPollTimer) {
        clearInterval(sendStatusPollTimer);
        sendStatusPollTimer = null;
    }
}

watch(
    () => sendStatus.value?.status,
    (currentStatus) => {
        if (currentStatus === 'pending') {
            sendStatusPollTimer ??= setInterval(
                fetchStatus,
                SEND_STATUS_POLL_INTERVAL,
            );
        } else {
            stopSendStatusPolling();
        }
    },
);

onUnmounted(stopSendStatusPolling);
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
.oas-status__retry {
    margin-bottom: var(--spacing-4);
}
</style>
