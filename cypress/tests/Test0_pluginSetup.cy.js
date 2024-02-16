describe('Setup OASwitchboard plugin', function () {
    it('Enable the plugi in the plugins list', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').check();
        cy.get('input[id^=select-cell-oaswitchboardforojsplugin]').should('be.checked');
    });
});

