describe('Send P1-PIO message with error', function () {
    it('Access submission and try to send Message to OA Switchboard', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Karbasizaed', 'publicknowledge', 'Archived');

        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button').contains('Unpublish').click();
        cy.get('[data-cy="dialog"] button').contains('Unpublish').click();
        cy.wait(1000);

        cy.publish('1', 'Vol. 1 No. 2 (2014)');
    });
});
