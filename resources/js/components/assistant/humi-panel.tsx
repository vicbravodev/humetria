import { useEffect } from 'react';
import HumiComposer from '@/components/assistant/humi-composer';
import HumiHeader from '@/components/assistant/humi-header';
import HumiThread from '@/components/assistant/humi-thread';
import { useHumiChat } from '@/hooks/use-humi-chat';
import type { PageContext } from '@/lib/assistant/chat-request';
import { contextLabel } from '@/lib/assistant/page-context';
import { suggestionsFor } from '@/lib/assistant/suggestions';
import { index as assistantPage } from '@/routes/tenant/assistant';

/** Why the person cannot write: Humi is off for the company, its budget is used, or they wrote too much for now. */
function closedTextOf(reason: string | undefined): string | null {
    if (reason === undefined || reason === 'error') {
        return null;
    }

    return reason === 'rate'
        ? 'Disponible en unos minutos'
        : 'Humi no está disponible';
}

/**
 * Humi in the panel of RH: its header, the conversation and where to write. It is the same inside the docked column, the
 * sheet of a narrow window and the full page; the chat belongs to it, so it starts again when it is opened anew.
 */
export default function HumiPanel({
    slug,
    page,
    fullPage,
    onClose,
    openId = null,
}: {
    slug: string;
    page: PageContext;
    fullPage: boolean;
    onClose: (() => void) | null;
    /** A conversation of the history to open with what was kept of it. */
    openId?: string | null;
}) {
    const chat = useHumiChat(slug, page, openId);
    const { failure, dismiss } = chat;
    const streaming =
        chat.status === 'streaming' || chat.status === 'submitted';

    useEffect(() => {
        if (failure?.reason !== 'rate' || failure.retryAfter === null) {
            return;
        }

        const timer = window.setTimeout(dismiss, failure.retryAfter * 1000);

        return () => window.clearTimeout(timer);
    }, [failure, dismiss]);

    return (
        <div className="flex h-full min-h-0 flex-col bg-background">
            <HumiHeader
                context={contextLabel(page)}
                fullPageHref={fullPage ? null : assistantPage.url(slug)}
                onNew={chat.reset}
                onClose={onClose}
            />
            <HumiThread
                chat={chat}
                suggestions={suggestionsFor(page)}
                onPick={chat.send}
            />
            <HumiComposer
                streaming={streaming}
                closedText={closedTextOf(failure?.reason)}
                onSend={chat.send}
                onStop={chat.stop}
            />
        </div>
    );
}
