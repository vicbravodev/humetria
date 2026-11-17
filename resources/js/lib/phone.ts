import type { CountryCode } from 'libphonenumber-js';
import {
    AsYouType,
    getCountries,
    getCountryCallingCode,
    parsePhoneNumberFromString,
} from 'libphonenumber-js';

/** The country a phone field starts on, and of a number written without one. The server assumes the same. */
export const DEFAULT_COUNTRY: CountryCode = 'MX';

/** What a phone field shows (masked for its country) and sends: E.164, or what was typed when it is no number. */
export type PhoneState = {
    country: CountryCode;
    display: string;
    value: string;
};

export type CountryOption = { code: CountryCode; name: string; prefix: string };

/**
 * What the field holds after a person types or pastes `text` with `country` chosen. A number pasted with its code
 * moves the field to that country; Mexico's old 1 of mobiles and 044/045 are dropped, as the server drops them.
 * `previous` is what it showed: erasing a space or a dash erases the digit before it, or the mask would put it back.
 */
export function typePhone(
    text: string,
    country: CountryCode,
    previous: string,
): PhoneState {
    const typed = withoutMexicanLegacy(
        erasedSeparator(text, previous),
        country,
    );

    if (typed === '') {
        return { country, display: '', value: '' };
    }

    if (!typed.startsWith('+')) {
        const mask = new AsYouType(country);
        const display = mask.input(typed);

        return { country, display, value: mask.getNumberValue() ?? typed };
    }

    const international = new AsYouType();
    const shown = international.input(typed);
    const found = international.getCountry();

    if (found === undefined) {
        return { country, display: shown, value: typed };
    }

    return {
        country: found,
        display: new AsYouType(found).input(international.getNationalNumber()),
        value: international.getNumberValue() ?? typed,
    };
}

/** The field for a number already stored in E.164: on its country, masked. Empty and on Mexico when there is none. */
export function phoneFromStored(e164: string | null | undefined): PhoneState {
    return typePhone(e164 ?? '', DEFAULT_COUNTRY, '');
}

/** Every country, Mexico first and the rest by their name in Spanish, with the code to dial it. */
export function countryOptions(): CountryOption[] {
    const names = new Intl.DisplayNames(['es-MX'], { type: 'region' });
    const options = getCountries().map((code) => ({
        code,
        name: names.of(code) ?? code,
        prefix: countryPrefix(code),
    }));
    const others = options
        .filter((option) => option.code !== DEFAULT_COUNTRY)
        .sort((a, b) => a.name.localeCompare(b.name, 'es'));

    return [
        ...options.filter((option) => option.code === DEFAULT_COUNTRY),
        ...others,
    ];
}

/** The code to dial a country from abroad: "+52". */
export function countryPrefix(country: CountryCode): string {
    return `+${getCountryCallingCode(country)}`;
}

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

/** 00 dials abroad from Mexico as + does; a Mexican number typed the old way becomes +52 and its 10 digits. */
function withoutMexicanLegacy(text: string, country: CountryCode): string {
    const trimmed = text.trim().replace(/^00/, '+');
    const digits = trimmed.replace(/\D/g, '');
    const legacy = trimmed.startsWith('+')
        ? /^521(\d{10})$/.exec(digits)
        : country === DEFAULT_COUNTRY
          ? /^(?:521|1|04[45])(\d{10})$/.exec(digits)
          : null;

    return legacy === null ? trimmed : `+52${legacy[1]}`;
}

function erasedSeparator(text: string, previous: string): string {
    const digits = (value: string) => value.replace(/\D/g, '');

    if (
        text.length !== previous.length - 1 ||
        digits(text) !== digits(previous)
    ) {
        return text;
    }

    let at = 0;

    while (at < text.length && text[at] === previous[at]) {
        at++;
    }

    const before = text.slice(0, at).search(/\d(?=\D*$)/);

    return before === -1
        ? text
        : text.slice(0, before) + text.slice(before + 1);
}
