const PESOS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

/** Cents as the staff reads them: "$24,900". */
export function pesos(cents: number): string {
    return PESOS.format(cents / 100);
}

/** What the card of the demo requests says: how many wait for someone. */
export function demosLine(waiting: number): string {
    return waiting === 0
        ? 'Ninguna espera respuesta.'
        : `${waiting} ${waiting === 1 ? 'espera' : 'esperan'} respuesta.`;
}

/** What the card of the quiet companies says. */
export function quietLine(quiet: number): string {
    return quiet === 0
        ? 'Todas tuvieron un acceso en el último mes.'
        : `${quiet} ${quiet === 1 ? 'empresa no tiene' : 'empresas no tienen'} un acceso en 30 días: vale la pena escribirles.`;
}
