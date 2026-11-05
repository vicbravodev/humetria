import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as complaints } from '@/routes/tenant/complaints';
import { index as interventions } from '@/routes/tenant/interventions';
import { index as telework } from '@/routes/tenant/telework';
import { index as teleworkDocuments } from '@/routes/tenant/telework/documents';
import { index as teleworkTrainings } from '@/routes/tenant/telework/trainings';
import type { NormKey } from '@/lib/compliance-routes';
import type { NavItem } from '@/types';

type Href = NavItem['href'];

export type LiveItem = {
    title: string;
    text: string;
    /** Its way in; without one, the item says how it goes ("Automático"). */
    action:
        | { label: string; href: (tenantSlug: string) => Href }
        | { note: string };
};

/** What stays alive once a route is done (board V3-AlDia; docs/02, «Ciclo de vida»): finishing does not switch the standard off. */
export const LIVE: Record<NormKey, LiveItem[]> = {
    nom035: [
        {
            title: 'Acciones del programa de intervención',
            text: 'Siguen con sus responsables y fechas hasta cerrarse.',
            action: { label: 'Ver programa', href: interventions },
        },
        {
            title: 'Quien entra nuevo recibe la política',
            text: 'Automático al darlo de alta.',
            action: { note: 'Automático' },
        },
        {
            title: 'Si alguien vive un acontecimiento traumático severo',
            text: 'Aplica la Guía I a esa persona cuando pase, sin esperar al siguiente ciclo.',
            action: { label: 'Aplicar Guía I', href: campaigns },
        },
        {
            title: 'Avisos de violencia o trato injusto',
            text: 'El canal de quejas sigue abierto para tus personas.',
            action: { label: 'Ver quejas', href: complaints },
        },
    ],
    nom037: [
        {
            title: 'Revisión de cada lugar',
            text: 'Según la periodicidad de tus ajustes; 12 meses si no la cambias.',
            action: { label: 'Ver centros', href: telework },
        },
        {
            title: 'Capacitación anual',
            text: 'Cada persona, una vez al año.',
            action: { label: 'Ver capacitación', href: teleworkTrainings },
        },
        {
            title: 'Nueva versión de cada documento',
            text: 'Cuando cambia, las personas lo vuelven a confirmar.',
            action: { label: 'Ver documentos', href: teleworkDocuments },
        },
        {
            title: 'Altas y bajas de teletrabajadores',
            text: 'En cualquier momento; cada alta empieza su propia revisión.',
            action: { note: 'Cuando pase' },
        },
    ],
    seating: [
        {
            title: 'Evaluación vigente por persona',
            text: 'Una nueva al cambiar de puesto o de condiciones.',
            action: { note: 'Cuando pase' },
        },
        {
            title: 'El reglamento interior',
            text: 'Se actualiza cuando cambia.',
            action: { note: 'Cuando cambie' },
        },
        {
            title: 'Asientos y reportes',
            text: 'Las personas reportan un asiento en cualquier momento.',
            action: { note: 'Al día' },
        },
    ],
};
