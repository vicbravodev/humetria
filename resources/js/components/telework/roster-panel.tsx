import { CalendarCheck, UserCheck } from 'lucide-react';
import EndTeleworkDialog from '@/components/telework/end-telework-dialog';
import JoinRosterDialog from '@/components/telework/join-roster-dialog';
import RosterEnded from '@/components/telework/roster-ended';
import { describeDate } from '@/lib/calendar-date';
import { rosterHint } from '@/lib/telework/roster';
import type { TeleworkerSummary } from '@/types';

/** Where the person stands in the roster: the way in (an aspirant), the way out (in the roster) or how it ended (pantalla: Eval-Cerrada). */
export default function RosterPanel({
    tenantSlug,
    teleworker,
    states,
}: {
    tenantSlug: string;
    teleworker: TeleworkerSummary;
    states: Record<string, string>;
}) {
    if (teleworker.status === 'ended') {
        return <RosterEnded teleworker={teleworker} />;
    }

    const aspirant = teleworker.status === 'aspirant';
    const Icon = aspirant ? UserCheck : CalendarCheck;

    return (
        <section
            className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-5"
            aria-labelledby="roster-title"
        >
            <div className="flex items-center gap-3">
                <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-primary">
                    <Icon aria-hidden className="size-5" />
                </span>
                <div>
                    <h2 id="roster-title" className="text-base font-semibold">
                        {aspirant
                            ? 'Aún no está en el padrón'
                            : 'En el padrón de teletrabajo'}
                    </h2>
                    <p className="text-[15px] text-muted-foreground">
                        {rosterHint(
                            teleworker.status,
                            teleworker.canJoin,
                            describeDate(teleworker.startedOn ?? ''),
                        )}
                    </p>
                </div>
            </div>
            <div className="flex flex-wrap gap-2">
                {aspirant && (
                    <JoinRosterDialog
                        tenantSlug={tenantSlug}
                        teleworker={teleworker}
                        states={states}
                    />
                )}
                <EndTeleworkDialog
                    tenantSlug={tenantSlug}
                    teleworker={teleworker}
                />
            </div>
        </section>
    );
}
