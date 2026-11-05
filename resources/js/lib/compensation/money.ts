const PESOS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});
const CENTS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});
const TENTHS = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 });

/** "$1,023,676" for 102,367,592 cents: whole pesos, for the figures and the charts of the report. */
export function wholePesos(cents: number): string {
    return PESOS.format(Math.round(cents / 100));
}

/** "$608.50" for "608.5": an amount as the editor types it, with its cents only when it has them. */
export function amountText(typed: string): string {
    const pesos = Number(typed);

    return CENTS.format(Number.isFinite(pesos) ? pesos : 0).replace(
        /\.00$/,
        '',
    );
}

/** "74.6 %": a share with one decimal at most. */
export function percentText(part: number, whole: number): string {
    return `${TENTHS.format(whole === 0 ? 0 : (part / whole) * 100)} %`;
}
