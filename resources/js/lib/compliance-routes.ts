import { index as campaigns } from '@/routes/tenant/campaigns';
import { index as seating } from '@/routes/tenant/seating';
import { index as telework } from '@/routes/tenant/telework';
import { certificate as seatingCertificate } from '@/routes/tenant/seating';
import { certificate as teleworkCertificate } from '@/routes/tenant/telework';
import { show as teleworkApplies } from '@/routes/tenant/telework/applies';
import { applies as centerApplies } from '@/routes/tenant/work-centers';
import type { CenterRoutes, NavItem } from '@/types';

export type NormKey = keyof Omit<CenterRoutes, 'id' | 'name'>;

/** A standard as the panel shows it: its name, what it is about and the page where its route starts. */
export type Norm = {
    key: NormKey;
    title: string;
    subtitle: string;
    href: (tenantSlug: string) => NavItem['href'];
    /** Where a center answers «¿Te aplica?» for it (decisión A2); NOM-035 applies to every center and asks nothing. */
    answer?: (tenantSlug: string, workCenterId: string) => NavItem['href'];
    /** Its printable certificate where a center declared that it does not apply (decisiones A2 y D6). */
    certificate?: (tenantSlug: string, workCenterId: string) => NavItem['href'];
};

/** The standards in the order of the menu (board 40). */
export const NORMS: [Norm, Norm, Norm] = [
    {
        key: 'nom035',
        title: 'NOM-035',
        subtitle: 'Riesgo psicosocial',
        href: campaigns,
    },
    {
        key: 'nom037',
        title: 'NOM-037',
        subtitle: 'Teletrabajo',
        href: telework,
        answer: (tenantSlug, workCenterId) =>
            teleworkApplies([tenantSlug, workCenterId]),
        certificate: (tenantSlug, workCenterId) =>
            teleworkCertificate([tenantSlug, workCenterId]),
    },
    {
        key: 'seating',
        title: 'Ley Silla',
        subtitle: 'Trabajo de pie',
        href: seating,
        answer: (tenantSlug, workCenterId) =>
            centerApplies([tenantSlug, workCenterId], {
                query: { norm: 'seating' },
            }),
        certificate: (tenantSlug, workCenterId) =>
            seatingCertificate([tenantSlug, workCenterId]),
    },
];

/** A standard by its key. */
export function normOf(key: NormKey): Norm {
    return NORMS.find((norm) => norm.key === key) ?? NORMS[0];
}

export type Stage = { title: string; text: string };

/** The six stages of each route, as boards V2-Ruta-N035, V2-Ruta-N037 and V2-Ruta-Silla title and explain them. */
export const STAGES: Record<NormKey, Stage[]> = {
    nom035: [
        {
            title: 'Define tu política de prevención',
            text: 'Un documento corto que dice cómo previenes los riesgos psicosociales y la violencia laboral, y cómo pueden quejarse las personas sin miedo.',
        },
        {
            title: 'Evalúa a tu personal',
            text: 'Las personas contestan, de forma confidencial, las guías que le tocan a su centro.',
        },
        {
            title: 'Canaliza a quien lo necesite',
            text: 'Quien lo requiere según la Guía I recibe valoración clínica.',
        },
        {
            title: 'Comparte los resultados con tu equipo',
            text: 'Las personas deben conocer los resultados generales, la política y cómo pueden quejarse. Humetría arma un resumen sin datos individuales.',
        },
        {
            title: 'Actúa sobre los riesgos',
            text: 'Tus resultados piden un programa de intervención con acciones, responsables y fechas.',
        },
        {
            title: 'Ten listo tu expediente',
            text: 'Se arma solo con lo anterior. Al informe 7.7 le faltan tus conclusiones, recomendaciones y responsable.',
        },
    ],
    nom037: [
        {
            title: 'Define las reglas del teletrabajo',
            text: 'Tu Política de Teletrabajo es la base: dice horarios, desconexión y cómo se regresa a la oficina. Sin ella, lo demás no se sostiene.',
        },
        {
            title: 'Registra a quién teletrabaja y dónde',
            text: 'Cada persona que trabaja desde casa, con el domicilio desde donde lo hace.',
        },
        {
            title: 'Revisa que cada lugar sea seguro',
            text: 'Cada persona contesta una lista sobre su espacio y la Comisión la valida.',
        },
        {
            title: 'Entrega equipo y capacita',
            text: 'El equipo que entregas a cada persona y su capacitación vigente.',
        },
        {
            title: 'Atiende lo que avisan las personas',
            text: 'Cambios de domicilio, accidentes y casos de fuerza mayor llegan desde su portal. Revísalos en cuanto lleguen.',
        },
        {
            title: 'Ten listo tu expediente',
            text: 'Humetría lo arma solo con lo de las etapas anteriores. Lo descargas cuando llegue una inspección.',
        },
    ],
    seating: [
        {
            title: 'Identifica los puestos de pie',
            text: 'Los puestos donde las personas trabajan de pie y quién los ocupa.',
        },
        {
            title: 'Pon asientos donde hacen falta',
            text: 'Asientos en cada puesto que lo permite y áreas de descanso señalizadas.',
        },
        {
            title: 'Evalúa a cada persona que trabaja de pie',
            text: 'La Comisión revisa cómo le afecta estar de pie a cada quien. Los datos del puesto ya están: solo confirmas lo de la persona.',
        },
        {
            title: 'Acuerda pausas y medidas',
            text: 'Las pausas y las medidas que pide el resultado de cada evaluación.',
        },
        {
            title: 'Actualiza tu reglamento interior',
            text: 'El reglamento recoge los asientos y las pausas: firmado, depositado y repartido.',
        },
        {
            title: 'Documenta para la inspección',
            text: 'El diagnóstico y programa de seguridad y salud, con los datos que ya capturaste.',
        },
    ],
};
