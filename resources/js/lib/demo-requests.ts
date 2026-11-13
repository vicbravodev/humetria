import type { DemoStatus, DemoView } from '@/types';

const LIST = new Intl.ListFormat('es-MX', { type: 'conjunction' });

/** The tools a demo request asks about, as a sentence names them: "NOM-035, Ley Silla y NOM-037". */
export function toolsText(tools: string[]): string {
    return tools.length === 0 ? 'Sin indicar' : LIST.format(tools);
}

export const DEMO_VIEWS: { value: DemoView; label: string }[] = [
    { value: 'all', label: 'Todas' },
    { value: 'new', label: 'Nuevas' },
    { value: 'contacted', label: 'Contactadas' },
    { value: 'converted', label: 'Convertidas' },
    { value: 'discarded', label: 'Descartadas' },
];

/** What a person may choose by hand: «Convertida» is only reached by creating the company from the request. */
export const DEMO_CHOICES: { value: DemoStatus; label: string }[] =
    DEMO_VIEWS.flatMap(({ value, label }) =>
        value === 'all' || value === 'converted' ? [] : [{ value, label }],
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
