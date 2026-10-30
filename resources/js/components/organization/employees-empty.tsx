import { UserMinus, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** Before any employee, with nobody deactivated yet, or when the search and filters leave none. */
export default function EmployeesEmpty({
    filtered,
    inactive,
}: {
    filtered: boolean;
    inactive: boolean;
}) {
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
    ) : (
        <EmptyState icon={Users} title="Aún no hay empleados">
            Da de alta a tu gente una por una o carga un CSV con todos: cada
            quien recibirá sus encuestas por correo o celular.
        </EmptyState>
    );
}
