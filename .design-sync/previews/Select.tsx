import { Label, Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from 'humetria';

const reasons = [
    { value: 'resignation', label: 'Renuncia voluntaria' },
    { value: 'dismissal', label: 'Despido' },
    { value: 'contract_end', label: 'Fin de contrato' },
    { value: 'retirement', label: 'Jubilación' },
];

export const Placeholder = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="reason-empty">Motivo</Label>
        <Select>
            <SelectTrigger id="reason-empty" className="w-full">
                <SelectValue placeholder="Elige un motivo" />
            </SelectTrigger>
            <SelectContent>
                {reasons.map((r) => (
                    <SelectItem key={r.value} value={r.value}>
                        {r.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    </div>
);

export const Selected = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="center">Centro de trabajo</Label>
        <Select defaultValue="apodaca">
            <SelectTrigger id="center" className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="apodaca">Planta Apodaca</SelectItem>
                <SelectItem value="monterrey">Oficinas Monterrey</SelectItem>
            </SelectContent>
        </Select>
    </div>
);

export const SmallInTable = () => (
    <Select defaultValue="hr">
        <SelectTrigger size="sm" aria-label="Rol de Mariana López">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="owner">Dueño</SelectItem>
            <SelectItem value="hr">Recursos Humanos</SelectItem>
            <SelectItem value="viewer">Solo lectura</SelectItem>
        </SelectContent>
    </Select>
);

export const Error = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="reason-error">Motivo</Label>
        <Select>
            <SelectTrigger id="reason-error" className="w-full" aria-invalid>
                <SelectValue placeholder="Elige un motivo" />
            </SelectTrigger>
            <SelectContent>
                {reasons.map((r) => (
                    <SelectItem key={r.value} value={r.value}>
                        {r.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
        <p className="text-sm text-destructive">Elige el motivo de la baja.</p>
    </div>
);

export const Disabled = () => (
    <div className="flex max-w-sm flex-col gap-2">
        <Label htmlFor="center-disabled">Centro de trabajo</Label>
        <Select defaultValue="apodaca" disabled>
            <SelectTrigger id="center-disabled" className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="apodaca">Planta Apodaca</SelectItem>
            </SelectContent>
        </Select>
    </div>
);
