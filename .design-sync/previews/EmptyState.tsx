import { Button, EmptyState } from 'humetria';
import { Building2, ClipboardList, Users } from 'lucide-react';

export const WithNextStep = () => (
    <EmptyState
        icon={Building2}
        title="Aún no hay centros de trabajo"
        action={<Button>Agregar un centro</Button>}
    >
        La difusión se organiza por centro. Agrega el primero para publicar ahí tu política y tus resultados.
    </EmptyState>
);

export const WithoutAction = () => (
    <EmptyState icon={ClipboardList} title="Aún no hay evaluaciones cerradas">
        Cuando cierres una evaluación podrás publicar su resultado aquí para las personas de ese centro.
    </EmptyState>
);

export const NoSearchResults = () => (
    <EmptyState
        icon={Users}
        title="Nadie coincide con «Ramírez»"
        action={<Button variant="outline">Limpiar búsqueda</Button>}
    >
        Revisa cómo se escribe el nombre o busca por número de empleado.
    </EmptyState>
);
