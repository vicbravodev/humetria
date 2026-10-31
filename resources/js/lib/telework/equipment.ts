import type { BadgeTone } from '@/lib/telework/review';
import type { Option } from '@/lib/option-search';
import type { EquipmentStatusValue } from '@/types';

/** The kinds of equipment of numeral 5.7, in the order RH reads them. */
export const EQUIPMENT_KINDS: Option[] = [
    { value: 'chair', label: 'Silla ergonómica' },
    { value: 'supplies', label: 'Insumos' },
    { value: 'ergonomic_accessory', label: 'Aditamento ergonómico' },
    { value: 'computer', label: 'Equipo de cómputo' },
    { value: 'other', label: 'Otro' },
];

/** The badge of an item: waiting for the person is a look, received is done, refused is a stop. */
export function equipmentTone(status: EquipmentStatusValue): BadgeTone {
    switch (status) {
        case 'pending':
            return 'warning';
        case 'received':
            return 'success';
        case 'refused':
            return 'destructive';
    }
}
