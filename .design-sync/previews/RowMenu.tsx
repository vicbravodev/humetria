import { RowMenu } from 'humetria';
import { KeyRound, Pencil, ShieldOff, UserMinus } from 'lucide-react';
import { useEffect, useRef } from 'react';

// RowMenu keeps its open state inside; the preview opens it the way a keyboard user does (Enter on its button).
export const EmployeeActions = () => {
    const row = useRef<HTMLDivElement>(null);
    useEffect(() => {
        row.current
            ?.querySelector('[data-slot="row-menu"]')
            ?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    }, []);

    return (
        <div
            ref={row}
            className="flex items-center justify-between rounded-xl border bg-card px-4 py-3"
            style={{ width: 420 }}
        >
            <div className="flex flex-col">
                <span className="text-sm font-medium">Ana Pérez Garza</span>
                <span className="text-sm text-muted-foreground">Planta Apodaca · Operaciones</span>
            </div>
            <RowMenu
                label="Acciones de Ana Pérez Garza"
                items={[
                    { label: 'Editar', icon: Pencil, onSelect: () => {} },
                    { label: 'Restablecer contraseña', icon: KeyRound, onSelect: () => {} },
                    { label: 'Quitar verificación en dos pasos', icon: ShieldOff, onSelect: () => {} },
                    { label: 'Dar de baja', icon: UserMinus, onSelect: () => {}, destructive: true },
                ]}
            />
        </div>
    );
};
