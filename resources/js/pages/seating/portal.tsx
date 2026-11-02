import { Head } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import PortalAcknowledgment from '@/components/seating/portal-acknowledgment';
import PortalInputForm from '@/components/seating/portal-input-form';
import PortalSeatReport from '@/components/seating/portal-seat-report';
import PortalShell from '@/components/seating/portal-shell';
import type { PortalAcknowledgment as Pending, PortalCondition } from '@/types';

export default function SeatingPortal({
    token,
    company,
    name,
    notice,
    footwear,
    discomfort,
    sentOn,
    acknowledgment,
    acknowledgedOn,
}: {
    token: string;
    company: string;
    name: string;
    notice: string;
    footwear: PortalCondition;
    discomfort: PortalCondition;
    /** The date of the person's last answer, if they ever sent one. */
    sentOn: string | null;
    /** The assessment waiting for the person's confirmation, if any. */
    acknowledgment: Pending | null;
    /** When the person confirmed their current assessment, if they did. */
    acknowledgedOn: string | null;
}) {
    return (
        <PortalShell company={company}>
            <Head title="Su trabajo de pie" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Su trabajo de pie, {name}
                    </h1>
                    <p className="text-muted-foreground">
                        Cuéntenos cómo es su calzado y cómo se siente al
                        trabajar de pie. Con esto la Comisión de Seguridad e
                        Higiene decide qué asiento y qué descansos le
                        corresponden.
                    </p>
                </div>
                {acknowledgment !== null && (
                    <PortalAcknowledgment
                        token={token}
                        pending={acknowledgment}
                    />
                )}
                {acknowledgedOn !== null && (
                    <p className="flex items-center gap-2 rounded-xl bg-accent px-4 py-3 text-base text-accent-foreground">
                        <CircleCheck aria-hidden className="size-5 shrink-0" />
                        Usted confirmó el {acknowledgedOn} que le informaron del
                        resultado de su evaluación.
                    </p>
                )}
                {sentOn !== null && (
                    <p className="flex items-center gap-2 rounded-xl bg-accent px-4 py-3 text-base text-accent-foreground">
                        <CircleCheck aria-hidden className="size-5 shrink-0" />
                        Nos contestó el {sentOn}. Si algo cambió, puede volver a
                        contestar.
                    </p>
                )}
                <PortalInputForm
                    token={token}
                    notice={notice}
                    footwear={footwear}
                    discomfort={discomfort}
                />
                <PortalSeatReport token={token} />
            </div>
        </PortalShell>
    );
}
