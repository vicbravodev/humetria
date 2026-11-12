<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\CompanyBrief;

/**
 * The companies, seen from outside any of them: for a console that works across companies (who pays what) and needs their names,
 * status and size without reading Tenancy's tables.
 */
interface CompanyRegistry
{
    /** A company by id; null when there is none (or the id is not one). */
    public function find(string $id): ?CompanyBrief;

    /**
     * The companies with those ids; an id that is not a company is left out.
     *
     * @param  list<string>  $ids
     * @return array<string, CompanyBrief> by id.
     */
    public function many(array $ids): array;

    /**
     * The companies whose trade name, legal name or RFC has what was typed (case and accents aside), by name, up to a limit:
     * for a field that searches them on the server.
     *
     * @return list<CompanyBrief>
     */
    public function search(string $term, int $limit = 20): array;

    /**
     * The id of every company, for a job that visits them all (the nightly measure of their files).
     *
     * @return list<string>
     */
    public function ids(): array;
}
