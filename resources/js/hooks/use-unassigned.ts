import { useEffect, useState } from 'react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { trayPage, trayUrl } from '@/lib/org-chart/tray';
import { unassigned } from '@/routes/tenant/org-chart/people';
import type { UnassignedPerson } from '@/types';

type Tray = {
    people: UnassignedPerson[];
    total: number;
    nextPage: number | null;
    status: 'loading' | 'ready' | 'error';
};

type Found = NonNullable<ReturnType<typeof trayPage>>;

const LOADING: Tray = {
    people: [],
    total: 0,
    nextPage: null,
    status: 'loading',
};

/** A page of the tray; null when the request failed, undefined when it was cancelled by a newer one. */
async function requestTray(
    url: string,
    signal?: AbortSignal,
): Promise<Found | null | undefined> {
    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            ...(signal === undefined ? {} : { signal }),
        });

        return response.ok ? trayPage(await response.json()) : null;
    } catch (error) {
        return error instanceof DOMException && error.name === 'AbortError'
            ? undefined
            : null;
    }
}

function arrived(page: number, found: Found | null) {
    return (current: Tray): Tray =>
        found === null
            ? { ...current, status: 'error' }
            : {
                  people:
                      page === 1
                          ? found.people
                          : [...current.people, ...found.people],
                  total: found.total,
                  nextPage: found.nextPage,
                  status: 'ready',
              };
}

/**
 * Whoever has no manager and leads nobody, for the tray of the editing mode: a page at a time and searched on the
 * server. It asks again when `version` changes (somebody was assigned, or an assignment was undone).
 */
export function useUnassigned({
    enabled,
    version,
}: {
    enabled: boolean;
    version: number;
}) {
    const tenantSlug = useTenantSlug();
    const [query, setQuery] = useState('');
    const [tray, setTray] = useState<Tray>(LOADING);
    const base = unassigned.url(tenantSlug);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        const wait = setTimeout(() => {
            void requestTray(trayUrl(base, query, 1), controller.signal).then(
                (found) => {
                    if (found !== undefined) {
                        setTray(arrived(1, found));
                    }
                },
            );
        }, 250);

        return () => {
            clearTimeout(wait);
            controller.abort();
        };
    }, [enabled, query, version, base]);

    return {
        ...tray,
        query,
        setQuery,
        more: () => {
            if (tray.nextPage !== null) {
                const page = tray.nextPage;

                void requestTray(trayUrl(base, query, page)).then((found) => {
                    if (found !== undefined) {
                        setTray(arrived(page, found));
                    }
                });
            }
        },
    };
}
