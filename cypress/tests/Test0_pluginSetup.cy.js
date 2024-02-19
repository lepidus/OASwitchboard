describe('Setup OASwitchboard plugin', function () {
    it('Enable the plugin in the plugins list', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').check();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').should('be.checked');
    });
    it('Access the plugin configuration form', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardforojsplugin > .first_column > .show_extras').click();

        cy.get(
            'tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-oaswitchboardforojsplugin-control-row"] > td > :nth-child(1)'
        ).contains('Settings').click();
        cy.contains('OA Switchboard Integration Plugin for OJS');
    });
});

