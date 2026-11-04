import { Archive, Gift } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** When the search leaves no benefit, or nothing has been archived yet. */
export default function BenefitsEmpty({
    filtered,
    archived,
}: {
    filtered: boolean;
    archived: boolean;
}) {
    if (filtered || !archived) {
        return (
            <EmptyState icon={Gift} title="Ninguna prestación coincide">
                Cambia la búsqueda para ver más prestaciones.
            </EmptyState>
        );
    }

    return (
        <EmptyState icon={Archive} title="Nada archivado">
            Una prestación que ya no uses se archiva y aquí la encuentras; sus
            términos se conservan y puedes restaurarla cuando quieras.
        </EmptyState>
    );
}
