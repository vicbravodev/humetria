import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Whom to write to at Humetria: a mail link with its subject when a support address is configured, and the plain words
 * when it is not, so nobody gets a link that goes nowhere.
 */
export default function SupportLink({
    subject,
    children,
}: {
    subject: string;
    children: ReactNode;
}) {
    const { support } = usePage().props;

    if (support.email === null) {
        return <>{children}</>;
    }

    return (
        <a
            href={`mailto:${support.email}?subject=${encodeURIComponent(subject)}`}
            className="font-medium underline underline-offset-4"
        >
            {children}
        </a>
    );
}
