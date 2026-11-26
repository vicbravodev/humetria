import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MaintenanceDialog from '@/components/announcements/maintenance-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { end } from '@/routes/announcements/maintenance';
import type { MaintenanceState } from '@/types';

/** Whether the platform is in maintenance, since when and why (tablero Avisos); the button that starts it, or the one that ends it. */
export default function MaintenanceCard({
    maintenance,
}: {
    maintenance: MaintenanceState;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section aria-labelledby="maintenance-title" className="card">
            <header className="flex items-center gap-3 border-b px-6 py-4">
                <h2
                    id="maintenance-title"
                    className="flex-1 text-base font-semibold"
                >
                    Modo mantenimiento
                </h2>
                {maintenance.active ? (
                    <Badge variant="warning">Activo</Badge>
                ) : (
                    <Badge variant="secondary">Apagado</Badge>
                )}
            </header>
            <div className="grid justify-items-start gap-2.5 px-6 py-4">
                <p className="text-muted-foreground">
                    {maintenance.active
                        ? `Desde el ${maintenance.since}: ${maintenance.reason}. Todas las empresas están en solo lectura; las encuestas y los portales siguen.`
                        : 'Al activarlo, todas las empresas pasan a solo lectura y las encuestas siguen recibiendo respuestas.'}
                </p>
                {maintenance.active ? (
                    <Form {...end.form()}>
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                Terminar mantenimiento
                            </Button>
                        )}
                    </Form>
                ) : (
                    <Button
                        variant="outline"
                        className="border-destructive text-destructive hover:text-destructive"
                        onClick={() => setOpen(true)}
                    >
                        Activar mantenimiento
                    </Button>
                )}
            </div>
            <MaintenanceDialog open={open} onOpenChange={setOpen} />
        </section>
    );
}
