describe('Send P1-PIO message with success', function () {
    it('Configure DOI', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.checkDoiConfig(['publication', 'issue', 'representation']);
        cy.get('a:contains("DOIs")').click();
        cy.assignDoisByTitle('Antimicrobial, heavy metal resistance and plasmid profile of coliforms isolated from nosocomial infections in a hospital in Isfahan, Iran');
    })

    it('Install Funding Plugin', function () {
        cy.login('admin', 'admin', 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.get('#plugins-button').click();
        cy.get('#pluginGallery-button').click();
        cy.get('span').contains('Funding').click();
        cy.get('[id^=pluginGallery-installPlugin-button-]').click();
        cy.get('.ok').click();
    }) 

    it('Enable Funding Plugin', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.contains('a', 'Website').click();
        cy.get('#plugins-button').click();
        cy.get('input[id^=select-cell-FundingPlugin]').check();
        cy.get('input[id^=select-cell-FundingPlugin]').should('be.checked');
    })

    it('Send Message to OA Switchboard', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(2) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('#publication-button').click();
        cy.get('button:contains("Unpublish")').click();
        cy.get('.pkpButton--isPrimary').contains("Unpublish").click();
        cy.get('#fundingGridInWorkflow-button').click();
        cy.get('[id^=component-plugins-generic-funding-controllers-grid-fundergrid-addFunder-button-]').click();
        cy.wait(1000);
        cy.get('input.ui-widget-content.ui-autocomplete-input').should('be.visible').first().focus().type("Universidade Federal de Santa Catarina [http://dx.doi.org/10.13039/501100007082]", {delay: 0});
        cy.wait(1000);
        cy.get('#funderForm > .formButtons > [id^=submitFormButton-]').click();

        cy.get('.pkpPublication__header > .pkpHeader__actions > button.pkpButton').contains("Schedule For Publication").click();
        cy.get('.pkpFormPage__footer button:contains("Publish")').click();

        cy.get('.app__notifications').contains("At least one author of the article must have a ROR associated with their affiliation.");
        cy.get('.app__notifications').contains("The message was successfully sent to the OA Switchboard");
    })

    it('Check the message on sandbox and validate funding', function () {
        cy.visit('https://sandboxhub.oaswitchboard.org/');
        cy.get('#\\31').type(Cypress.env('OASUsername'));
        cy.get('#\\32 ').type(Cypress.env('OASPassword'));
        cy.get('.sc-kGXeez').click();
        cy.get('tbody > :nth-child(1) > :nth-child(2)').click();
        cy.get('.modal-content').contains("funders:");
        cy.get('.modal-content').contains("name: Universidade Federal de Santa Catarina");
        cy.get('.modal-content').contains("fundref: http://dx.doi.org/10.13039/501100007082");
    })
})