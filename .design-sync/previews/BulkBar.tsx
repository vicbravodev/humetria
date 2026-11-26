import { BulkBar, Button, Checkbox } from 'humetria';

const PEOPLE = [
    { name: 'Rosa Elena Martínez', area: 'Producción', checked: true },
    { name: 'Jorge Luis Treviño', area: 'Almacén', checked: true },
    { name: 'Ana Sofía Garza', area: 'Calidad', checked: false },
    { name: 'Luis Fernando Cantú', area: 'Mantenimiento', checked: true },
];

export const UnderATable = () => (
    <div className="max-w-2xl overflow-hidden card">
        <ul className="divide-y">
            {PEOPLE.map((person) => (
                <li key={person.name} className="flex items-center gap-3 px-4 py-3">
                    <Checkbox defaultChecked={person.checked} aria-label={`Elegir a ${person.name}`} />
                    <span className="flex-1 font-medium">{person.name}</span>
                    <span className="text-sm text-muted-foreground">{person.area}</span>
                </li>
            ))}
        </ul>
        <BulkBar count={3} onClear={() => {}}>
            <Button>Enviar enlace a su portal</Button>
        </BulkBar>
    </div>
);

export const OneChosen = () => (
    <div className="max-w-2xl overflow-hidden card">
        <BulkBar count={1} one="marca elegida" many="marcas elegidas" onClear={() => {}}>
            <Button>Validar</Button>
            <Button variant="outline">Rechazar</Button>
        </BulkBar>
    </div>
);

export const ManyActions = () => (
    <div className="max-w-2xl overflow-hidden card">
        <BulkBar count={12} one="marca elegida" many="marcas elegidas" onClear={() => {}}>
            <Button>Validar</Button>
            <Button variant="outline">Rechazar</Button>
        </BulkBar>
    </div>
);
