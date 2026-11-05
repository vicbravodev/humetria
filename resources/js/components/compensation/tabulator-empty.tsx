import { Layers } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** When the search leaves no category, or the company has none yet. */
export default function TabulatorEmpty({ filtered }: { filtered: boolean }) {
    if (filtered) {
        return (
            <EmptyState icon={Layers} title="Ninguna categoría coincide">
                Cambia la búsqueda para ver más categorías y niveles.
            </EmptyState>
        );
    }

    return (
        <EmptyState icon={Layers} title="Todavía no hay tabulador">
            Captura el salario de una categoría y nivel con «Nuevo salario», o
            carga el padrón con categoría y nivel: aparecerán aquí para ponerles
            su salario.
        </EmptyState>
    );
}
