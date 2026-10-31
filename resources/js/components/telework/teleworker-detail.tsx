import { usePage } from '@inertiajs/react';
import { Briefcase, Home, Percent } from 'lucide-react';
import AddWorkplaceDialog from '@/components/telework/add-workplace-dialog';
import PortalLinkPanel from '@/components/telework/portal-link-panel';
import RosterPanel from '@/components/telework/roster-panel';
import WorkplaceStatusBadge from '@/components/telework/workplace-status-badge';
import { expiryText } from '@/lib/telework/roster';
import type { TeleworkerSummary } from '@/types';

const STATUS: Record<TeleworkerSummary['status'], string> = {
    aspirant: 'Aspirante',
    active: 'En el padrón',
    ended: 'Terminó',
};

/** A person who will telework: their workplaces with where each checklist stands, and their portal link (pantalla: Eval-Cerrada). */
export default function TeleworkerDetail({
    tenantSlug,
    teleworker,
    states,
}: {
    tenantSlug: string;
    teleworker: TeleworkerSummary;
    states: Record<string, string>;
}) {
    const newLink = usePage().flash.portalLink;

    return (
        <div className="flex flex-col gap-6">
            <ul className="flex flex-wrap gap-x-5 gap-y-1 text-[15px] leading-[22px] text-muted-foreground">
                <li className="flex items-center gap-1.5">
                    <Briefcase aria-hidden className="size-4" />
                    {teleworker.jobTitle}
                </li>
                <li className="flex items-center gap-1.5">
                    <Percent aria-hidden className="size-4" />
                    {teleworker.percentage} % en teletrabajo
                </li>
                <li>{STATUS[teleworker.status]}</li>
            </ul>
            <RosterPanel
                tenantSlug={tenantSlug}
                teleworker={teleworker}
                states={states}
            />
            <PortalLinkPanel
                tenantSlug={tenantSlug}
                teleworkerId={teleworker.id}
                hasEmail={teleworker.hasEmail}
                newLink={newLink}
            />
            <section className="grid gap-3" aria-labelledby="workplaces-title">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 id="workplaces-title" className="text-xl font-semibold">
                        Lugares de trabajo
                    </h2>
                    <AddWorkplaceDialog
                        tenantSlug={tenantSlug}
                        teleworkerId={teleworker.id}
                        states={states}
                    />
                </div>
                <ul className="grid gap-3">
                    {teleworker.workplaces.map((workplace) => (
                        <li
                            key={workplace.id}
                            className="flex items-start gap-3.5 rounded-xl border bg-card p-4"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                                <Home aria-hidden className="size-5" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-base font-semibold">
                                    {workplace.address}
                                </p>
                                <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[15px] text-muted-foreground">
                                    <WorkplaceStatusBadge
                                        status={workplace.status}
                                        label={workplace.statusLabel}
                                    />
                                    {expiryText(
                                        workplace.status,
                                        workplace.expiresOn,
                                    )}
                                </p>
                                <p className="text-[15px] text-muted-foreground">
                                    Lista: {workplace.label}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}
