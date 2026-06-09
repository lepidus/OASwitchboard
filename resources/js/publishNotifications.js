const notifyTypeByStyleClass = {
    notifySuccess: 'success',
    notifyWarning: 'warning',
    notifyError: 'warning',
};

async function showPendingTrivialNotifications() {
    const response = await fetch(
        `${pkp.context.pageBaseUrl}notification/fetchNotification`,
        {headers: {'X-Requested-With': 'XMLHttpRequest'}},
    );
    const json = await response.json();
    const notificationsByLevel = json?.content?.general;
    if (!notificationsByLevel) {
        return;
    }
    Object.values(notificationsByLevel).forEach((notificationsById) => {
        Object.values(notificationsById).forEach((notification) => {
            pkp.eventBus.$emit(
                'notify',
                notification.text,
                notifyTypeByStyleClass[notification.addclass] ?? 'notice',
            );
        });
    });
}

export function registerPublishNotifications() {
    pkp.eventBus.$on('form-success', (formId) => {
        if (formId === 'publish') {
            showPendingTrivialNotifications();
        }
    });
}
