import { show } from '@/routes/companies/assistant';

export type TenantAiSettings = {
    enabled: boolean;
    /** The budget the superadmin set for the company; null while it has the default one. */
    monthlyTokenBudget: number | null;
    defaultBudget: number;
    usedTokens: number;
};

type Raw = {
    enabled: boolean;
    monthly_token_budget: number | null;
    default_monthly_token_budget: number;
    used_tokens: number;
};

function isNumberField(value: object, key: string): boolean {
    return key in value && typeof Reflect.get(value, key) === 'number';
}

function isSettings(value: unknown): value is Raw {
    if (
        typeof value !== 'object' ||
        value === null ||
        !('enabled' in value) ||
        typeof value.enabled !== 'boolean'
    ) {
        return false;
    }

    const budgetIsKnown =
        'monthly_token_budget' in value &&
        (value.monthly_token_budget === null ||
            typeof value.monthly_token_budget === 'number');

    return (
        budgetIsKnown &&
        isNumberField(value, 'default_monthly_token_budget') &&
        isNumberField(value, 'used_tokens')
    );
}

/** Whether Humi is on for a company, its budget of tokens and what it used this month; null if the server cannot say. */
export async function readTenantSettings(
    tenantId: string,
): Promise<TenantAiSettings | null> {
    try {
        const response = await fetch(show.url(tenantId), {
            headers: { Accept: 'application/json' },
        });
        const body: unknown = response.ok ? await response.json() : null;

        return isSettings(body)
            ? {
                  enabled: body.enabled,
                  monthlyTokenBudget: body.monthly_token_budget,
                  defaultBudget: body.default_monthly_token_budget,
                  usedTokens: body.used_tokens,
              }
            : null;
    } catch {
        return null;
    }
}

/** A count of tokens with its thousands separated: 2,000,000. */
export function tokensText(tokens: number): string {
    return tokens.toLocaleString('en-US');
}
