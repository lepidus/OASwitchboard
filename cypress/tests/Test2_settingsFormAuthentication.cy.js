describe('Setup OASwitchboard credentials', function () {
    it('Configure the OAS API credentials in the plugin settings form', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();

        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardforojsplugin';

        cy.get('tr#' + pluginRowId + ' a.show_extras').click();
        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.contains('OA Switchboard Integration Plugin for OJS');
        
        cy.contains('Please, enter your Open Access Switchboard credentials below, to allow the plugin to access the API.');
        cy.get('input[name=OASUsername]').should('be.visible');
        cy.get('input[name=OASPassword]').should('be.visible');

        cy.get('input[name=OASUsername]').type(Cypress.env('OASUsername'), {force: true});
        cy.get('input[name=OASPassword]').type(Cypress.env('OASPassword'));
        cy.get('form#OASwitchboardForOJSSettingsForm button:contains("Save")').click();
        cy.get('form#OASwitchboardForOJSSettingsForm').should('not.be.visible');
        cy.contains('Your changes have been saved.');

        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.contains('OA Switchboard Integration Plugin for OJS');
        cy.get('form#OASwitchboardForOJSSettingsForm').contains('The API credentials are ready to use! Currently using credentials for: ' + Cypress.env('OASUsername') + '.');
        cy.get('form#OASwitchboardForOJSSettingsForm').contains('You can edit the credentials below, or click the Cancel button.');
    })
})
