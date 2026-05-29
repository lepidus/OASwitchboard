describe('Send P1-PIO message with success', function () {
    it('Configure DOI', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.checkDoiConfig(['publication', 'issue', 'representation']);
        cy.get('a:contains("DOIs")').click();
        cy.assignDoisByTitle('Antimicrobial, heavy metal resistance and plasmid profile of coliforms isolated from nosocomial infections in a hospital in Isfahan, Iran');
    });

    it('Install Funding Plugin', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.get('nav').contains('Settings').click();
        cy.get('nav').contains('Website').click({force: true});
        cy.get('#plugins-button').click();
        cy.get('#pluginGallery-button').click();
        cy.get('span').contains('Funding').click();
        cy.get('[id^=pluginGallery-installPlugin-button-]').click();
        cy.get('.ok').click();
    });

    it('Enable Funding Plugin', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('nav').contains('Settings').click();
        cy.get('nav').contains('Website').click({force: true});
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-FundingPlugin]').check();
        cy.get('input[id^=select-cell-FundingPlugin]').should('be.checked');
    });

    it('Send Message to OA Switchboard', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Karbasizaed', 'publicknowledge', 'Archived');

        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button').contains('Unpublish').click();
        cy.get('[data-cy="dialog"] button').contains('Unpublish').click();
        cy.wait(1000);

        cy.publish('1', 'Vol. 1 No. 2 (2014)');

        cy.contains('The P1 message was successfully sent to the OA Switchboard.');
    });
});
