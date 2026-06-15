<template>
    <div class="oas-status">
        <h2>{{ t('plugins.generic.OASwitchboard.workflowTab.title') }}</h2>

        <pkp-spinner v-if="isLoading" :message="t('common.loading')" />

        <template v-else-if="status">
            <pkp-notification v-if="!status.pluginConfigured" type="information">
                <div class="oas-status__row">
                    <pkp-icon class="oas-status__icon oas-status__icon--information" icon="Help" />
                    <p class="oas-status__headline">
                        {{ t('plugins.generic.OASwitchboard.pluginIsNotConfigured') }}
                    </p>
                </div>
            </pkp-notification>

            <template v-else>
                <template v-if="sendStatus">
                    <pkp-notification :type="sendStatusNotificationType">
                        <div class="oas-status__row">
                            <pkp-icon
                                :class="['oas-status__icon', `oas-status__icon--${sendStatusNotificationType}`]"
                                :icon="sendStatusIcon"
                            />
                            <div class="oas-status__body">
                                <p class="oas-status__headline">{{ t(sendStatusMessageKey) }}</p>
                                <p v-if="sendStatus.error" class="oas-status__detail">
                                    {{ sendStatus.error }}
                                </p>
                                <p v-if="sendStatus.updatedAt" class="oas-status__meta">
                                    {{
                                        t('plugins.generic.OASwitchboard.sendStatus.lastUpdate', {
                                            date: sendStatus.updatedAt,
                                        })
                                    }}
                                </p>
                            </div>
                        </div>
                    </pkp-notification>

                    <pkp-button-row
                        v-if="sendStatus.status === 'failed'"
                        class="oas-status__actions"
                    >
                        <pkp-button
                            is-warnable
                            :is-disabled="isResending"
                            @click="resendMessage"
                        >
                            {{ t('plugins.generic.OASwitchboard.sendStatus.retry') }}
                        </pkp-button>
                    </pkp-button-row>
                </template>

                <template v-else>
                    <pkp-notification
                        v-if="status.readyToSend"
                        :type="status.hasRor ? 'success' : 'information'"
                    >
                        <div class="oas-status__row">
                            <pkp-icon
                                :class="[
                                    'oas-status__icon',
                                    status.hasRor
                                        ? 'oas-status__icon--success'
                                        : 'oas-status__icon--information',
                                ]"
                                :icon="status.hasRor ? 'Complete' : 'Help'"
                            />
                            <div class="oas-status__body">
                                <p class="oas-status__headline">
                                    {{ capitalize(t('plugins.generic.OASwitchboard.postRequirementsSuccess')) }}
                                </p>
                                <p v-if="!status.hasRor" class="oas-status__detail">
                                    {{ t('plugins.generic.OASwitchboard.rorRecommendation') }}
                                </p>
                            </div>
                        </div>
                    </pkp-notification>

                    <pkp-notification v-else type="information">
                        <div class="oas-status__row">
                            <pkp-icon class="oas-status__icon oas-status__icon--information" icon="Help" />
                            <div class="oas-status__body">
                                <p class="oas-status__headline">
                                    {{ capitalize(t('plugins.generic.OASwitchboard.postRequirementsError.introductionText')) }}
                                </p>
                                <ul class="oas-status__errors">
                                    <li v-for="(msg, i) in status.missingFields" :key="i">{{ t(msg) }}</li>
                                </ul>
                                <p v-if="!status.hasRor" class="oas-status__detail">
                                    {{ t('plugins.generic.OASwitchboard.rorRecommendation') }}
                                </p>
                                <p class="oas-status__detail">
                                    {{ t('plugins.generic.OASwitchboard.postRequirementsError.conclusionText') }}
                                </p>
                            </div>
                        </div>
                    </pkp-notification>
                </template>
            </template>
        </template>
    </div>
</template>

<script setup>
import {computed, onMounted, onUnmounted, watch} from 'vue';

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

const sendStatusIcon = computed(
    () =>
        ({
            pending: 'Clock',
            sent: 'Complete',
            failed: 'Error',
        })[sendStatus.value?.status] ?? 'Clock',
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

// Quando o modal de publish fecha com sucesso, re-busca o status para refletir
// o envio sem precisar de refresh manual. O envio é assíncrono, então a busca
// pega o estado "pending" e o poll-while-pending acima assume até liquidar.
function handlePublishFormSuccess(formId, publication) {
    if (formId !== 'publish') {
        return;
    }
    if (publication?.submissionId && publication.submissionId !== submissionId.value) {
        return;
    }
    fetchStatus();
}

onMounted(() => pkp.eventBus.$on('form-success', handlePublishFormSuccess));
onUnmounted(() => pkp.eventBus.$off('form-success', handlePublishFormSuccess));
</script>

<style scoped>
.oas-status {
    padding: var(--spacing-4);
}
.oas-status h2 {
    margin-bottom: var(--spacing-4);
}
.oas-status__row {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-2);
}
.oas-status__icon {
    flex-shrink: 0;
    margin-top: 0.1rem;
}
.oas-status__icon :deep(svg) {
    width: 1.25rem;
    height: 1.25rem;
}
.oas-status__icon--information {
    color: var(--color-primary);
}
.oas-status__icon--success {
    color: var(--color-success);
}
.oas-status__icon--warning {
    color: var(--color-negative);
}
.oas-status__body {
    flex: 1;
}
.oas-status__headline {
    font-weight: 600;
    color: var(--text-color-heading);
}
.oas-status__detail {
    margin-top: var(--spacing-1);
}
.oas-status__meta {
    margin-top: var(--spacing-1);
    color: var(--text-color-secondary);
}
.oas-status__errors {
    margin: var(--spacing-2) 0 var(--spacing-2) var(--spacing-6);
    list-style: disc;
}
.oas-status__actions {
    margin-top: var(--spacing-3);
}
</style>
