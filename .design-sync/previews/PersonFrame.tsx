import { Button, PersonFrame } from 'humetria';
import { Clock, Fingerprint, MapPin } from 'lucide-react';
import type { ReactNode } from 'react';

Object.assign(globalThis, { HumetriaPage: { url: '/me/aceros-del-norte/punch', props: { brand: null } } });

const Phone = ({ children }: { children: ReactNode }) => (
    // The frame is min-h-dvh (the 780 px viewport) and the card leaves ~340 x 740: a 362 x 780 phone drawn at 94 % shows it whole,
    // seal and bottom actions included. `transform` keeps the fixed tab bar inside the phone.
    <div style={{ width: 362, height: 780, zoom: 0.94, overflow: 'auto', transform: 'translateZ(0)' }}>
        {children}
    </div>
);

// The time record's area: Marcar, with the punch as the main action at the foot.
export const PunchScreen = () => (
    <Phone>
        <PersonFrame
            company="Aceros del Norte"
            area={{ icon: Clock, label: 'Asistencia' }}
            actions={
                <Button className="h-14 w-full text-lg font-semibold">
                    <Fingerprint className="size-5" />
                    Marcar entrada
                </Button>
            }
        >
            <div className="flex w-full max-w-md flex-col gap-5">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl leading-8 font-semibold tracking-tight">Buenos días, Mariana</h1>
                    <p className="text-muted-foreground">martes, 25 de noviembre</p>
                </div>
                <div className="flex flex-col gap-2 card p-4">
                    <span className="text-muted-foreground">Su horario de hoy</span>
                    <span className="text-3xl font-semibold tabular-nums">7:00 a 15:00</span>
                    <span className="flex items-center gap-1.5 text-base text-muted-foreground">
                        <MapPin className="size-4" />
                        Planta Apodaca
                    </span>
                </div>
            </div>
        </PersonFrame>
    </Phone>
);

// A screen of state: its group centered in the free space.
export const CenteredState = () => (
    <Phone>
        <PersonFrame company="Aceros del Norte" area={{ icon: Clock, label: 'Asistencia' }} centered>
            <div className="flex w-full max-w-md flex-col items-center gap-3 text-center">
                <h1 className="text-2xl leading-8 font-semibold tracking-tight">Su entrada quedó registrada</h1>
                <p className="text-muted-foreground">7:02 · Planta Apodaca. Puede cerrar esta pantalla.</p>
            </div>
        </PersonFrame>
    </Phone>
);
