import type { CountryCode } from 'libphonenumber-js';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    useSyncExternalStore,
} from 'react';
import {
    countryOptions,
    countryPrefix,
    phoneFromStored,
    typePhone,
} from '@/lib/phone';

const FOLDER = '/node_modules/country-flag-icons/3x2/';

/** Each flag is its own small SVG file, loaded when its country is chosen: no page carries the 250 of them. */
const FLAGS = import.meta.glob<string>(
    '/node_modules/country-flag-icons/3x2/*.svg',
    { query: '?url', import: 'default' },
);

/**
 * The state of a phone field: what it shows and sends, the countries to choose from, and the chosen one's flag
 * (undefined while it loads). Choosing a country masks the digits already typed as written there. `input` goes on
 * the box typed in: when its form is reset (Inertia's resetOnSuccess too), the field goes back to where it started.
 */
export function usePhoneField(stored: string | null | undefined) {
    const [phone, setPhone] = useState(() => phoneFromStored(stored));
    // Country names come from the browser's Intl, which Node's may word otherwise: the server renders the chosen
    // country alone, by its code, and the list arrives once hydrated.
    const hydrated = useSyncExternalStore(
        subscribeToNothing,
        () => true,
        () => false,
    );
    const countries = useMemo(
        () =>
            hydrated
                ? countryOptions()
                : [
                      {
                          code: phone.country,
                          name: '',
                          prefix: countryPrefix(phone.country),
                      },
                  ],
        [hydrated, phone.country],
    );
    const flag = useCountryFlag(phone.country);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const form = input.current?.form;
        const reset = () => setPhone(phoneFromStored(stored));

        form?.addEventListener('reset', reset);

        return () => form?.removeEventListener('reset', reset);
    }, [stored]);

    return {
        input,
        phone,
        countries,
        flag,
        type: (text: string) =>
            setPhone(typePhone(text, phone.country, phone.display)),
        choose: (code: string) => {
            const country = countries.find((option) => option.code === code);

            if (country !== undefined) {
                setPhone(
                    typePhone(
                        phone.display.replace(/\D/g, ''),
                        country.code,
                        '',
                    ),
                );
            }
        },
    };
}

function subscribeToNothing(): () => void {
    return () => undefined;
}

function useCountryFlag(country: CountryCode): string | undefined {
    const [flag, setFlag] = useState<{ country: string; url: string }>();

    useEffect(() => {
        let current = true;

        void FLAGS[`${FOLDER}${country}.svg`]?.().then((url) => {
            if (current) {
                setFlag({ country, url });
            }
        });

        return () => {
            current = false;
        };
    }, [country]);

    return flag?.country === country ? flag.url : undefined;
}
