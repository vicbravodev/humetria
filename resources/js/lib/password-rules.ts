export type PasswordCheck = { label: string; met: boolean };

const CLASSES: Record<string, { label: string; pattern: RegExp }> = {
    lower: { label: 'Una minúscula', pattern: /\p{Ll}/u },
    upper: { label: 'Una mayúscula', pattern: /\p{Lu}/u },
    digit: { label: 'Un número', pattern: /\p{N}/u },
    special: { label: 'Un símbolo', pattern: /[^\p{L}\p{N}]/u },
};

const LETTER = { label: 'Una letra', pattern: /\p{L}/u };

/**
 * What a new password must have, read from the rules the server sends (Laravel's `passwordrules` string, like
 * "minlength: 12; required: lower; required: upper;"), each marked as met or not by `value`. Without "upper", Laravel
 * asks for any letter, not a lowercase one.
 */
export function passwordChecks(rules: string, value: string): PasswordCheck[] {
    const parts = rules
        .split(';')
        .map((part) => part.split(':').map((piece) => piece.trim()))
        .filter(([key]) => key === 'minlength' || key === 'required');
    const anyCase = !parts.some(([, kind]) => kind === 'upper');

    return parts.flatMap(([key, argument = '']) => {
        if (key === 'minlength') {
            const min = Number(argument);

            return [
                {
                    label: `Al menos ${min} caracteres`,
                    met: Array.from(value).length >= min,
                },
            ];
        }

        const check =
            anyCase && argument === 'lower' ? LETTER : CLASSES[argument];

        return check === undefined
            ? []
            : [{ label: check.label, met: check.pattern.test(value) }];
    });
}
