import { EyeOff, History, MessageSquareText, UserRound } from 'lucide-react';
import { describeDate } from '@/lib/calendar-date';
import { cn } from '@/lib/utils';
import type { CaseEntryRow } from '@/types';

const ICONS = {
    reporter_message: UserRound,
    handler_message: MessageSquareText,
    internal_note: EyeOff,
    status_change: History,
    assignment_change: History,
    recusal: History,
} as const;

const LABELS: Record<CaseEntryRow['kind'], string> = {
    reporter_message: 'Mensaje de la persona',
    handler_message: 'Respuesta a la persona',
    internal_note: 'Nota interna (la persona no la ve)',
    status_change: 'Historial',
    assignment_change: 'Historial',
    recusal: 'Historial',
};

/** One entry of the thread: who, when (the person's messages carry a day and no hour) and what. */
export default function CaseEntry({ entry }: { entry: CaseEntryRow }) {
    const Icon = ICONS[entry.kind];
    const history =
        entry.kind === 'status_change' ||
        entry.kind === 'assignment_change' ||
        entry.kind === 'recusal';
    const when =
        entry.when === null ? null : (describeDate(entry.when) ?? entry.when);

    return (
        <li
            className={cn(
                'flex items-start gap-3 rounded-xl px-4 py-3',
                entry.kind === 'internal_note'
                    ? 'bg-warning text-warning-foreground'
                    : 'border bg-card',
            )}
        >
            <Icon aria-hidden className="mt-1 size-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="text-caption text-muted-foreground">
                    {LABELS[entry.kind]} · {when}
                    {entry.time === null ? '' : ` · ${entry.time}`}
                </p>
                <p className="whitespace-pre-line">
                    {entry.author !== null && (
                        <strong className="font-semibold">
                            {entry.author}
                            {history ? ' ' : ': '}
                        </strong>
                    )}
                    {entry.text}
                </p>
            </div>
        </li>
    );
}
