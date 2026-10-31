import { Plus } from 'lucide-react';
import { useState } from 'react';
import WorkCenterDialog from '@/components/tenancy/work-center-dialog';
import { Button } from '@/components/ui/button';
import { DialogTrigger } from '@/components/ui/dialog';
import { store } from '@/routes/tenant/work-centers';
import type { MexicanStateOption } from '@/types';

/**
 * Adds a work center: from the header's button, or from the dashed tile that closes the grid of cards (pantallas:
 * Centros).
 */
export default function CreateWorkCenterDialog({
    tenantSlug,
    states,
    tile = false,
}: {
    tenantSlug: string;
    states: MexicanStateOption[];
    tile?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <WorkCenterDialog
            open={open}
            onOpenChange={setOpen}
            copy={{
                title: 'Dar de alta centro de trabajo',
                description:
                    'Sus datos aparecerán en el informe de la NOM-035 de este centro.',
                submit: 'Dar de alta',
            }}
            form={store.form(tenantSlug)}
            states={states}
            trigger={
                <DialogTrigger asChild>
                    {tile ? (
                        <button
                            type="button"
                            className="flex min-h-[260px] flex-col items-center justify-center gap-2.5 rounded-xl border-[1.5px] border-dashed border-input bg-muted p-6 text-center transition-colors duration-150 ease-out hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <span className="inline-flex size-11 items-center justify-center rounded-xl border bg-card text-primary">
                                <Plus aria-hidden className="size-5" />
                            </span>
                            <span className="font-medium">
                                Agrega otro centro
                            </span>
                            <span className="max-w-[280px] text-sm text-muted-foreground">
                                Una planta, sucursal u oficina con domicilio
                                propio.
                            </span>
                        </button>
                    ) : (
                        <Button>
                            <Plus aria-hidden />
                            Dar de alta centro
                        </Button>
                    )}
                </DialogTrigger>
            }
        />
    );
}
