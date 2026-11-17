import type { PageContext } from '@/lib/assistant/chat-request';

const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
const CAMPAIGN = new RegExp(`^/admin/[^/]+/campaigns/(${UUID})(?:/|$)`, 'i');
const WORK_CENTER = new RegExp(
    `^/admin/[^/]+/telework/centers/(${UUID})(?:/|$)`,
    'i',
);

/**
 * The campaign or the work center whose page the person has open, read from its address. It is only what the browser
 * says: the server checks it belongs to their company before Humi uses it.
 */
export function pageContextOf(path: string): PageContext {
    const clean = path.split(/[?#]/)[0] ?? '';
    const campaign = CAMPAIGN.exec(clean)?.[1];
    const center = WORK_CENTER.exec(clean)?.[1];

    if (campaign !== undefined) {
        return { kind: 'campaign', id: campaign };
    }

    return center === undefined ? null : { kind: 'work_center', id: center };
}

const INVITING: ReadonlyArray<[RegExp, string]> = [
    [/^\/admin\/[^/]+\/campaigns\/?$/, 'campaigns'],
    [new RegExp(`^/admin/[^/]+/campaigns/${UUID}/results/?$`, 'i'), 'results'],
    [/^\/admin\/[^/]+\/work-centers\/?$/, 'work_centers'],
    [/^\/admin\/[^/]+\/programs\/?$/, 'interventions'],
    [
        new RegExp(`^/admin/[^/]+/campaigns/${UUID}/program/?$`, 'i'),
        'interventions',
    ],
];

/**
 * The page where the closed bubble may invite, read from its address: campaigns, results, work centers or the Programas
 * de intervención; null on any other. Its message is the fact `invite.{page}` of the server.
 */
export function invitationPageOf(path: string): string | null {
    const clean = path.split(/[?#]/)[0] ?? '';

    return INVITING.find(([pattern]) => pattern.test(clean))?.[1] ?? null;
}

/** What the header says Humi is looking at. */
export function contextLabel(page: PageContext): string {
    switch (page?.kind) {
        case 'campaign':
            return 'Esta campaña';
        case 'work_center':
            return 'Este centro de trabajo';
        default:
            return 'Cumplimiento general';
    }
}
