import { useEffect, useState } from 'react';
import { parseOpenStatements } from '@/lib/billing-statements';
import { openStatements } from '@/routes/billing/accounts';
import type { OpenStatement } from '@/types';

/** The statements of the account that still have a balance, asked once when the payment dialog opens; null while they load. */
export function useOpenStatements(accountId: string): OpenStatement[] | null {
    const [statements, setStatements] = useState<OpenStatement[] | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        async function load() {
            try {
                const response = await fetch(openStatements.url(accountId), {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const body: unknown = await response.json();
                setStatements(parseOpenStatements(body) ?? []);
            } catch {
                // Aborted when the dialog closes, or the network failed: the list stays as it was.
            }
        }

        void load();

        return () => controller.abort();
    }, [accountId]);

    return statements;
}
