import { usePage } from '@inertiajs/react';
import { nextObligation } from '@/lib/app-navigation';

/**
 * "Siguiente obligación" above the user in the sidebar (pantalla Panel): when the soonest evaluation in course
 * closes, with how much of its time has gone by. Hidden without one and while the sidebar is folded to its icons.
 */
export function NextObligationCard() {
    const { inCourse } = usePage().props;
    const obligation = nextObligation(inCourse);

    if (obligation === null || inCourse === null) {
        return null;
    }

    return (
        <section
            aria-label="Siguiente obligación"
            data-test="next-obligation"
            className="mx-3 mb-2.5 flex flex-col gap-2 rounded-xl border p-3 group-data-[collapsible=icon]:hidden"
        >
            <h2 className="text-[13px] leading-[18px] font-medium">
                Siguiente obligación
            </h2>
            <p className="text-[13px] leading-[18px] text-muted-foreground">
                {obligation.lead}{' '}
                <b className="font-semibold text-foreground">
                    {obligation.when}
                </b>
            </p>
            <div
                aria-hidden
                className="h-1.5 overflow-hidden rounded-full bg-track"
            >
                <span
                    className="block h-full rounded-full bg-primary"
                    style={{ width: `${inCourse.elapsed}%` }}
                />
            </div>
        </section>
    );
}
