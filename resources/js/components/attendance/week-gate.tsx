import { CalendarDays } from 'lucide-react';

/**
 * Before anything of the person shows (board «Asistencia: Mi semana, centrado»): a state screen, its group in the middle of
 * the free space, the 80 px icon coming in; their week opens only once their device verifies them (the button is at the foot).
 */
export default function WeekGate() {
    return (
        <section className="flex max-w-xs flex-col items-center gap-4 text-center">
            <span
                aria-hidden
                className="flex size-20 shrink-0 animate-in items-center justify-center rounded-2xl bg-accent text-primary duration-200 ease-out fade-in zoom-in-96"
            >
                <CalendarDays className="size-10" />
            </span>
            <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                Mi semana
            </h1>
            <p className="text-[17px] leading-[26px] text-muted-foreground">
                Para ver sus marcas y sus horas, confirme que es usted con su
                huella, su rostro o su PIN. Nada de su semana se guarda en este
                teléfono.
            </p>
        </section>
    );
}
