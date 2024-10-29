describe('Error when submission is published', function () {
    it('Access submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(2) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('#publication-button').click();
        cy.get('button:contains("Unpublish")').click();
        cy.get('.modal__content button:contains("Unpublish")').click();
        cy.get('.pkpPublication > .pkpHeader button:contains("Publish")').click();
        cy.get('.pkpFormPage__footer button:contains("Publish")').click();
    })  
})