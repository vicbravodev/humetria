import { Combobox, InputError, Label } from 'humetria';

const CENTERS = [
    { value: '', label: 'Todos los centros' },
    { value: 'apodaca', label: 'Planta Apodaca' },
    { value: 'escobedo', label: 'Bodega Escobedo' },
    { value: 'santa-fe', label: 'Oficinas Santa Fe' },
    { value: 'guadalajara', label: 'Sucursal Guadalajara' },
];

export const CenterFilter = () => (
    <div style={{ width: 256 }}>
        <Combobox
            id="center-filter"
            name="center"
            source={{ options: CENTERS }}
            defaultValue={{ value: 'apodaca', label: 'Planta Apodaca' }}
            placeholder="Centro de trabajo"
        />
    </div>
);

export const Placeholder = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="employee-area">Área</Label>
        <Combobox
            id="employee-area"
            name="area_id"
            source={{
                options: [
                    { value: 'ops', label: 'Operaciones' },
                    { value: 'warehouse', label: 'Almacén' },
                    { value: 'hr', label: 'Recursos Humanos' },
                ],
                allowNew: true,
            }}
            placeholder="Elige o escribe un área"
        />
    </div>
);

export const WithError = () => (
    <div className="grid gap-2 max-w-sm">
        <Label htmlFor="employee-boss">Jefe directo</Label>
        <Combobox
            id="employee-boss"
            name="manager_id"
            source={{ options: [{ value: '1', label: 'Laura Garza', hint: 'Gerente de planta' }] }}
            placeholder="Busca por nombre"
            invalid
            describedBy="employee-boss-error"
        />
        <InputError id="employee-boss-error" message="Elige quién es su jefe directo." />
    </div>
);
