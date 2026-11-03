import { TriangleAlert } from 'lucide-react';
import CodeRow from '@/components/complaints-public/code-row';
import type { Receipt } from '@/lib/complaints/receipt';

/** What the person takes with them, shown once: the reference and the key to follow the case up, and what losing it means. */
export default function ReceiptCard({ receipt }: { receipt: Receipt }) {
    return (
        <section className="flex flex-col gap-5 card p-5" aria-live="polite">
            <h2 className="text-xl font-semibold">Recibimos su queja</h2>
            <p>
                Para saber cómo va, leer lo que le responda la persona encargada
                y contestarle, vuelva a esta página con estos dos datos.
            </p>
            <CodeRow label="Referencia" code={receipt.reference} />
            <CodeRow label="Clave" code={receipt.key} />
            <p className="flex items-start gap-2 rounded-lg bg-warning px-4 py-3 text-base text-warning-foreground">
                <TriangleAlert aria-hidden className="mt-1 size-4 shrink-0" />
                <span>
                    Anótelos ahora: esta pantalla no se vuelve a mostrar y, si
                    pierde la clave, no podemos recuperarla. Cierre esta página
                    en un dispositivo propio, no en uno compartido.
                </span>
            </p>
        </section>
    );
}
