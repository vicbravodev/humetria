import { ShieldCheck } from 'lucide-react';

/**
 * What the channel protects and what it cannot: said before anything is written. Promising «anonimato total» without it
 * being so would be the worst mistake of this page.
 */
export default function AnonymityLimits() {
    return (
        <section className="flex flex-col gap-3 card p-5">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                <ShieldCheck aria-hidden className="size-5 text-primary" />
                Qué protegemos y qué no
            </h2>
            <p>
                No guardamos su dirección de internet, su navegador, cookies ni
                la hora exacta en que escribe, y esta página no inicia ninguna
                sesión. Su queja solo la ve quien la empresa designó para
                atenderla, nunca la persona de la que usted se queja.
            </p>
            <p className="font-medium">Aun así, tenga presente que:</p>
            <ul className="list-disc space-y-1.5 pl-5">
                <li>
                    Lo que escriba puede identificarle: detalles que solo usted
                    conoce, o su manera de redactar.
                </li>
                <li>
                    El centro de trabajo y la persona señalada reducen el grupo
                    de quién pudo haber escrito.
                </li>
                <li>
                    La red de la empresa o su proveedor de internet pueden ver
                    que visitó esta página. Si le preocupa, use los datos de su
                    celular.
                </li>
            </ul>
        </section>
    );
}
