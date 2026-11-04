import { Head } from '@inertiajs/react';
import { Clock, Search } from 'lucide-react';
import PublicShell from '@/components/complaints-public/public-shell';
import AlternativeCard from '@/components/complaints-public/alternative-card';
import StatusMessage from '@/components/humetria/status-message';
import type { ExternalAlternative } from '@/types';

export default function Unavailable({
    reason,
    company,
    alternative,
}: {
    reason: 'paused' | 'invalid';
    company: string | null;
    alternative: ExternalAlternative;
}) {
    const paused = reason === 'paused';

    return (
        <PublicShell company={company}>
            <Head title={paused ? 'Canal en pausa' : 'Enlace no disponible'} />
            <div className="flex w-full max-w-2xl flex-col items-center gap-8">
                <StatusMessage
                    icon={paused ? Clock : Search}
                    title={
                        paused
                            ? 'El canal no está disponible por ahora'
                            : 'Este enlace no está disponible'
                    }
                >
                    {paused
                        ? 'La empresa pausó la recepción de quejas y denuncias. Vuelva a intentarlo más tarde o use otra vía.'
                        : 'El enlace no existe, venció o ya no funciona. Pida a la empresa el enlace o el cartel vigente, o use otra vía.'}
                </StatusMessage>
                <AlternativeCard alternative={alternative} />
            </div>
        </PublicShell>
    );
}
