import { Fingerprint, MousePointerClick, Smartphone } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';

const STEPS: { icon: LucideIcon; title: string; detail: string }[] = [
    {
        icon: Fingerprint,
        title: 'Cree su llave',
        detail: 'Huella o rostro de este teléfono',
    },
    {
        icon: Smartphone,
        title: 'Póngala en su inicio',
        detail: 'Se abre como app, con avisos',
    },
    {
        icon: MousePointerClick,
        title: 'Marque su primera entrada',
        detail: 'Desde su inicio, con un solo toque',
    },
];

/**
 * What a person sees the first time, with the link RH issued: three steps and that it is done once. It names nobody (the link
 * is the only thing that says who it is for, and only once it is spent).
 */
export default function LinkFirstUse() {
    return (
        <div className="flex w-full max-w-md flex-col gap-6">
            <div className="flex flex-col gap-1.5">
                <h1 className="text-[28px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Le damos la bienvenida
                </h1>
                <p className="text-lg text-muted-foreground">
                    Son tres pasos y se hacen una sola vez.
                </p>
            </div>
            <ol className="flex flex-col gap-3">
                {STEPS.map((step, index) => (
                    <li
                        key={step.title}
                        className="flex items-center gap-3.5 card p-4"
                    >
                        <IconTile icon={step.icon} />
                        <span className="flex min-w-0 flex-col">
                            <span className="text-base font-semibold">
                                {index + 1}. {step.title}
                            </span>
                            <span className="text-base text-muted-foreground">
                                {step.detail}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
            <p className="text-center text-base text-muted-foreground">
                Su huella nunca sale del teléfono
            </p>
        </div>
    );
}
