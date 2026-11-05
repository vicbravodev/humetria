import { Head } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import PublicShell from '@/components/complaints-public/public-shell';
import AlternativeCard from '@/components/complaints-public/alternative-card';
import StatusMessage from '@/components/humetria/status-message';
import LinkUnavailable from '@/components/link-unavailable';
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
                {paused ? (
                    <StatusMessage
                        icon={Clock}
                        title="El canal no está disponible por ahora"
                    >
                        La empresa pausó la recepción de quejas y denuncias.
                        Vuelva a intentarlo más tarde o use otra vía.
                    </StatusMessage>
                ) : (
                    <LinkUnavailable>
                        Pida a la empresa el enlace o el cartel vigente, o use
                        otra vía.
                    </LinkUnavailable>
                )}
                <AlternativeCard alternative={alternative} />
            </div>
        </PublicShell>
    );
}
