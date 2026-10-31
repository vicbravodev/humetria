import { roleSummary } from '@/lib/roles';
import type { TenantRole } from '@/types/tenancy';

export type ErrorMessage = { caption: string; title: string; body: string };

const SERVER_ERROR = {
    title: 'Algo salió mal de nuestro lado',
    body: 'Ya quedó registrado. Vuelve a intentarlo en unos minutos.',
};

const MESSAGES: Record<number, Omit<ErrorMessage, 'caption'>> = {
    403: {
        title: 'No tienes acceso a esta página',
        body: 'Tu cuenta no tiene permiso para entrar aquí. Si crees que debería, pídeselo a quien administra la cuenta.',
    },
    404: {
        title: 'No encontramos esta página',
        body: 'Puede que el enlace esté mal escrito o que la página se haya movido.',
    },
    419: {
        title: 'La página expiró',
        body: 'Pasó un rato sin actividad y, por seguridad, la página expiró. Vuelve a abrirla para continuar.',
    },
    503: {
        title: 'Estamos en mantenimiento',
        body: 'Volvemos en unos minutos. Gracias por la paciencia.',
    },
};

/** What the error page says for a status (pantallas: Error-403, Error-404); anything unknown reads as a server error. */
export function errorMessage(status: number): ErrorMessage {
    return {
        caption: `Error ${status}`,
        ...(MESSAGES[status] ?? SERVER_ERROR),
    };
}

/**
 * Why a 403 happened when it is the person's role in the company: "Tu rol en Acme es Consulta, que ve resultados e
 * informes". Null outside a company and for the owner, whose role allows everything a role can.
 */
export function roleLimit(
    status: number,
    tenant: { trade_name: string; role: TenantRole; role_label: string } | null,
): { company: string; role: string; summary: string } | null {
    if (status !== 403 || tenant === null || tenant.role === 'owner') {
        return null;
    }

    return {
        company: tenant.trade_name,
        role: tenant.role_label,
        summary: roleSummary(tenant.role),
    };
}
