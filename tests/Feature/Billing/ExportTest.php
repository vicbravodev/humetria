<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Support\CsvCell;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

mutates(CsvCell::class);

/** The CSV a staff member downloads, as its rows (without the byte-order mark). */
function exportRows(object $test, string $report, ?User $user = null): array
{
    $response = $test->actingAs($user ?? superadmin())->get(route('billing.exports.show', $report))->assertOk();
    $body = $response->streamedContent();

    expect(str_starts_with($body, "\xEF\xBB\xBF"))->toBeTrue();

    return array_map(str_getcsv(...), array_values(array_filter(explode("\n", substr($body, 3)), fn (string $line): bool => $line !== '')));
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('lives under /admin/billing/exports', function (): void {
    expect(route('billing.exports.show', 'portfolio', absolute: false))->toBe('/admin/billing/exports/portfolio');
});

it('sends guests to the login and gives everyone who is not staff a 404', function (): void {
    $this->get(route('billing.exports.show', 'portfolio'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('billing.exports.show', 'portfolio'))->assertNotFound();
});

it('answers 404 to a report that does not exist', function (): void {
    $this->actingAs(superadmin())->get('/admin/billing/exports/secrets')->assertNotFound();
});

it('downloads as a CSV file named for the report and the day, for the superadmin and for support', function (): void {
    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('billing.exports.show', 'balances'))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('saldos-2026-11-26.csv');
    }
});

it('lists the portfolio: every company an account pays with its plan, status, size and what it costs a month before VAT', function (): void {
    [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
    $account = BillingAccount::query()->findOrFail(AccountCompany::query()->where('tenant_id', $tenant->id)->value('billing_account_id'));
    $withoutPlan = App\Modules\Tenancy\Models\Tenant::factory()->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $withoutPlan->id]);

    $rows = exportRows($this, 'portfolio');

    expect($rows[0])->toBe(['Cuenta', 'RFC de la cuenta', 'Tipo', 'Empresa', 'RFC de la empresa', 'Plan', 'Perfil', 'Estado', 'Personas', 'Centros', 'Importe mensual sin IVA', 'Próxima renovación'])
        ->and($rows)->toHaveCount(3);
    $mine = collect($rows)->firstWhere(3, $tenant->trade_name);
    expect($mine[0])->toBe($account->legal_name)->and($mine[5])->toBe('Cumplimiento')->and($mine[8])->toBe('100')->and($mine[10])->toBe('4900.00');
    $bare = collect($rows)->firstWhere(3, $withoutPlan->trade_name);
    expect($bare[5])->toBe('Sin plan')->and($bare[10])->toBe('');
});

it('lists the balances: each account with what it owes, what is past due and its oldest open statement', function (): void {
    [$tenant] = statementCompany(['starts_on' => '2026-09-01']);
    AccountCompany::query()->update(['starts_on' => '2026-09-01']);
    $this->travelTo('2026-11-11 12:00:00');
    resolve(IssueStatement::class)->handle(resolve(GenerateStatement::class)->handle($tenant->id, CarbonImmutable::parse('2026-11-01'), null), User::factory()->create());
    $this->travelTo('2026-11-26 15:00:00');
    BillingAccount::factory()->create(); // owes nothing: not listed

    $rows = exportRows($this, 'balances');

    expect($rows[0])->toBe(['Cuenta', 'RFC', 'Estados con saldo', 'Saldo por cobrar', 'Saldo vencido', 'Emitido el más antiguo'])
        ->and($rows)->toHaveCount(2)
        ->and(array_slice($rows[1], 2))->toBe(['1', '5684.00', '5684.00', '2026-11-11']);
});

it('lists the renewals by date: each company that pays, its renewal and what the next draft will total', function (): void {
    [$later] = statementCompany(['starts_on' => '2026-09-01', 'renews_on' => '2027-01-01']);
    [$sooner] = statementCompany(['starts_on' => '2026-09-01', 'renews_on' => '2026-12-01']);
    statementCompany(['status' => App\Modules\Billing\Enums\SubscriptionStatus::Ended]);

    $rows = exportRows($this, 'renewals');

    expect($rows[0])->toBe(['Empresa', 'Cuenta', 'Plan', 'Estado', 'Renovación', 'Total del siguiente estado con IVA'])
        ->and(array_column(array_slice($rows, 1), 0))->toBe([$sooner->trade_name, $later->trade_name])
        ->and($rows[1][4])->toBe('2026-12-01')->and($rows[1][5])->toBe('5684.00');
});

it('writes one line of log and one audit entry for each export, with the report and the number of rows', function (): void {
    Log::spy();
    statementCompany(['starts_on' => '2026-09-01']);
    $admin = superadmin();

    exportRows($this, 'portfolio', $admin);

    Log::shouldHaveReceived('info')->with('billing.report_exported', ['report' => 'portfolio', 'rows' => 1])->once();
    expect(AuditEntry::query()->where('event', 'billing.report_exported')->sole()->metadata)->toEqual(['report' => 'portfolio', 'rows' => 1])
        ->and(AuditEntry::query()->where('event', 'billing.report_exported')->sole()->actor_id)->toBe($admin->id);
});

describe('a cell', function (): void {
    it('cannot run as a formula in a spreadsheet: what starts with = + - @ or a tab is quoted with an apostrophe', function (string $text, string $safe): void {
        expect(CsvCell::safe($text))->toBe($safe);
    })->with([
        ['=HYPERLINK("x")', "'=HYPERLINK(\"x\")"],
        ['+52 55', "'+52 55"],
        ['-1', "'-1"],
        ['@cmd', "'@cmd"],
        ["\tx", "'\tx"],
        ['Acme -1', 'Acme -1'],
        ['', ''],
    ]);
});
