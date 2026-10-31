import type { NoticeKindValue } from '@/types';

/** The three written notices of numerals 6.3, 6.7 and 6.9, in the order the portal offers them. */
export const NOTICE_KINDS: {
    value: NoticeKindValue;
    title: string;
    help: string;
}[] = [
    {
        value: 'condition_change',
        title: 'Alteración de condiciones o fuerza mayor',
        help: 'Algo cambió en la seguridad o salud de su lugar de trabajo, o un caso de fuerza mayor le impide teletrabajar, incluido un acontecimiento traumático severo que haya sufrido o presenciado por su trabajo (numeral 6.3).',
    },
    {
        value: 'address_change',
        title: 'Cambio de domicilio',
        help: 'Avise con anticipación si cambia el lugar donde teletrabaja, de forma definitiva o temporal (numeral 6.7). La empresa revisará que el nuevo lugar sea adecuado.',
    },
    {
        value: 'work_risk',
        title: 'Riesgo de trabajo',
        help: 'Avise si sufrió un accidente o enfermedad con motivo de su trabajo (numeral 6.9).',
    },
];

/** What a kind of notice asks beyond the dates and the description. */
export function noticeExtras(kind: NoticeKindValue): {
    traumatic: boolean;
    address: boolean;
} {
    return {
        traumatic: kind === 'condition_change',
        address: kind === 'address_change',
    };
}
