import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MaintenanceDialog from '@/components/announcements/maintenance-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { end } from '@/routes/announcements/maintenance';
import type { MaintenanceState } from '@/types';

/** Whether the platform is in maintenance, since when and why; the switch that starts it, or the button that ends it. */
export default function MaintenanceCard({
    maintenance,
}: {
    maintenance: MaintenanceState;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section
            aria-labelledby="maintenance-title"
            className="flex flex-wrap items-center justify-between gap-4 card"
        >
            <div className="grid min-w-0 flex-1 gap-1">
                <div className="flex flex-wrap items-center gap-3">
                    <h2
                        id="maintenance-title"
                        className="text-xl font-semibold tracking-[-0.01em]"
                    >
                        Modo mantenimiento
                    </h2>
                    {maintenance.active ? (
                        <Badge variant="warning">Activo</Badge>
                    ) : (
                        <Badge variant="secondary">Apagado</Badge>
                    )}
                </div>
                <p className="text-meta text-muted-foreground">
                    {maintenance.active
                        ? `Desde el ${maintenance.since}: ${maintenance.reason}. Todas las empresas están en solo lectura; las encuestas y los portales siguen.`
                        : 'Al activarlo, todas las empresas pasan a solo lectura y las encuestas siguen recibiendo respuestas.'}
                </p>
            </div>
            {maintenance.active ? (
                <Form {...end.form()}>
                    {({ processing }) => (
                        <Button type="submit" disabled={processing}>
                            Terminar mantenimiento
                        </Button>
                    )}
                </Form>
            ) : (
                <Button variant="destructive" onClick={() => setOpen(true)}>
                    Activar mantenimiento
                </Button>
            )}
            <MaintenanceDialog open={open} onOpenChange={setOpen} />
        </section>
    );
}
