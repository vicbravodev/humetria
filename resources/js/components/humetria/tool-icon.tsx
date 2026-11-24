import { cn } from '@/lib/utils';

/** The tools of Humetría, as the demo form and the public pages name them. */
export type ToolIconName =
    | 'nom_035'
    | 'nom_037'
    | 'ley_silla'
    | 'attendance'
    | 'complaints'
    | 'engagement'
    | 'leave'
    | 'org_chart'
    | 'compensation';

/**
 * Humetría's own icon for each tool: one family of 24 px line drawings (1.75 stroke, round ends) so a tool is recognised
 * by its mark wherever it is named. Each path draws what the tool is about, not a generic symbol.
 */
export const PATHS: Record<ToolIconName, string[]> = {
    // A head with a pulse inside: what people feel at work (psychosocial risk).
    nom_035: [
        'M9.5 21v-3.2A7 7 0 1 1 18 11l1.6 2.8a.6.6 0 0 1-.5.9H18v2.1a2 2 0 0 1-2 2h-1.5V21',
        'M6.5 10.5h1.8l1.2-2.3 1.8 4.6 1.2-2.3h1.8',
    ],
    // A laptop under a roof: work done from home (telework).
    nom_037: ['M3.5 11 12 4l8.5 7', 'M7.5 10.5h9v6h-9z', 'M5.5 19.5h13'],
    // A chair with its backrest: a seat to rest on (Ley Silla).
    ley_silla: [
        'M8 3.5v11',
        'M8 9.5h6.5',
        'M8 14.5h8.5a1 1 0 0 1 1 1V16',
        'M8.5 14.5 7 20.5',
        'M16.5 14.5l1.5 6',
    ],
    // A clock with a check: the hours, registered and confirmed.
    attendance: [
        'M18.4 10.4A7.5 7.5 0 1 0 11 18.5',
        'M11 6.5V11l2.8 1.8',
        'M14.5 18.5l2 2 4-4',
    ],
    // A speech bubble with a lock: a complaint that stays safe.
    complaints: [
        'M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4A1.5 1.5 0 0 1 4 14.5z',
        'M9.5 9.5h5v3.5h-5z',
        'M10.5 9.5V8.5a1.5 1.5 0 0 1 3 0v1',
    ],
    // A gauge: how the climate of the team reads today.
    engagement: [
        'M4 17a8 8 0 1 1 16 0',
        'M12 17l3.5-4.5',
        'M6.6 11.6l1.1 1.1',
        'M12 9v1.5',
        'M17.4 11.6l-1.1 1.1',
    ],
    // A calendar with a sun: days off.
    leave: [
        'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z',
        'M8 3v4',
        'M16 3v4',
        'M4 10h16',
        'M12 13.2a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6z',
    ],
    // Three people joined by their lines: who reports to whom.
    org_chart: [
        'M9.5 3.5h5v4h-5z',
        'M3.5 16.5h5v4h-5z',
        'M15.5 16.5h5v4h-5z',
        'M12 7.5v4.5',
        'M6 16.5v-4.5h12v4.5',
    ],
    // Coins with an arrow up: what a raise costs.
    compensation: [
        'M3.5 8c0 1.1 2.7 2 6 2s6-.9 6-2-2.7-2-6-2-6 .9-6 2z',
        'M3.5 8v4c0 1.1 2.7 2 6 2',
        'M3.5 12v4c0 1.1 2.7 2 6 2s6-.9 6-2v-1',
        'M15.5 11.5l5-5',
        'M17 6.5h3.5V10',
    ],
};

export default function ToolIcon({
    tool,
    className,
}: {
    tool: ToolIconName;
    className?: string;
}) {
    return (
        <svg
            aria-hidden
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={cn('size-6 shrink-0', className)}
        >
            {PATHS[tool].map((path) => (
                <path key={path} d={path} />
            ))}
        </svg>
    );
}
