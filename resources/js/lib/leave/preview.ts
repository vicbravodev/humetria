import type { LeavePreview } from '@/types';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

/** What to ask the server for two picked days (Y-m-d); null while they are not both there, in order. */
export function previewQuery(
    startsOn: string,
    endsOn: string,
    typeId: string,
): Record<string, string> | null {
    if (startsOn === '' || endsOn === '' || endsOn < startsOn) {
        return null;
    }

    return {
        starts_on: startsOn,
        ends_on: endsOn,
        ...(typeId === '' ? {} : { leave_type_id: typeId }),
    };
}

/** The server's answer to a preview, or null when it is not one. */
export function parsePreview(value: unknown): LeavePreview | null {
    if (!isRecord(value)) {
        return null;
    }

    const { workingDays, hadBalance, shortBy, continuousNotice } = value;

    if (
        typeof workingDays !== 'number' ||
        (typeof hadBalance !== 'boolean' && hadBalance !== null) ||
        typeof continuousNotice !== 'boolean'
    ) {
        return null;
    }

    return {
        workingDays,
        hadBalance,
        shortBy: typeof shortBy === 'number' ? shortBy : 0,
        continuousNotice,
    };
}

/** The first reason the server gives for refusing the dates, or null. */
export function previewError(value: unknown): string | null {
    if (!isRecord(value) || !isRecord(value['errors'])) {
        return null;
    }

    for (const messages of Object.values(value['errors'])) {
        if (Array.isArray(messages) && typeof messages[0] === 'string') {
            return messages[0];
        }
    }

    return null;
}
