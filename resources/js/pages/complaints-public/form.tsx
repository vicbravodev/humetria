import { Head } from '@inertiajs/react';
import AnonymityLimits from '@/components/complaints-public/anonymity-limits';
import HowItWorks from '@/components/complaints-public/how-it-works';
import PublicShell from '@/components/complaints-public/public-shell';
import type { ExternalAlternative } from '@/types';

export default function Form({
    company,
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
}: {
    company: string;
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
}) {
    return (
        <PublicShell company={company}>
            <Head title="Quejas y denuncias" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Quejas y denuncias
                    </h1>
                    <p className="text-muted-foreground">
                        Aquí puede avisar, de forma anónima o confidencial, de
                        una práctica que afecta su entorno de trabajo, de un
                        caso de violencia laboral o de un acontecimiento
                        traumático severo.
                    </p>
                </div>
                <HowItWorks
                    procedure={procedure}
                    acknowledgeWithinDays={acknowledgeWithinDays}
                    resolveWithinDays={resolveWithinDays}
                />
                <AnonymityLimits />
            </div>
        </PublicShell>
    );
}
