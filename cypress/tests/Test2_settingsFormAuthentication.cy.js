describe('Setup OASwitchboard credentials', function () {
    it('Configure the OAS API credentials in the plugin settings form', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();

        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardplugin';

        cy.get('tr#' + pluginRowId + ' a.show_extras').click();
        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.contains('OA Switchboard Plugin');
        
        cy.contains('Please, enter your Open Access Switchboard credentials below, to allow the plugin to access the API.');
        cy.get('input[name=OASUsername]').should('be.visible');
        cy.get('input[name=OASPassword]').should('be.visible');

        cy.contains('Use API for tests');
        cy.contains('Use sandbox API for plugin testing purposes.');
        cy.get('#isSandBoxAPI').click();

        cy.get('input[name=OASUsername]').type(Cypress.env('OASUsername'), {force: true});
        cy.get('input[name=OASPassword]').type(Cypress.env('OASPassword'));
        cy.get('form#OASwitchboardSettingsForm button:contains("Save")').click();
        cy.get('form#OASwitchboardSettingsForm').should('not.be.visible');
        cy.contains('Your changes have been saved.');

        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.contains('OA Switchboard Plugin');
        cy.get('form#OASwitchboardSettingsForm').contains('The API credentials are ready to use! Currently using credentials for: ' + Cypress.env('OASUsername') + '.');
        cy.get('form#OASwitchboardSettingsForm').contains('You can edit the credentials below, or click the Cancel button.');
    })
})
