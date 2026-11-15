<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * What an account was already told (see the table's migration): insert-only, one row per notice, so a job that runs again does not tell twice.
 *
 * @property string $id
 * @property string $billing_account_id
 * @property string $kind
 * @property string $reference
 */
final class BillingNotice extends Model
{
    use HasVersion4Uuids;

    public $timestamps = false;
}
