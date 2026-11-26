import { countOf } from '@/lib/plural';
import type { AccountDetail, PaymentRow, SubscriptionView } from '@/types';

/** The line under an account's name (tablero Cuenta de facturación): its RFC, regime and CFDI use, where to write and how many companies it pays. */
export function accountFacts(
    account: AccountDetail,
    companies: number,
): string {
    return [
        account.rfc,
        account.tax_regime === null ? null : `Régimen ${account.tax_regime}`,
        account.cfdi_use === null ? null : `Uso de CFDI ${account.cfdi_use}`,
        account.contact_email,
        companies === 0
            ? 'aún no paga ninguna empresa'
            : `paga por ${countOf(companies, 'empresa', 'empresas')}`,
    ]
        .filter((fact) => fact !== null)
        .join(' · ');
}

/** «Promedio de pago»: how many days after (or before) it was due the account pays, on average. */
export function averagePaid(days: number | null): {
    value: string;
    hint: string;
} {
    if (days === null) {
        return { value: '—', hint: 'Sin estados pagados este año' };
    }

    return {
        value: countOf(Math.abs(days), 'día', 'días'),
        hint:
            days > 0
                ? 'después de vencer'
                : days < 0
                  ? 'antes de vencer'
                  : 'el día que vence',
    };
}

/** The line under a payment: its day, and the months it settled, what is left to apply or why it was reversed. */
export function paymentNote(payment: PaymentRow): string {
    if (payment.reversed) {
        return `${payment.paid_on} · revertido: ${payment.reversal_reason ?? ''}`;
    }

    if (payment.applied_to.length === 0) {
        return `${payment.paid_on} · sin aplicar ${payment.unapplied}`;
    }

    const months = payment.applied_to;
    const named =
        months.length === 1
            ? months.join('')
            : `${months.slice(0, -1).join(', ')} y ${months.at(-1) ?? ''}`;

    return `${payment.paid_on} · aplicado a ${named}`;
}

/** The month after the day given (Y-m-d), by name: «diciembre». */
export function nextMonth(today: string): string {
    const [year = 0, month = 1] = today.split('-').map(Number);

    return new Intl.DateTimeFormat('es-MX', {
        month: 'long',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(year, month, 1)));
}

/** What a company the account pays has contracted, in a line: its plan, seats and centers, or a union's flat fee. */
export function companyLine(company: {
    subscription: Pick<
        SubscriptionView,
        'plan_label' | 'seats' | 'allowed_centers'
    > | null;
    union: boolean;
}): string {
    const { subscription } = company;

    if (subscription === null) {
        return 'Sin plan asignado';
    }

    if (company.union) {
        return `${subscription.plan_label} · cuota fija`;
    }

    return `${subscription.plan_label} · ${countOf(subscription.seats, 'asiento', 'asientos')} · ${countOf(subscription.allowed_centers, 'centro', 'centros')}`;
}
