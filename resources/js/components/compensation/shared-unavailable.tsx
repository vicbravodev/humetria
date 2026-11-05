import { Link2Off } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';

/**
 * What a link that opens nothing says (board V3-Estados, enlace vencido), whatever the reason: it never says which, nor whose
 * it was.
 */
export default function SharedUnavailable() {
    return (
        <main className="flex flex-1 items-center justify-center px-6 pb-24">
            <StatusMessage
                icon={Link2Off}
                title="Este enlace ya no está disponible"
            >
                Pudo vencer, o quien lo compartió lo revocó. Pídele que te
                comparta uno nuevo.
            </StatusMessage>
        </main>
    );
}
