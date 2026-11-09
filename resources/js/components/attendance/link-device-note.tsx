import { Smartphone } from 'lucide-react';

/** How to mark when this device is not linked yet. */
export default function LinkDeviceNote() {
    return (
        <section
            aria-labelledby="link-device-title"
            className="flex flex-col gap-2 card p-5"
        >
            <div className="flex items-center gap-3">
                <Smartphone
                    aria-hidden
                    className="size-6 shrink-0 text-primary"
                />
                <h2
                    id="link-device-title"
                    className="text-xl leading-7 font-semibold"
                >
                    ¿Aún no vincula este teléfono?
                </h2>
            </div>
            <p className="text-base">
                Para marcar, su teléfono necesita una llave de acceso. Pida a
                Recursos Humanos su enlace para vincularlo; le llegará a su
                correo y sirve una sola vez.
            </p>
        </section>
    );
}
