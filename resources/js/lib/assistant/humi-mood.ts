import type { HumiExpression } from '@/lib/assistant/humi-expressions';

/** What Humi is going through, from what the panel knows. */
export type HumiMoodInput = {
    /** An answer is coming. */
    busy: boolean;
    /** The last message did not get an answer. */
    failed: boolean;
    /** The person has the box focused. */
    writing: boolean;
    /** It finished an answer a moment ago. */
    finished: boolean;
};

/** The face for the moment (design system, Humi): calcula while it answers, escucha when you write or it failed, listo once done. */
export function moodOf(input: HumiMoodInput): HumiExpression {
    if (input.busy) {
        return 'calcula';
    }

    if (input.failed || input.writing) {
        return 'escucha';
    }

    return input.finished ? 'listo' : 'hola';
}
