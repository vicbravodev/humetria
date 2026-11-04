import { describeDate } from '@/lib/calendar-date';
import type { Draft } from '@/lib/complaints/form';
import { NOTICE } from '@/lib/complaints/form';
import type { ComplaintKindOption, ComplaintTypeOption } from '@/types';

type Row = [label: string, value: string];

const UNSAID = 'Sin indicar';

function whoRow(draft: Draft, kinds: ComplaintKindOption[]): Row {
    if (draft.type === NOTICE) {
        return ['Personas involucradas', draft.involvedText.trim() || UNSAID];
    }

    const kind = kinds.find((option) => option.value === draft.respondentKind);

    return ['De quién se trata', kind?.label ?? UNSAID];
}

function identityRow(draft: Draft): Row {
    return [
        'Cómo va',
        draft.identityMode === 'anonymous'
            ? 'Anónima: sin nombre ni contacto'
            : `Confidencial, con su nombre (${draft.reporterName.trim()})`,
    ];
}

/** The label and the value of each line the person reviews before sending. */
export function reviewRows(
    draft: Draft,
    types: ComplaintTypeOption[],
    kinds: ComplaintKindOption[],
): Row[] {
    const type = types.find((option) => option.value === draft.type);
    const rows: Row[] = [
        ['Qué informa', type?.label ?? UNSAID],
        ['Lo que pasó', draft.description.trim()],
    ];

    if (draft.type === NOTICE) {
        rows.push([
            'Fecha en que ocurrió',
            describeDate(draft.occurredOn) ?? UNSAID,
        ]);
    }

    rows.push(
        ['Centro de trabajo', draft.workCenter?.label ?? UNSAID],
        whoRow(draft, kinds),
        identityRow(draft),
        [
            'Aviso por correo',
            draft.notifyEmail.trim() === ''
                ? 'No'
                : 'Sí: le avisaremos a su correo. Nadie lo verá.',
        ],
    );

    return rows;
}
