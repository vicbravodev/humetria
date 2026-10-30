import { Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';

/** Before any employee, or when the filters leave none. */
export default function EmployeesEmpty({ filtered }: { filtered: boolean }) {
    return filtered ? (
        <EmptyState icon={Users} title="Ningún empleado coincide">
            Cambia la búsqueda o los filtros para ver a más personas.
        </EmptyState>
    ) : (
        <EmptyState icon={Users} title="Aún no hay empleados">
            Da de alta a tu gente una por una o carga un CSV con todos: cada
            quien recibirá sus encuestas por correo o celular.
        </EmptyState>
    );
}
