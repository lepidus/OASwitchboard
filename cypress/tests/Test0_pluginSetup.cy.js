describe('Setup OASwitchboard plugin', function () {
    it('Enable the plugin in the plugins list', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-oaswitchboardplugin]').check();
        cy.get('input[id^=select-cell-oaswitchboardplugin]').should('be.checked');
    })
    it('Access the plugin configuration form', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();

        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardplugin';

        cy.get('tr#' + pluginRowId + ' a.show_extras').click();
        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.get('form#OASwitchboardSettingsForm').should('be.visible')
        
        cy.contains('OA Switchboard Plugin');
        cy.contains('Please, enter your Open Access Switchboard credentials below, to allow the plugin to access the API.')
        
        cy.get('input[name=OASUsername]').should('be.visible');
        cy.get('input[name=OASPassword]').should('be.visible');
    });
});

