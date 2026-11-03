import { index, show } from '@/routes/tenant/campaigns';
import type {
    BreadcrumbItem,
    ProgramCampaign,
    ProgramRequirement,
    ProgramScope,
} from '@/types';

/** What an evaluation without a Programa tells: the title and the sentence (num. 8.3). */
export function programStartText(requirement: ProgramRequirement): {
    title: string;
    body: string;
} {
    switch (requirement) {
        case 'required':
            return {
                title: 'Esta evaluación pide un Programa de intervención',
                body: 'Hay cuestionarios en riesgo medio o más. El Programa parte de ese resultado: una fila por cada categoría y dominio que lo pide.',
            };
        case 'below_minimum':
            return {
                title: 'Hubo muy pocos cuestionarios para mostrar cifras',
                body: 'Por la confidencialidad no se deriva ninguna fila del resultado. Puedes crear el Programa vacío y agregar tú las filas que quieras atender.',
            };
        case 'not_required':
            return {
                title: 'La norma no pide un Programa para esta evaluación',
                body: 'Ningún cuestionario quedó en riesgo medio o más (num. 8.3). Si cambia el resultado, aquí aparecerá la opción de crearlo.',
            };
    }
}

/** The name of what a row attends, by its scope. */
export const SCOPE_LABELS: Record<ProgramScope, string> = {
    final: 'Calificación final',
    category: 'Categoría',
    domain: 'Dominio',
};

/** The trail of the Programa: NOM-035 › the work center › Programa de intervención. */
export function programTrail(
    tenantSlug: string,
    campaign: ProgramCampaign,
): BreadcrumbItem[] {
    return [
        { title: 'NOM-035', href: index(tenantSlug) },
        { title: campaign.workCenter, href: show([tenantSlug, campaign.id]) },
        { title: 'Programa de intervención' },
    ];
}
