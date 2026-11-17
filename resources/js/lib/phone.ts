import { parsePhoneNumberFromString } from 'libphonenumber-js';

/** The country a phone field starts on, and of a number written without one. The server assumes the same. */
export const DEFAULT_COUNTRY = 'MX';

/** A stored E.164 number as people read it: a Mexican one as dialed in Mexico, another with its country code. */
export function readablePhone(e164: string): string {
    const number = parsePhoneNumberFromString(e164);

    if (number === undefined) {
        return e164;
    }

    return number.country === DEFAULT_COUNTRY
        ? number.formatNational()
        : number.formatInternational();
}
