import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as complaints } from '@/routes/tenant/complaints';
import {
    dossier as disclosureDossier,
    index as disclosure,
} from '@/routes/tenant/disclosure';
import { index as interventions } from '@/routes/tenant/interventions';
import {
    dossier as seatingDossier,
    route as seatingRoute,
} from '@/routes/tenant/seating';
import {
    dossier as teleworkDossier,
    index as telework,
    route as teleworkRoute,
} from '@/routes/tenant/telework';
import { index as teleworkDocuments } from '@/routes/tenant/telework/documents';
import { index as teleworkTrainings } from '@/routes/tenant/telework/trainings';
import type { NormKey } from '@/lib/compliance-routes';
import type { NavItem } from '@/types';

type Href = NavItem['href'];

/** The printable dossier each module keeps for a center: what «Descargar expediente» opens. */
export function dossierHref(
    norm: NormKey,
    tenantSlug: string,
    workCenterId: string,
): Href {
    const args: [string, string] = [tenantSlug, workCenterId];

    switch (norm) {
        case 'nom035':
            return disclosureDossier(args);
        case 'nom037':
            return teleworkDossier(args);
        default:
            return seatingDossier(args);
    }
}

/**
 * Where each of the six stages of a route is done today (board V3-Movil), until the pages of each route arrive with its
 * own PR: the policies and documents of the company, the center's page, and the dossier last. NOM-037 and Ley Silla
 * already have their route pages (boards V2-Ruta-N037 and V2-Ruta-Silla), where every stage is done.
 */
export function stageHrefs(
    norm: NormKey,
    tenantSlug: string,
    workCenterId: string,
): Href[] {
    const center: [string, string] = [tenantSlug, workCenterId];

    switch (norm) {
        case 'nom035':
            return [
                disclosure(tenantSlug),
                campaigns(tenantSlug),
                campaigns(tenantSlug),
                disclosure(tenantSlug),
                interventions(tenantSlug),
                dossierHref(norm, tenantSlug, workCenterId),
            ];
        case 'nom037':
            return [
                ...Array.from({ length: 5 }, () => teleworkRoute(center)),
                dossierHref(norm, tenantSlug, workCenterId),
            ];
        default:
            return [
                ...Array.from({ length: 5 }, () => seatingRoute(center)),
                dossierHref(norm, tenantSlug, workCenterId),
            ];
    }
}

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
