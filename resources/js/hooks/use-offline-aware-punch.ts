import { useOfflineFlow } from '@/hooks/use-offline-flow';
import { forgetPerson } from '@/hooks/use-offline-store';
import { usePunch } from '@/hooks/use-punch';
import type { MarkType } from '@/lib/attendance/next-step';

/**
 * Marcar online and without a connection in one: `usePunch` for the server's way, `useOfflineFlow` for the phone's queue. With
 * the company's switch on (it comes with every signed answer, `context.offline`), a mark that could not reach the server, or
 * one tapped when the phone knows it has no network, is made without a connection. With it off, Marcar is as it was: online
 * only. «¿No es usted?» also takes what the phone kept of the person out of it (not their marks that wait).
 */
export function useOfflineAwarePunch({
    slug,
    deliverUrl,
    ticketsUrl,
    ...options
}: {
    slug: string;
    deliverUrl: string;
    ticketsUrl: string;
} & Omit<Parameters<typeof usePunch>[0], 'onOffline'>) {
    const offline = useOfflineFlow({ slug, deliverUrl, ticketsUrl });
    const enabled = offline.tickets.context?.offline === true;
    const punch = usePunch({
        ...options,
        onOffline: enabled ? offline.start : null,
    });

    return {
        ...punch,
        offline,
        /** `scanned` is the code the app's camera just read for this very mark (the state has not caught up yet). */
        mark: (type: MarkType, scanned?: string) => {
            if (enabled && !navigator.onLine) {
                offline.start(type, scanned ?? punch.code);
            } else {
                void punch.mark(type, scanned === undefined ? {} : { scanned });
            }
        },
        forget: () => {
            void forgetPerson(slug);
            void punch.forget();
        },
    };
}
