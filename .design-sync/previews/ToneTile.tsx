import { ToneTile } from 'humetria';
import { Clock, Coffee, Fingerprint, LogIn, MapPinOff, WifiOff } from 'lucide-react';

export const Tones = () => (
    <div className="flex gap-3">
        <ToneTile icon={LogIn} tone="accent" />
        <ToneTile icon={Coffee} tone="warning" />
        <ToneTile icon={MapPinOff} tone="blocked" />
        <ToneTile icon={Clock} tone="ok" />
    </div>
);

export const Large = () => (
    <div className="flex gap-3">
        <ToneTile icon={Fingerprint} large />
        <ToneTile icon={WifiOff} tone="warning" large />
        <ToneTile icon={MapPinOff} tone="blocked" large />
    </div>
);

export const DayStatus = () => (
    <section className="flex max-w-md items-center gap-4 card p-5">
        <ToneTile icon={Clock} tone="ok" />
        <div className="flex min-w-0 flex-col">
            <p className="text-xl font-semibold">Trabajando</p>
            <p className="text-base text-muted-foreground">Entró a las 8:02</p>
        </div>
    </section>
);

export const PersonProblem = () => (
    <section className="flex max-w-md flex-col items-start gap-3">
        <ToneTile icon={MapPinOff} tone="blocked" large />
        <h1 className="text-2xl font-semibold">Está fuera de la zona del centro</h1>
        <p className="text-muted-foreground">Acérquese a Planta Apodaca y vuelva a intentarlo.</p>
    </section>
);
