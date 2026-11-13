import type { AuditFilters } from '@/types';

const LABELS: Record<string, string> = {
    'tenant.created': 'Empresa dada de alta',
    'tenant.updated': 'Empresa actualizada',
    'tenant.activated': 'Empresa activada',
    'tenant.reactivated': 'Empresa reactivada',
    'tenant.restricted': 'Empresa restringida',
    'tenant.trial_started': 'Prueba iniciada',
    'tenant.owner_changed': 'Dueño cambiado',
    'tenant.module_granted': 'Herramienta concedida',
    'tenant.module_revoked': 'Herramienta quitada',
    'tenant.modules_synced': 'Herramientas del plan aplicadas',
    'tenant_invitation.sent': 'Invitación enviada',
    'tenant_invitation.resent': 'Invitación reenviada',
    'tenant_invitation.cancelled': 'Invitación cancelada',
    'tenant_invitation.accepted': 'Invitación aceptada',
    'tenant_member.removed': 'Miembro quitado',
    'tenant_member.role_changed': 'Rol cambiado',
    'impersonation.started': 'Inicio de impersonación',
    'impersonation.stopped': 'Fin de impersonación',
    'user.password_reset_sent': 'Enlace de contraseña enviado',
    'user.two_factor_removed': 'Verificación en dos pasos quitada',
    'user.support_granted': 'Rol de soporte dado',
    'user.support_revoked': 'Rol de soporte quitado',
    'user.superadmin_created': 'Superadmin creado',
    'demo.updated': 'Solicitud de demo actualizada',
    'demo.converted': 'Solicitud de demo convertida',
    'campaign.launched': 'Campaña lanzada',
    'campaign.closed': 'Campaña cerrada',
    'results.viewed': 'Resultados consultados',
    'billing.payment_recorded': 'Pago registrado',
    'billing.statement_issued': 'Estado de cuenta emitido',
};

/** An event as a person reads it; one the map does not know yet shows its own name. */
export function eventLabel(event: string): string {
    return LABELS[event] ?? event;
}

/** The trail's filters as its address carries them: only the ones in use. */
export function auditQuery(
    filters: AuditFilters,
): Partial<Record<keyof AuditFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.family === '' ? {} : { family: filters.family }),
        ...(filters.company === '' ? {} : { company: filters.company }),
        ...(filters.from === '' ? {} : { from: filters.from }),
        ...(filters.to === '' ? {} : { to: filters.to }),
    };
}

/** Whether any filter narrows the trail: an empty list is then the filters' doing. */
export function auditFiltered(filters: AuditFilters): boolean {
    return Object.keys(auditQuery(filters)).length > 0;
}
