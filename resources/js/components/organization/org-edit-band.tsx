import { Pencil } from 'lucide-react';

/** The band over the canvas while whoever may edit is editing: what to do, and that nothing waits for a «Guardar». */
export default function OrgEditBand() {
    return (
        <div
            role="status"
            data-slot="org-edit-band"
            className="flex items-center gap-3 rounded-t-xl border-b bg-accent px-4 py-3 text-sm text-accent-foreground"
        >
            <Pencil aria-hidden className="size-4 shrink-0 text-primary" />
            <p>
                <strong className="font-semibold">Modo edición</strong> ·
                Arrastra a una persona y suéltala sobre su jefe nuevo. Los
                cambios se guardan al soltar.
            </p>
        </div>
    );
}
