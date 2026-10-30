import type {
    SurveyGate,
    SurveyInstrument,
    SurveyItem,
    SurveySection,
} from '@/types';

/** A person's answers to one guide: item numbers ("1"…) and gate keys, to option values. */
export type Answers = Record<string, string>;

/** One screen of the survey: a yes/no gate alone, or up to a few items of one section. */
export type Step = {
    section: number;
    gate: SurveyGate | null;
    items: SurveyItem[];
    keys: string[];
};

export type StepProgress = {
    section: number;
    sections: number;
    answered: number;
    inSection: number;
};

/**
 * The sections the person is asked, given their answers so far. Guía I ends after a section I with no "yes"
 * (GR.I a), as the server checks.
 */
function visibleSections(
    instrument: SurveyInstrument,
    answers: Answers,
): number[] {
    const first = instrument.sections[0];
    const continues =
        instrument.key !== 'nom035.guide-i' ||
        (first?.items.some((item) => answers[String(item.number)] === 'yes') ??
            false);

    return instrument.sections
        .map((_, index) => index)
        .filter((index) => index === 0 || continues);
}

function gateOpen(section: SurveySection, answers: Answers): boolean {
    return section.gate === null || answers[section.gate.key] === 'yes';
}

function sectionKeys(section: SurveySection, answers: Answers): string[] {
    const items = gateOpen(section, answers)
        ? section.items.map((item) => String(item.number))
        : [];

    return section.gate === null ? items : [section.gate.key, ...items];
}

/** Every question the person is asked, in order: a gated block only after its "yes". */
export function visibleKeys(
    instrument: SurveyInstrument,
    answers: Answers,
): string[] {
    return visibleSections(instrument, answers).flatMap((index) => {
        const section = instrument.sections[index];

        return section === undefined ? [] : sectionKeys(section, answers);
    });
}

export function isComplete(
    instrument: SurveyInstrument,
    answers: Answers,
): boolean {
    return visibleKeys(instrument, answers).every(
        (key) => answers[key] !== undefined,
    );
}

/** What is sent: only the questions asked, so an answer left in a block later skipped never reaches the server. */
export function payload(
    instrument: SurveyInstrument,
    answers: Answers,
): Answers {
    const sent: Answers = {};

    for (const key of visibleKeys(instrument, answers)) {
        const value = answers[key];

        if (value !== undefined) {
            sent[key] = value;
        }
    }

    return sent;
}

/** The screens of the guide: a gate on its own, then its items `perScreen` at a time, never mixing sections. */
export function steps(
    instrument: SurveyInstrument,
    answers: Answers,
    perScreen: number,
): Step[] {
    return visibleSections(instrument, answers).flatMap((index) => {
        const section = instrument.sections[index];

        if (section === undefined) {
            return [];
        }

        const screens: Step[] =
            section.gate === null
                ? []
                : [
                      {
                          section: index,
                          gate: section.gate,
                          items: [],
                          keys: [section.gate.key],
                      },
                  ];

        if (!gateOpen(section, answers)) {
            return screens;
        }

        for (let start = 0; start < section.items.length; start += perScreen) {
            const items = section.items.slice(start, start + perScreen);
            screens.push({
                section: index,
                gate: null,
                items,
                keys: items.map((item) => String(item.number)),
            });
        }

        return screens;
    });
}

/** "Sección 3 de 8 · 2 de 3 respondidas": progress by section, counting a gate with its block. */
export function progressOf(
    instrument: SurveyInstrument,
    answers: Answers,
    step: Step,
): StepProgress {
    const section = instrument.sections[step.section];
    const keys = section === undefined ? [] : sectionKeys(section, answers);

    return {
        section: step.section + 1,
        sections: instrument.sections.length,
        answered: keys.filter((key) => answers[key] !== undefined).length,
        inSection: keys.length,
    };
}

/** Where a guide's answers wait on the device until they are sent. */
export function storageKey(token: string, instrument: string): string {
    return `humetria:survey:${token}:${instrument}`;
}

/** Answers kept on the device, if they are what this page wrote: an object of strings. Anything else is dropped. */
export function readAnswers(text: string | null): Answers {
    if (text === null) {
        return {};
    }

    let parsed: unknown;

    try {
        parsed = JSON.parse(text);
    } catch {
        return {};
    }

    if (
        typeof parsed !== 'object' ||
        parsed === null ||
        Array.isArray(parsed)
    ) {
        return {};
    }

    const entries = Object.entries(parsed);

    return entries.every(([, value]) => typeof value === 'string')
        ? Object.fromEntries(
              entries.map(([key, value]) => [key, String(value)]),
          )
        : {};
}
