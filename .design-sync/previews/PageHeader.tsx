import { Badge, Button, PageHeader } from 'humetria';
import { CalendarDays, FileText, Users } from 'lucide-react';

export const WithStatusAndFacts = () => (
    <PageHeader
        caption="NOM-035 · Planta Apodaca"
        title="Evaluación 2026"
        status={<Badge variant="secondary">En curso</Badge>}
        facts={[
            { icon: Users, text: '186 personas' },
            { icon: FileText, text: 'Guías I y III' },
            { icon: CalendarDays, text: 'Cierra el 14 de diciembre' },
        ]}
    >
        <Button variant="outline">Enviar recordatorio</Button>
        <Button>Ver avance</Button>
    </PageHeader>
);

export const WithDescription = () => (
    <PageHeader
        title="Empleados"
        description="Faltan 3 personas por invitar. Sin correo ni celular no les llega la evaluación."
    >
        <Button>Agregar empleado</Button>
    </PageHeader>
);

export const TitleOnly = () => <PageHeader title="Centros de trabajo" />;
