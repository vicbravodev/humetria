/** Pages that carry their own frame, outside the panel: the exact names, then the families that share a prefix. */
const STANDALONE_NAMES = new Set([
    // The survey has its own frame (SurveyShell).
    'landing',
    'campaigns/survey',
    'seating/portal',
    // The policy page of the person's portal: their own link, no account.
    'disclosure/portal-policy',
    // A report opened from a temporary link: nobody signed in.
    'results/shared-report',
    // The report of a contract scenario opened from a temporary link: nobody signed in.
    'compensation/shared-report',
    // A first-time configuration has its own frame (SetupFrame): one question at a time, nothing else around it.
    'telework/applies',
    // A document written with questions has the same frame: the questions and the text beside them, nothing else.
    'telework/write',
    'tenancy/applies',
    'tenancy/onboarding',
    // An error page stands alone: the address may not even be a page of the panel.
    'error',
]);

const STANDALONE_PREFIXES = [
    // The person's portal has its own frame (PortalShell): no account, no panel.
    'telework/portal',
    // The channel for complaints is public and keeps no trace of who came: no panel, no account.
    'complaints-public/',
    // The permanent page of the workers, opened with a link: no account, no panel.
    'disclosure-public/',
];

/** Whether a page renders without the panel around it. */
export function isStandalone(name: string): boolean {
    return (
        STANDALONE_NAMES.has(name) ||
        STANDALONE_PREFIXES.some((prefix) => name.startsWith(prefix))
    );
}
