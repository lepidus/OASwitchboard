describe('Send P1-PIO message with success', function () {
    it('Configure DOI', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.checkDoiConfig(['publication', 'issue', 'representation']);
        cy.get('a:contains("DOIs")').click();
        cy.assignDoisByTitle('Antimicrobial, heavy metal resistance and plasmid profile of coliforms isolated from nosocomial infections in a hospital in Isfahan, Iran');
    });

    // TODO: Install Funding plugin once a package is available via plugin gallery
    // (funders are optional in the P1 message and the fixture submission has no funder data. its non-blocking).

    it('Send Message to OA Switchboard', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Karbasizaed', 'publicknowledge', 'Published');

        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button').contains('Unpublish').click();
        cy.get('[data-cy="dialog"] button').contains('Unpublish').click();
        cy.wait(1000);

        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button:contains("Schedule For Publication")').click();
        cy.get('div[id^="publish-"] button:contains("Publish")').click();
        cy.wait(1000);
        cy.contains('The P1 message was successfully sent to the OA Switchboard.');
    });
});
