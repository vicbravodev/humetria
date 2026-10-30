import { ClipboardList } from 'lucide-react';

/** Where the evaluation in course goes on the panel, while none is. */
export default function DashboardQuietCard() {
    return (
        <article className="flex flex-col items-center justify-center gap-1 rounded-xl border border-dashed bg-card p-6 text-center">
            <span className="mb-3 flex size-12 items-center justify-center rounded-xl bg-accent text-primary">
                <ClipboardList aria-hidden className="size-6" />
            </span>
            <h2 className="text-lg leading-[26px] font-semibold">
                Ninguna evaluación en curso
            </h2>
            <p className="max-w-sm text-muted-foreground">
                Cuando lances una, aquí verás quién ha contestado y cuánto falta
                para el cierre.
            </p>
        </article>
    );
}
