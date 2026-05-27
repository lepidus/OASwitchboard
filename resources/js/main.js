import OASStatusTab from './Components/OASStatusTab.vue';

pkp.registry.registerComponent('OASStatusTab', OASStatusTab);

const OAS_MENU_KEY = 'publication_oaswitchboard';

pkp.registry.storeExtend('workflow', (piniaContext) => {
    const workflowStore = piniaContext.store;
    const {useLocalize} = pkp.modules.useLocalize;
    const {t} = useLocalize();

    workflowStore.extender.extendFn('getMenuItems', (menuItems) => {
        const publicationMenu = menuItems.find((item) => item.key === 'publication');
        if (!publicationMenu) {
            return menuItems;
        }

        publicationMenu.items = [
            ...(publicationMenu.items || []),
            {
                key: OAS_MENU_KEY,
                label: t('plugins.generic.OASwitchboard.workflowTab.label'),
                state: {
                    primaryMenuItem: 'publication',
                    secondaryMenuItem: 'oaswitchboard',
                    title: t('plugins.generic.OASwitchboard.workflowTab.title'),
                },
            },
        ];

        return menuItems;
    });

    workflowStore.extender.extendFn('getPrimaryItems', (primaryItems, args) => {
        if (args?.selectedMenuState?.secondaryMenuItem !== 'oaswitchboard') {
            return primaryItems;
        }

        return [
            {
                component: 'OASStatusTab',
                props: {submission: args.submission},
            },
        ];
    });
});
