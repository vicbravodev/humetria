import { Pencil } from 'lucide-react';
import { useState } from 'react';
import ChannelSettingsForm from '@/components/complaints/channel-settings-form';
import ProcedureFacts from '@/components/complaints/procedure-facts';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ChannelBounds, ChannelSettings } from '@/types';

/**
 * «Cómo se atiende» (board V4-Quejas-Canal): the procedure the people read before writing, and the deadlines the company set —
 * not the norm's — with the way to change them.
 */
export default function ChannelProcedure({
    settings,
    bounds,
}: {
    settings: ChannelSettings;
    bounds: ChannelBounds;
}) {
    const [editing, setEditing] = useState(false);

    return (
        <section
            aria-labelledby="procedure"
            className="flex flex-col gap-5 card p-6"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex max-w-[560px] flex-col gap-1">
                    <h2
                        id="procedure"
                        className="text-xl leading-7 font-semibold"
                    >
                        Cómo se atiende
                    </h2>
                    <p className="text-muted-foreground">
                        Las personas lo leen antes de escribir. Escríbelo en
                        lenguaje sencillo, en primera persona de la empresa.
                    </p>
                </div>
                <Button variant="outline" onClick={() => setEditing(true)}>
                    <Pencil aria-hidden />
                    Editar
                </Button>
            </div>
            <p className="whitespace-pre-line">{settings.procedure}</p>
            <ProcedureFacts
                acknowledgeWithinDays={settings.acknowledgeWithinDays}
                resolveWithinDays={settings.resolveWithinDays}
                retentionMonths={settings.retentionMonths}
            />
            <p className="text-caption text-muted-foreground">
                Estos plazos los fijas tú; la norma no los marca. Al vencer la
                conservación, se borra el contenido y queda solo un registro sin
                datos personales.
            </p>
            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-[760px]">
                    <DialogTitle>Cómo se atiende</DialogTitle>
                    <DialogDescription>
                        El procedimiento y los plazos que leen las personas
                        antes de presentar una queja.
                    </DialogDescription>
                    <ChannelSettingsForm
                        settings={settings}
                        bounds={bounds}
                        activating={false}
                        onSaved={() => setEditing(false)}
                    />
                </DialogContent>
            </Dialog>
        </section>
    );
}
