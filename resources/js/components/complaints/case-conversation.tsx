import type { ReactNode } from 'react';
import Pagination from '@/components/humetria/pagination';
import { shortDay } from '@/lib/complaints/deadline';
import type { CaseEntryRow, Paginated } from '@/types';

/**
 * «Conversación con la persona» (board V4-Quejas-Caso): what the person reads with their reference and key — their messages
 * and the answers — oldest first like any conversation, and below it what comes next. The internal notes never go here.
 */
export default function CaseConversation({
    messages,
    received,
    children,
}: {
    messages: Paginated<CaseEntryRow>;
    /** What the system told the person when it came; it opens the conversation on its oldest page. */
    received: string;
    children: ReactNode;
}) {
    const oldest = messages.current_page >= messages.last_page;
    const shown = [...messages.data].reverse();

    return (
        <section
            aria-labelledby="case-conversation"
            className="flex flex-col overflow-hidden card"
        >
            <header className="flex flex-col gap-1 border-b px-6 py-5">
                <h2 id="case-conversation" className="text-lg font-semibold">
                    Conversación con la persona
                </h2>
                <p className="text-sm text-muted-foreground">
                    La lee entrando con su referencia y su clave. No escribas
                    nada que la identifique.
                </p>
            </header>
            <ol className="flex flex-col divide-y">
                {oldest && (
                    <li className="flex flex-col gap-1 px-6 py-4 text-sm">
                        <span className="text-muted-foreground">Sistema</span>
                        <span>{received}</span>
                    </li>
                )}
                {shown.map((entry) => (
                    <li
                        key={entry.id}
                        className="flex flex-col gap-1 px-6 py-4"
                    >
                        <span className="text-sm text-muted-foreground">
                            {entry.kind === 'reporter_message'
                                ? 'La persona'
                                : (entry.author ?? 'Quien atiende')}
                            {entry.when !== null &&
                                ` · ${shortDay(entry.when)}`}
                            {entry.time !== null && ` · ${entry.time}`}
                        </span>
                        <span className="whitespace-pre-line">
                            {entry.text}
                        </span>
                    </li>
                ))}
            </ol>
            {messages.last_page > 1 && (
                <div className="px-6 pb-4">
                    <Pagination
                        page={messages}
                        label="Páginas de la conversación"
                        previous="Ver mensajes más recientes"
                        next="Ver mensajes anteriores"
                        pinned={false}
                    />
                </div>
            )}
            <div className="flex flex-col gap-4 border-t bg-muted px-6 py-5">
                {children}
            </div>
        </section>
    );
}
