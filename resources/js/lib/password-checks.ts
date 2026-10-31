export type PasswordCheck = { label: string; met: boolean };

const SEGMENTS = 4;

/**
 * What the server will ask of a new password, read from the `passwordrules` it sends (Laravel's
 * Password::toPasswordRulesString), each marked as met or not by what was typed so far. A leaked password can only be
 * caught by the server, so it is not listed.
 */
export function passwordChecks(
    rules: string,
    password: string,
): PasswordCheck[] {
    const minLength = /minlength:\s*(\d+)/.exec(rules)?.[1];
    const required = (kind: string) => rules.includes(`required: ${kind}`);
    const mixedCase = required('upper');
    const checks: (PasswordCheck | false)[] = [
        minLength !== undefined && {
            label: `Al menos ${minLength} caracteres`,
            met: password.length >= Number(minLength),
        },
        mixedCase && {
            label: 'Mayúsculas y minúsculas',
            met: /\p{Ll}/u.test(password) && /\p{Lu}/u.test(password),
        },
        !mixedCase &&
            required('lower') && {
                label: 'Al menos una letra',
                met: /\p{L}/u.test(password),
            },
        required('digit') && {
            label: 'Al menos un número',
            met: /\d/.test(password),
        },
        required('special') && {
            label: 'Al menos un símbolo',
            met: /[^\p{L}\p{N}\s]/u.test(password),
        },
    ];

    return checks.filter((check) => check !== false);
}

/** How many of the meter's four segments the checks met fill. */
export function filledSegments(checks: PasswordCheck[]): number {
    if (checks.length === 0) {
        return 0;
    }

    return Math.round(
        (checks.filter((check) => check.met).length / checks.length) * SEGMENTS,
    );
}
