import { Armchair } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { equipmentTone } from '@/lib/telework/equipment';
import type { EquipmentRow } from '@/types';

/** One item delivered to the person, with the badge of their answer and the reason if they refused it. */
export default function EquipmentItemRow({ item }: { item: EquipmentRow }) {
    return (
        <li className="flex items-start gap-3.5 rounded-xl border bg-card p-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <Armchair aria-hidden className="size-5" />
            </span>
            <div className="min-w-0">
                <p className="text-base font-semibold">{item.description}</p>
                <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[15px] text-muted-foreground">
                    <Badge variant={equipmentTone(item.status)}>
                        {item.statusLabel}
                    </Badge>
                    {item.kind} · entregado el{' '}
                    {describeDate(item.deliveredOn) ?? item.deliveredOn}
                </p>
                {item.refusalReason !== null && (
                    <p className="mt-1 text-[15px]">
                        Motivo: {item.refusalReason}
                    </p>
                )}
            </div>
        </li>
    );
}
