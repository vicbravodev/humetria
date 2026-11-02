import { Link2Off } from 'lucide-react';
import StatusMessage from '@/components/humetria/status-message';

/** A shared report whose link expired or was revoked (pantallas: Enlace-Vencido): what happened and what to do. */
export default function LinkUnavailable() {
    return (
        <main className="flex flex-1 items-center justify-center px-6 pb-24">
            <StatusMessage
                icon={Link2Off}
                caption="Enlace vencido"
                title="Este enlace ya no está disponible"
            >
                Venció o quien lo compartió lo desactivó. Pídele un enlace nuevo
                a quien te lo envió.
            </StatusMessage>
        </main>
    );
}
