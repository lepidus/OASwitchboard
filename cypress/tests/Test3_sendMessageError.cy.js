describe('Send P1-PIO message with error', function () {
    it('Access submission and try to send Message to OA Switchboard', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Karbasizaed', 'publicknowledge', 'Published');

        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button').contains('Unpublish').click();
        cy.get('[data-cy="dialog"] button').contains('Unpublish').click();
        cy.wait(1000);

        // The submission keeps its issue assignment after unpublishing, so
        // "Schedule For Publication" opens the publish modal directly without
        // asking to pick an issue.
        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button:contains("Schedule For Publication")').click();
        cy.get('div[id^="publish-"] button:contains("Publish")').click();
    });
});
