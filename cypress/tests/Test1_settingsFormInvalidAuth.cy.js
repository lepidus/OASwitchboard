describe('Setup OASwitchboard invalid credentials', function () {
    it('Configure the OAS API invalid credentials in the plugin settings form', function () {
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

        cy.get('input[name=OASUsername]').focus().clear();
        cy.get('input[name=OASPassword]').focus().clear();
        cy.get('form#OASwitchboardSettingsForm button:contains("Save")').click();
        cy.get('label[for^=OASUsername].error').should('contain', 'This field is required.');
        cy.get('label[for^=OASPassword].error').should('contain', 'This field is required.');

        cy.get('input[name=OASUsername]').type('username');
        cy.get('input[name=OASPassword]').type('password');
        cy.get('form#OASwitchboardSettingsForm button:contains("Save")').click();
        cy.get('form#OASwitchboardSettingsForm').should('contain', 'Authentication failed, please check the OA Switchboard API credentials.');
    })
})
