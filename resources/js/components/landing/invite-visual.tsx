import StepBar from '@/components/landing/step-bar';

/** Step 2: the invitation as it reaches a phone, and how many have answered. */
export default function InviteVisual() {
    return (
        <div className="grid gap-4">
            <div className="mx-auto grid w-full max-w-[260px] gap-3 rounded-[28px] border bg-background p-3.5">
                <p className="rounded-2xl rounded-bl-md border bg-card px-3.5 py-3 text-sm leading-snug">
                    <b className="block font-semibold">
                        Tu encuesta confidencial
                    </b>
                    Te toma unos minutos. RH no verá lo que contestes.
                </p>
                <span className="flex min-h-11 items-center justify-center rounded-xl bg-primary text-meta font-medium text-primary-foreground">
                    Contestar
                </span>
                <small className="text-center text-caption text-muted-foreground">
                    Enlace de un solo uso
                </small>
            </div>
            <div className="grid gap-2 text-sm">
                <p className="flex justify-between gap-2 text-muted-foreground">
                    <span>Han contestado</span>
                    <span>
                        <b className="font-semibold text-foreground tabular-nums">
                            78
                        </b>{' '}
                        de 120
                    </span>
                </p>
                <StepBar value={0.65} />
            </div>
        </div>
    );
}
