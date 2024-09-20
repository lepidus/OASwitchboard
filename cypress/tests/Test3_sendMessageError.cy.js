describe('Send P1-PIO message with error', function () {
    it('Access submission and try to send Message to OA Switchboard', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(2) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('#publication-button').click();
        cy.get('button:contains("Unpublish")').click();
        cy.get('.pkpButton--isPrimary').contains("Unpublish").click();
        cy.get('.pkpPublication__header > .pkpHeader__actions > button.pkpButton').contains("Schedule For Publication").click();
        cy.get('.pkpFormPage__footer button:contains("Publish")').click();

        cy.get('.app__notifications').contains("We don't have the mandatory data for sending the message to the OA Switchboard, please check the submission activity log to understand the pending requirements.");
        cy.get('.app__notifications').contains("The article must have a DOI associated.");
    })
})
