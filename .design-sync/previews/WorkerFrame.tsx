import { Button, IconTile, PortalTabBar, WorkerFrame } from 'humetria';
import { CalendarDays, ChevronRight, ClipboardList, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

Object.assign(globalThis, { HumetriaPage: { url: '/me/aceros-del-norte', props: { brand: null } } });

const Phone = ({ children }: { children: ReactNode }) => (
    // The frame is min-h-dvh (the 780 px viewport) and the card leaves ~340 x 740: a 362 x 780 phone drawn at 94 % shows it whole,
    // seal and bottom actions included. `transform` keeps the fixed tab bar inside the phone.
    <div style={{ width: 362, height: 780, zoom: 0.94, overflow: 'auto', transform: 'translateZ(0)' }}>
        {children}
    </div>
);

// As home-sections.tsx: one big card per tool, with what waits in it.
const Tool = ({ icon, label, description, pending }: { icon: LucideIcon; label: string; description: string; pending?: number }) => (
    <a href="#" className="flex min-h-16 items-center gap-3.5 card p-4">
        <IconTile icon={icon} />
        <span className="flex min-w-0 flex-1 flex-col">
            <span className="text-base font-semibold">{label}</span>
            <span className="truncate text-base text-muted-foreground">{description}</span>
        </span>
        {pending !== undefined && (
            <span className="rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-primary-foreground">{pending}</span>
        )}
        <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-muted-foreground" />
    </a>
);

export const PortalStart = () => (
    <Phone>
        <WorkerFrame
            company="Aceros del Norte"
            bar={<PortalTabBar current="start" pending={2} punchUrl="/me/aceros-del-norte/punch" onSelect={() => {}} />}
        >
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl leading-8 font-semibold tracking-tight text-balance">Buenos días, Mariana</h1>
                <p className="text-muted-foreground">martes, 25 de noviembre · Planta Apodaca</p>
            </div>
            <section className="flex flex-col gap-2">
                <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">Lo que le espera</h2>
                <div className="flex flex-col gap-3">
                    <Tool icon={ClipboardList} label="Evaluación NOM-035" description="Le toma unos 15 minutos" pending={1} />
                    <Tool icon={CalendarDays} label="Mis vacaciones" description="Tiene 12 días disponibles" pending={1} />
                </div>
            </section>
        </WorkerFrame>
    </Phone>
);

export const TaskWithBackAndAction = () => (
    <Phone>
        <WorkerFrame
            company="Aceros del Norte"
            back="/me/aceros-del-norte"
            actions={<Button className="h-14 w-full text-lg font-semibold">Pedir días</Button>}
        >
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">Mis vacaciones</h1>
            <div className="flex flex-col gap-1 card p-4">
                <span className="text-muted-foreground">Días disponibles</span>
                <span className="text-3xl font-semibold tabular-nums">12</span>
                <span className="text-base text-muted-foreground">Del periodo 2026, vencen el 14 de marzo de 2027</span>
            </div>
            <div className="flex flex-col gap-1 card p-4">
                <span className="text-base font-semibold">22 al 26 de diciembre</span>
                <span className="text-base text-muted-foreground">5 días · Esperando respuesta de su jefe</span>
            </div>
        </WorkerFrame>
    </Phone>
);
