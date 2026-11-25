/** What the server reads of an amount typed in pesos: no sign, no spaces and no thousands commas ("15,000.50" is "15000.50"). */
export function rawPesos(text: string): string {
    return text.replace(/[$\s,]/g, '');
}

/** An amount in pesos the way it is written: thousands commas and two decimals ("15000.5" is "15,000.50"); null when it is not one. */
export function formatPesos(raw: string): string | null {
    const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(raw);

    if (match === null) {
        return null;
    }

    const [, whole = '', cents = ''] = match;

    return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${cents.padEnd(2, '0')}`;
}
