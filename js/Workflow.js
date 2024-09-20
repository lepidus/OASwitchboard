(function () {
    if (typeof pkp === 'undefined' || typeof pkp.eventBus === 'undefined') {
        return;
    }

    $.pkp.plugins.generic.oaswitchboardplugin.showNotification = function (responseObject) {
        const { content } = responseObject;
        if (!content?.general) {
            return;
        }

        const notificationsData = content.general;

        Object.entries(notificationsData).forEach(([levelId, notifications]) => {
            Object.values(notifications).forEach(({ addclass, text }) => {
                let type = 'notice';

                switch (addclass) {
                    case 'notifySuccess':
                        type = 'success';
                        break;
                    case 'notifyWarning':
                    case 'notifyError':
                    case 'notifyFormError':
                    case 'notifyForbidden':
                        type = 'warning';
                        break;
                }

                pkp.eventBus.$emit('notify', text, type);
            });
        });
    }

    pkp.eventBus.$on('form-success', (formId, data) => {
        $.ajax({
            type: 'POST',
            url: $.pkp.plugins.generic.oaswitchboardplugin.notificationUrl,
            success: $.pkp.plugins.generic.oaswitchboardplugin.showNotification,
            dataType: 'json',
            async: false
        });
    });
}());