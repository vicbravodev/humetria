import LinkUnavailable from '@/components/link-unavailable';

/**
 * What a link that opens nothing says (board V3-Estados, enlace vencido), whatever the reason: it never says which, nor whose
 * it was.
 */
export default function SharedUnavailable() {
    return (
        <main className="flex flex-1 items-center justify-center px-6 pb-24">
            <LinkUnavailable>
                Pudo vencer, o quien lo compartió lo revocó. Pídele que te
                comparta uno nuevo.
            </LinkUnavailable>
        </main>
    );
}
