import { Armchair } from 'lucide-react';
import EquipmentDialog from '@/components/telework/equipment-dialog';
import EquipmentItemRow from '@/components/telework/equipment-item-row';
import type { TeleworkerSummary } from '@/types';

/** The equipment delivered to the person with where each item stands (numerals 5.7 and 5.1 k; criterio C-22). */
export default function EquipmentSection({
    tenantSlug,
    teleworker,
}: {
    tenantSlug: string;
    teleworker: TeleworkerSummary;
}) {
    const ended = teleworker.status === 'ended';

    return (
        <section className="grid gap-3" aria-labelledby="equipment-title">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="equipment-title" className="text-xl font-semibold">
                    Equipo entregado
                </h2>
                {!ended && (
                    <EquipmentDialog
                        tenantSlug={tenantSlug}
                        teleworkerId={teleworker.id}
                        today={teleworker.today}
                    />
                )}
            </div>
            {teleworker.equipment.length === 0 ? (
                <p className="flex items-center gap-3 rounded-xl border bg-card p-4 text-[15px] text-muted-foreground">
                    <Armchair aria-hidden className="size-5 shrink-0" />
                    {teleworker.noEquipmentReason ??
                        'Aún no hay equipo registrado para esta persona.'}
                </p>
            ) : (
                <ul className="grid gap-3">
                    {teleworker.equipment.map((item) => (
                        <EquipmentItemRow key={item.id} item={item} />
                    ))}
                </ul>
            )}
        </section>
    );
}
