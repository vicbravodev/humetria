import { pesos } from '@/lib/overview';

/** A column of «Ingreso mensual»: the shape the chart of the design system draws. */
export type BilledColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    percent: number;
};

/** «Buenos días, Victor»: the greeting of «Resumen» by the hour of the day, with the first name when there is one. */
export function greeting(hour: number, name: string): string {
    const salute =
        hour >= 6 && hour < 12
            ? 'Buenos días'
            : hour >= 12 && hour < 19
              ? 'Buenas tardes'
              : 'Buenas noches';
    const first = name.trim().split(/\s+/)[0] ?? '';

    return first === '' ? salute : `${salute}, ${first}`;
}

const SHORT = new Intl.DateTimeFormat('es-MX', {
    month: 'short',
    timeZone: 'UTC',
});
const LONG = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

/** Thousands of pesos, short, for the top of a column: "$184k". */
function thousands(cents: number): string {
    const pesosValue = cents / 100;

    return pesosValue < 1000
        ? pesos(cents)
        : `$${Math.round(pesosValue / 1000)}k`;
}

/** What was billed each month as the columns of «Ingreso mensual»: each against the highest. */
export function billedColumns(
    months: { month: string; cents: number }[],
): BilledColumn[] {
    const highest = Math.max(0, ...months.map(({ cents }) => cents));

    return months.map(({ month, cents }) => {
        const date = new Date(`${month}-01T00:00:00Z`);

        return {
            key: month,
            label: SHORT.format(date).replace('.', ''),
            valueText: thousands(cents),
            tooltip: `${LONG.format(date).replace(' de ', ' ')}: ${pesos(cents)} facturado`,
            percent: highest === 0 ? 0 : Math.round((cents / highest) * 100),
        };
    });
}
