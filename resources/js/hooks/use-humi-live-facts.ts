import { useEffect, useState } from 'react';
import { readLiveFacts } from '@/lib/assistant/api';

const NONE: Readonly<Record<string, string>> = {};

/**
 * The live facts of the company (the server keeps them a minute), read once for `slug`; none while they come, when they
 * fail, or with no `slug` (nothing to ask for).
 */
export function useHumiLiveFacts(
    slug: string | null,
): Readonly<Record<string, string>> {
    const [facts, setFacts] = useState(NONE);

    useEffect(() => {
        if (slug === null) {
            return;
        }

        let current = true;

        void readLiveFacts(slug).then((read) => {
            if (current) {
                setFacts(read);
            }
        });

        return () => {
            current = false;
        };
    }, [slug]);

    return slug === null ? NONE : facts;
}
