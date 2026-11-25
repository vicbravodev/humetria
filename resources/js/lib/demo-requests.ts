import type { DemoFilters, DemoMetrics, DemoStatus, DemoView } from '@/types';

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

/** The list's address: the status and what was searched, each only when there is one. */
export function demoListQuery(
    filters: DemoFilters,
): Partial<Record<keyof DemoFilters, string>> {
    // The list is the page's default view: its address does not name it.
    const q = filters.q.trim();

    return { ...demoQuery(filters.status), ...(q === '' ? {} : { q }) };
}

/** What an empty tab says: nothing arrived at all, or nothing is in that state. */
export function demoEmptyText(view: DemoView): string {
    return view === 'all'
        ? 'Cuando alguien llene el formulario de la landing, su solicitud aparecerá aquí.'
        : 'Ninguna solicitud está en este estado. Cuando cambies una, aparecerá aquí.';
}

/** A figure with nothing to count yet. «Sin dato» is the channel's word: it means nobody knows where one came from. */
const NOTHING_YET = '—';

/** The median time to the first answer: hours under two days, then days. */
export function firstResponseText(hours: number | null): string {
    if (hours === null) {
        return NOTHING_YET;
    }

    if (hours < 1) {
        return 'Menos de 1 h';
    }

    return hours < 48
        ? `${Math.round(hours)} h`
        : `${Math.round(hours / 24)} d`;
}

/** How many of the new requests have waited more than 48 hours. */
export function unattendedText(unattended: number): string {
    return unattended === 0
        ? 'Ninguna con más de 48 h'
        : `${unattended} con más de 48 h`;
}

export type DemoMetricTile = {
    title: string;
    /** Null while the figures arrive. */
    figure: string | null;
    caption: string;
};

/** The four figures at the top of the console, in order; while they arrive, their titles alone. */
export function demoMetricTiles(
    metrics: DemoMetrics | undefined,
): DemoMetricTile[] {
    return [
        {
            title: 'Nuevas',
            figure: metrics === undefined ? null : String(metrics.new),
            caption:
                metrics === undefined ? '' : unattendedText(metrics.unattended),
        },
        {
            title: 'Tiempo de primera respuesta',
            figure:
                metrics === undefined
                    ? null
                    : firstResponseText(metrics.first_response_hours),
            caption: 'Mediana, últimos 90 días',
        },
        {
            title: 'Conversión a empresa',
            figure:
                metrics === undefined
                    ? null
                    : metrics.conversion === null
                      ? NOTHING_YET
                      : `${metrics.conversion} %`,
            caption: 'Últimos 90 días',
        },
        sourceTile(metrics),
    ];
}

function sourceTile(metrics: DemoMetrics | undefined): DemoMetricTile {
    const title = 'Fuente principal';

    if (metrics === undefined) {
        return { title, figure: null, caption: '' };
    }

    return metrics.source === null
        ? { title, figure: NOTHING_YET, caption: 'Ninguna dijo de dónde llegó' }
        : {
              title,
              figure: metrics.source.label,
              caption: `${metrics.source.share} % de las solicitudes, 90 días`,
          };
}
