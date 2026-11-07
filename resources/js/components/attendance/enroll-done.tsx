import { CircleCheck } from 'lucide-react';

/** The phone is linked: what the person can do now. */
export default function EnrollDone() {
    return (
        <section
            role="status"
            className="flex flex-col items-start gap-3 card p-5"
        >
            <CircleCheck aria-hidden className="size-7 text-primary" />
            <h2 className="text-xl leading-7 font-semibold">
                Su teléfono quedó vinculado
            </h2>
            <p className="text-base">
                Desde ahora registra su jornada con este teléfono y su huella,
                su rostro o su PIN. El enlace que recibió ya no sirve: si cambia
                de teléfono, pida a Recursos Humanos uno nuevo.
            </p>
        </section>
    );
}
