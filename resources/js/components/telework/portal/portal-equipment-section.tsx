import PortalEquipmentCard from '@/components/telework/portal/portal-equipment-card';
import type { PortalEquipment } from '@/types';

/** The equipment the company says it delivered, for the person to confirm they received it or refuse it. */
export default function PortalEquipmentSection({
    equipment,
    token,
}: {
    equipment: PortalEquipment[];
    token: string;
}) {
    if (equipment.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="portal-equipment" className="grid gap-3">
            <h2 id="portal-equipment" className="text-xl font-semibold">
                Equipo por confirmar
            </h2>
            <ul className="grid gap-3">
                {equipment.map((item) => (
                    <PortalEquipmentCard
                        key={item.id}
                        item={item}
                        token={token}
                    />
                ))}
            </ul>
        </section>
    );
}
