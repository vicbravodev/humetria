<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Support\LimitMessage;

it('tells whom to write to in a refusal that sends the company to Humetría, when a support address is set', function (): void {
    config(['app.support_email' => 'ayuda@humetria.mx']);

    expect(LimitMessage::refusal(CompanyLimits::SEATS, 5, 5, 1, null))
        ->toBe('Tu plan cubre 5 personas y ya las tienes todas. Pide una ampliación a Humetría para dar de alta a más. Escríbenos a ayuda@humetria.mx.');
});

it('writes Humetría with its accent and adds no contact when no support address is set', function (): void {
    config(['app.support_email' => null]);

    expect(LimitMessage::refusal(CompanyLimits::CENTERS, 2, 2, 1, null))
        ->toBe('Tu plan cubre 2 centros de trabajo y ya los tienes todos. Pide una ampliación a Humetría para agregar otro.');
});
