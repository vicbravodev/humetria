import { UserMinus, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { useIsUnion } from '@/hooks/use-is-union';

/** Before any employee, with nobody deactivated yet, or when the search and filters leave none. */
export default function EmployeesEmpty({
    filtered,
    inactive,
}: {
    filtered: boolean;
    inactive: boolean;
}) {
    const union = useIsUnion();

    if (filtered) {
        return (
            <EmptyState icon={Users} title="Ningún empleado coincide">
                Cambia la búsqueda o los filtros para ver a más personas.
            </EmptyState>
        );
    }

    return inactive ? (
        <EmptyState icon={UserMinus} title="Nadie dado de baja">
            Quien des de baja aparece aquí con su historia, y puedes reactivarle
            cuando quieras.
        </EmptyState>
    ) : union ? (
        <EmptyState icon={Users} title="Aún no hay personas en el padrón">
            Carga el padrón que te entregó la empresa en un CSV, o da de alta a
            las personas una por una: basta con su ID de la empresa.
        </EmptyState>
    ) : (
        <EmptyState icon={Users} title="Aún no hay empleados">
            Da de alta a tu gente una por una o carga un CSV con todos: cada
            quien recibirá sus encuestas por correo o celular.
        </EmptyState>
    );
}
