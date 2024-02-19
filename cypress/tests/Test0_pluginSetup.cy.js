describe('Setup OASwitchboard plugin', function () {
    it('Enable the plugin in the plugins list', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').check();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').should('be.checked');
    })
    it('Access the plugin configuration form', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();

        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardforojsplugin';

        cy.get('tr#' + pluginRowId + ' a.show_extras').click();
        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.get('form#OASwitchboardForOJSForm').should('be.visible')
        
        cy.contains('OA Switchboard Integration Plugin for OJS');
        cy.contains('Please, enter your Open Access Switchboard credentials below, to allow the plugin to access the API.')
        
        cy.get('input[name=OASUsername]').should('be.visible');
        cy.get('input[name=OASPassword]').should('be.visible');
    });
});

