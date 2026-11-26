import { Button, Combobox, Label, Popover, PopoverContent, PopoverTrigger } from 'humetria';
import { UserRoundPen } from 'lucide-react';

const MANAGERS = [
    { value: 'lucia', label: 'Lucía Garza Treviño' },
    { value: 'ernesto', label: 'Ernesto Villarreal' },
    { value: 'paola', label: 'Paola Méndez Ríos' },
];

export const ChangeManager = () => (
    <div className="flex" style={{ justifyContent: 'flex-end', padding: 16, paddingRight: 24 }}>
        <Popover open onOpenChange={() => {}}>
            <PopoverTrigger asChild>
                <Button variant="outline">
                    <UserRoundPen />
                    Cambiar jefe
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="grid gap-3 p-4"
                style={{ width: 340 }}
                onOpenAutoFocus={(e) => e.preventDefault()}
            >
                <p className="text-base font-semibold">Cambiar jefe de Jorge Salinas Ruiz</p>
                <div className="grid gap-1.5">
                    <Label htmlFor="manager-of-jorge">Jefe directo</Label>
                    <Combobox
                        id="manager-of-jorge"
                        name="manager_id"
                        source={{ options: MANAGERS }}
                        defaultValue={{ value: 'lucia', label: 'Lucía Garza Treviño' }}
                        placeholder="Elige a su jefe directo"
                    />
                </div>
                <div className="flex gap-2" style={{ justifyContent: 'flex-end' }}>
                    <Button variant="ghost">Cancelar</Button>
                    <Button>Guardar</Button>
                </div>
            </PopoverContent>
        </Popover>
    </div>
);
