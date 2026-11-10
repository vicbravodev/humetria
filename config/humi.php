<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Provider and model
    |--------------------------------------------------------------------------
    |
    | Humi, the HR assistant, answers with this provider and model. Never fixed in code: the keys of the providers
    | live in config/ai.php.
    |
    */

    'provider' => env('HUMI_PROVIDER', 'anthropic'),

    'model' => env('HUMI_MODEL', 'claude-sonnet-5-5'),

    /*
    |--------------------------------------------------------------------------
    | Budget and rate limits
    |--------------------------------------------------------------------------
    |
    | The tokens a company may use per calendar month (the superadmin can set another figure for a company), and the
    | messages per minute and per day of one person in one company; the company as a whole has its own per-minute limit.
    |
    */

    'monthly_token_budget' => (int) env('HUMI_MONTHLY_TOKEN_BUDGET', 2_000_000),

    /* Tokens held on the company's quota before a message goes to the provider: what the agent may use in one (MaxTokens × MaxSteps = 1,500 × 6).
       The real count settles it afterwards; this only has to be enough that two messages cannot both spend the last of the quota. */
    'reservation_tokens' => (int) env('HUMI_RESERVATION_TOKENS', 9_000),

    'rate_per_minute' => (int) env('HUMI_RATE_PER_MINUTE', 10),

    'rate_per_day' => (int) env('HUMI_RATE_PER_DAY', 200),

    'tenant_rate_per_minute' => (int) env('HUMI_TENANT_RATE_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Normative corpus
    |--------------------------------------------------------------------------
    |
    | The criteria documents Humi answers about the norms from, by norm: they hold each criterion with its source and are
    | the document that rules how Humetría reads the norm (the code follows them). A new norm is one more line here.
    |
    */

    'regulations' => [
        'NOM-035' => 'docs/normas/nom-035/criterios.md',
        'NOM-037' => 'docs/normas/nom-037/criterios.md',
    ],

];
