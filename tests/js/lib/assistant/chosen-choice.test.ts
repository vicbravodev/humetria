import type { UIMessage } from 'ai';
import { describe, expect, it } from 'vite-plus/test';
import {
    choiceStateOf,
    chosenChoice,
    textOfUser,
} from '@/lib/assistant/chosen-choice';

const rows = [
    { label: 'De todo', text: 'Quiero ver todo' },
    { label: 'NOM-035', text: 'Quiero ver NOM-035' },
];

function said(role: UIMessage['role'], text: string): UIMessage {
    return { id: 'm', role, parts: [{ type: 'text', text }] };
}

describe('chosenChoice', () => {
    it('marks the option whose text is exactly what the person sent next', () => {
        expect(chosenChoice(rows, 'Quiero ver NOM-035')).toBe(
            'Quiero ver NOM-035',
        );
    });

    it('marks nothing when the person wrote something else, or nothing yet', () => {
        expect(chosenChoice(rows, 'quiero ver nom-035')).toBeNull();
        expect(chosenChoice(rows, 'Otra cosa')).toBeNull();
        expect(chosenChoice(rows, '')).toBeNull();
        expect(chosenChoice(rows, null)).toBeNull();
    });
});

describe('textOfUser', () => {
    it('joins what the person wrote, and is null for no message or for an answer of Humi', () => {
        expect(textOfUser(said('user', 'Hola'))).toBe('Hola');
        expect(textOfUser(said('assistant', 'Hola'))).toBeNull();
        expect(textOfUser(undefined)).toBeNull();
    });
});

describe('choiceStateOf', () => {
    const onPick = () => undefined;

    it('keeps the buttons live only on the last answer, once it has finished arriving', () => {
        const live = choiceStateOf({
            rows,
            last: true,
            streaming: false,
            next: undefined,
            onPick,
        });
        const arriving = choiceStateOf({
            rows,
            last: true,
            streaming: true,
            next: undefined,
            onPick,
        });
        const older = choiceStateOf({
            rows,
            last: false,
            streaming: false,
            next: said('user', 'Quiero ver todo'),
            onPick,
        });

        expect(live).toEqual({ disabled: false, chosen: null, onPick });
        expect(arriving.disabled).toBe(true);
        expect(older).toEqual({
            disabled: true,
            chosen: 'Quiero ver todo',
            onPick,
        });
    });
});
