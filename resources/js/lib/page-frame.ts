/** Pages that carry their own frame, outside the panel: the exact names, then the families that share a prefix. */
const STANDALONE_NAMES = new Set([
    // The survey has its own frame (SurveyShell).
    'landing',
    'campaigns/survey',
    // The climate and pulse survey of the person's emailed link: the same frame, no account.
    'engagement/survey',
    'seating/portal',
    // «Mi equipo» in the person's portal: their own link, no account.
    'organization/portal-team',
    // The policy page of the person's portal: their own link, no account.
    'disclosure/portal-policy',
    // The support message of the person's portal, when RH channeled them through it: their own link, no account.
    'results/portal-support',
    // A report opened from a temporary link: nobody signed in.
    'results/shared-report',
    // The QR screen of a work center: a fixed tablet, no account, the whole screen for the code.
    'attendance/kiosk',
    // A person links their phone from the link RH sent them: no account, their own frame.
    'attendance/enroll',
    // A person marks from their linked device: no account, their own frame.
    'attendance/punch',
    // A person sees and answers their week from their linked device: no account, the same frame.
    'attendance/week',
    // The report of a contract scenario opened from a temporary link: nobody signed in.
    'compensation/shared-report',
    // A first-time configuration has its own frame (SetupFrame): one question at a time, nothing else around it.
    'telework/applies',
    // A document written with questions has the same frame: the questions and the text beside them, nothing else.
    'telework/write',
    'disclosure/policy-write',
    'tenancy/applies',
    'tenancy/onboarding',
    // An error page stands alone: the address may not even be a page of the panel.
    'error',
]);

const STANDALONE_PREFIXES = [
    // The person's portal has its own frame (PortalShell): no account, no panel.
    'telework/portal',
    // The vacations pages of the person's portal (theirs, their team's, a statement): their own link, no account.
    'leave/portal',
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
