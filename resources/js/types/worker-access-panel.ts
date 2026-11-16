import type { Paginated } from './demos';

/** The tabs of «Accesos»: everyone, with a key in use, invited (a link RH issued that still opens), and the rest. */
export type AccessView = 'all' | 'with_key' | 'invited' | 'not_invited';

/** A person's key: its name, when, and whether it is synced. Never its credential or its public key. */
export type AccessKey = {
    id: string;
    label: string;
    synced: boolean;
    /** Local times («Y-m-d H:i»). */
    registeredAt: string;
    lastUsedAt: string | null;
    revokedAt: string | null;
};

/** A person on «Accesos»: whether they have an email (their invitation goes there), where they stand, their keys. */
export type AccessPerson = {
    id: string;
    name: string;
    jobTitle: string;
    center: string;
    hasEmail: boolean;
    status: Exclude<AccessView, 'all'>;
    /** The link RH issued waiting for them: sent (and the hours it has left) or expired; null when none waits. */
    link: {
        state: 'sent' | 'expired';
        sentAt: string;
        hoursLeft: number | null;
    } | null;
    keys: AccessKey[];
};

export type WorkerAccessPageProps = {
    center: { id: string; name: string } | null;
    filters: { q: string; status: AccessView };
    counts: Record<AccessView, number>;
    people: Paginated<AccessPerson>;
    canManage: boolean;
    /** Hours a link RH issues lasts (config/worker_access.php). */
    linkHours: number;
};
