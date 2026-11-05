import { Link2Off } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** What a link that opens nothing says, whatever the reason: it never says which, nor whose it was. */
export default function SharedUnavailable() {
    return (
        <main className="mx-auto flex w-full max-w-xl flex-1 items-center px-4 pb-16">
            <EmptyState
                icon={Link2Off}
                title="Este enlace ya no está disponible"
            >
                Pudo vencer, o quien lo compartió lo revocó. Pídele que te
                comparta uno nuevo.
            </EmptyState>
        </main>
    );
}
