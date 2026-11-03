import { Landmark } from 'lucide-react';
import type { ExternalAlternative } from '@/types';

/** Another way to be heard: the federal labor inspection takes anonymous reports. */
export default function AlternativeCard({
    alternative,
}: {
    alternative: ExternalAlternative;
}) {
    return (
        <section className="flex w-full flex-col gap-2 card p-5">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                <Landmark aria-hidden className="size-5 text-primary" />
                Otra vía
            </h2>
            <p>{alternative.name}</p>
            <p className="text-base text-muted-foreground">
                {alternative.note}
            </p>
            <p className="text-base">
                Correo: {alternative.email} · Teléfono: {alternative.phone}
            </p>
        </section>
    );
}
