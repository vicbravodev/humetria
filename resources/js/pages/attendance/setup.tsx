import { Head, setLayoutProps } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import SetupSteps from '@/components/attendance/setup-steps';
import PageHeader from '@/components/humetria/page-header';
import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deadlineText, setupProgress } from '@/lib/attendance/setup';
import { index } from '@/routes/tenant/attendance';
import type { AttendanceSetupProps } from '@/types';

/**
 * The road to the first mark (board A1 of the Asistencia redesign): the four things a company does before its people can
 * mark, with how far each is and the date the registro is mandatory (1 January 2027, LFT art. 132-XXXIV).
 */
export default function AttendanceSetup({ setup }: AttendanceSetupProps) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Asistencia', href: index(tenantSlug) },
            { title: 'Puesta en marcha' },
        ],
    });
    const { done, total } = setupProgress(setup);
    const ready = done === total;

    return (
        <>
            <Head title="Puesta en marcha · Asistencia" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Asistencia"
                    title="Puesta en marcha"
                    status={
                        ready ? (
                            <Badge variant="success">Todo listo</Badge>
                        ) : (
                            <Badge variant="warning">
                                Obligatorio desde el 1 de enero de 2027
                            </Badge>
                        )
                    }
                    description={
                        ready
                            ? 'Tus centros, horarios, teléfonos y política están listos: tus personas ya pueden marcar.'
                            : 'Una persona marca cuando su centro tiene zona y pantalla QR, tiene horario y vinculó su teléfono.'
                    }
                    facts={[
                        {
                            icon: CalendarClock,
                            text: deadlineText(setup.daysLeft),
                        },
                    ]}
                />
                <section
                    data-tour="attendance-setup"
                    aria-labelledby="avance"
                    className="flex flex-col gap-3"
                >
                    <h2 id="avance" className="text-xl leading-7 font-semibold">
                        {ready
                            ? 'Todo listo para que marquen'
                            : 'Falta poco para que todos puedan marcar'}
                    </h2>
                    <ProgressBar
                        value={done}
                        max={total}
                        label="Pasos de la puesta en marcha"
                    />
                    <p className="text-sm text-muted-foreground">
                        {`${done} de ${total} pasos listos.`}
                    </p>
                </section>
                <SetupSteps setup={setup} />
            </div>
        </>
    );
}
