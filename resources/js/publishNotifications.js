const SEND_STATUS_POLL_INTERVAL = 2000;
const SEND_STATUS_POLL_LIMIT = 15;

const {t} = pkp.modules.useLocalize.useLocalize();

function notify(text, type) {
    pkp.eventBus.$emit('notify', text, type);
}

function sleep(milliseconds) {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

async function fetchSendStatus(submissionId) {
    const response = await fetch(
        `${pkp.context.apiBaseUrl}_submissions/${submissionId}/oaSwitchboardStatus`,
        {headers: {'X-Requested-With': 'XMLHttpRequest'}},
    );
    if (!response.ok) {
        return null;
    }
    const json = await response.json();
    return json?.sendStatus ?? null;
}

function notifySendResult(sendStatus) {
    if (sendStatus.status === 'sent') {
        notify(
            t('plugins.generic.OASwitchboard.sendMessageWithSuccess'),
            'success',
        );
    } else {
        notify(
            t('plugins.generic.OASwitchboard.sendMessageWithError'),
            'warning',
        );
    }
}

async function watchSendStatusUntilSettled(submissionId) {
    const sendStatus = await fetchSendStatus(submissionId);
    if (!sendStatus) {
        return;
    }

    if (sendStatus.status !== 'pending') {
        notifySendResult(sendStatus);
        return;
    }

    notify(t('plugins.generic.OASwitchboard.sendScheduled'), 'notice');

    for (let attempt = 0; attempt < SEND_STATUS_POLL_LIMIT; attempt++) {
        await sleep(SEND_STATUS_POLL_INTERVAL);
        const polledStatus = await fetchSendStatus(submissionId);
        if (polledStatus && polledStatus.status !== 'pending') {
            notifySendResult(polledStatus);
            return;
        }
    }
}

export function registerPublishNotifications() {
    pkp.eventBus.$on('form-success', (formId, publication) => {
        if (formId !== 'publish' || !publication?.submissionId) {
            return;
        }
        watchSendStatusUntilSettled(publication.submissionId);
    });
}
