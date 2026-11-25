import type { DemoStatus, DemoView } from '@/types';

const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** The tools a demo request asks about, as a sentence names them: "NOM-035, Ley Silla y NOM-037". */
export function toolsText(tools: string[]): string {
    return tools.length === 0 ? 'Sin indicar' : LIST.format(tools);
}

/** The size of the company as the console writes it, or that the person did not say. */
export function workforceText(workforce: string | null): string {
    return workforce === null ? 'Sin indicar' : `${workforce} trabajadores`;
}

/** Who the request is for, or that the person did not say. */
export function profileText(profile: string | null): string {
    return profile ?? 'Perfil sin indicar';
}

export const DEMO_VIEWS: { value: DemoView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'new', label: 'Nuevas' },
    { value: 'contacted', label: 'Contactadas' },
    { value: 'scheduled', label: 'Demo agendada' },
    { value: 'trial', label: 'Prueba activa' },
    { value: 'converted', label: 'Convertidas' },
    { value: 'discarded', label: 'Descartadas' },
];

/** Not a state to choose: «Convertida» comes from creating the company, «Demo agendada» from scheduling it with its day. */
const NOT_BY_HAND: DemoStatus[] = ['converted', 'scheduled'];

/** What a person may choose by hand. */
export const DEMO_CHOICES: { value: DemoStatus; label: string }[] =
    DEMO_VIEWS.flatMap(({ value, label }) =>
        value === 'all' || NOT_BY_HAND.includes(value)
            ? []
            : [{ value, label }],
    );

/** The console's view as its address carries it: the default, all of them, carries nothing. */
export function demoQuery(view: DemoView): { status?: DemoView } {
    return view === 'all' ? {} : { status: view };
}

/** What an empty tab says: nothing arrived at all, or nothing is in that state. */
export function demoEmptyText(view: DemoView): string {
    return view === 'all'
        ? 'Cuando alguien llene el formulario de la landing, su solicitud aparecerá aquí.'
        : 'Ninguna solicitud está en este estado. Cuando cambies una, aparecerá aquí.';
}
