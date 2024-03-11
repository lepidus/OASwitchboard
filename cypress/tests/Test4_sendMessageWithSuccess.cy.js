describe('Error when submission is published', function () {
    it('Enable DOI Plugin', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-doipubidplugin]').check();
        cy.get('input[id^=select-cell-doipubidplugin]').should('be.checked');
        const pluginRowId = 'component-grid-settings-plugins-settingsplugingrid-category-pubIds-row-doipubidplugin';

        cy.get('tr#' + pluginRowId + ' a.show_extras').click();
        cy.get('a[id^=' + pluginRowId + '-settings-button]').click();

        cy.get('#enableIssueDoi').click();
        cy.get('#enablePublicationDoi').click();
        cy.get('#enableRepresentationDoi').click();
        cy.get('input[name=doiPrefix]').type("10.6666");
        cy.get('#doiSettingsForm button:contains("Save")').click();
    })

    it('Enable ROR Plugin', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.waitJQuery();
        cy.get('#plugins-button').click();
        cy.get('#pluginGallery-button').click();
        cy.get('span').contains('Research Organization Registry(ROR) Plugin').click();
        cy.get('[id^=pluginGallery-installPlugin-button-]').click();
        cy.get('.ok').click();
    }) 
})