/** Pages that carry their own frame, outside the panel: the exact names, then the families that share a prefix. */
const STANDALONE_NAMES = new Set([
    // The survey has its own frame (SurveyShell).
    'landing',
    'campaigns/survey',
    'seating/portal',
    // A report opened from a temporary link: nobody signed in.
    'results/shared-report',
    // An error page stands alone: the address may not even be a page of the panel.
    'error',
]);

const STANDALONE_PREFIXES = [
    // The person's portal has its own frame (PortalShell): no account, no panel.
    'telework/portal',
    // The channel for complaints is public and keeps no trace of who came: no panel, no account.
    'complaints-public/',
];

/** Whether a page renders without the panel around it. */
export function isStandalone(name: string): boolean {
    return (
        STANDALONE_NAMES.has(name) ||
        STANDALONE_PREFIXES.some((prefix) => name.startsWith(prefix))
    );
}
