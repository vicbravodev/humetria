import { Armchair } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { equipmentTone } from '@/lib/telework/equipment';
import type { EquipmentRow } from '@/types';

/** One item delivered to the person, with the badge of their answer and the reason if they refused it. */
export default function EquipmentItemRow({ item }: { item: EquipmentRow }) {
    return (
        <li className="flex items-start gap-3.5 card p-4">
            <IconTile icon={Armchair} />
            <div className="min-w-0">
                <p className="text-base font-semibold">{item.description}</p>
                <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-meta text-muted-foreground">
                    <Badge variant={equipmentTone(item.status)}>
                        {item.statusLabel}
                    </Badge>
                    {item.kind} · entregado el{' '}
                    {describeDate(item.deliveredOn) ?? item.deliveredOn}
                </p>
                {item.refusalReason !== null && (
                    <p className="mt-1 text-meta">
                        Motivo: {item.refusalReason}
                    </p>
                )}
            </div>
        </li>
    );
}
