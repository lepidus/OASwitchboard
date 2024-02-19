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

        cy.get('input[name=OASUsername]').should('be.visible');
        cy.get('input[name=OASPassword]').should('be.visible');

        cy.get('input[name=OASUsername]').focus().clear();
        cy.get('input[name=OASPassword]').focus().clear();

        cy.get('form#OASwitchboardForOJSForm button:contains("Save")').click();
        cy.get('label[for^=OASUsername].error').should('contain', 'This field is required.');
        cy.get('label[for^=OASPassword].error').should('contain', 'This field is required.');

        cy.get('input[name=OASUsername]').type('username');
        cy.get('input[name=OASPassword]').type('password');
        cy.get('form#OASwitchboardForOJSForm button:contains("Save")').click();
        cy.get('form#OASwitchboardForOJSForm').should('not.be.visible')
    })
})
