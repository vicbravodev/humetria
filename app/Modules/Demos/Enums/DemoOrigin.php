<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/**
 * Where a request came from, as the funnel groups it: a paid ad (its click, or a paid utm_medium), a campaign of ours
 * (any other utm), a search engine, another site, or nobody knows (which is not direct traffic).
 */
enum DemoOrigin: string
{
    case Ad = 'ad';
    case Campaign = 'campaign';
    case Search = 'search';
    case Referral = 'referral';
    case Unknown = 'unknown';

    /**
     * The origin of each row, in SQL, so the console filters and shows it from the same rule.
     *
     * @var literal-string
     */
    public const string SQL = "case
        when ad_click_id is not null or lower(utm_medium) in ('cpc', 'ppc', 'paid', 'pago', 'paid_search', 'paid_social', 'display') then 'ad'
        when coalesce(utm_source, utm_medium, utm_campaign) is not null then 'campaign'
        when referrer_host ~* '(^|\\.)(google|bing|duckduckgo|yahoo|ecosia)\\.' then 'search'
        when referrer_host is not null then 'referral'
        else 'unknown' end";

    public function label(): string
    {
        return match ($this) {
            self::Ad => 'Anuncio',
            self::Campaign => 'Campaña',
            self::Search => 'Orgánico',
            self::Referral => 'Referido',
            self::Unknown => 'Sin dato',
        };
    }
}
