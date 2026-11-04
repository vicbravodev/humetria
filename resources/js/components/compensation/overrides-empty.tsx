import { UserRoundPen } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** When the search leaves no person, or nobody has an individual salary yet. */
export default function OverridesEmpty({ filtered }: { filtered: boolean }) {
    if (filtered) {
        return (
            <EmptyState icon={UserRoundPen} title="Nadie coincide">
                Cambia la búsqueda para ver más personas.
            </EmptyState>
        );
    }

    return (
        <EmptyState icon={UserRoundPen} title="Nadie tiene sueldo individual">
            Casi todos cobran el salario de su categoría y nivel. Si alguien
            gana otra cosa, captúralo aquí con su ID de la empresa y se
            calculará con ese sueldo.
        </EmptyState>
    );
}
