import { useEffect, useState } from 'react';
import HumiComposer from '@/components/assistant/humi-composer';
import HumiHeader from '@/components/assistant/humi-header';
import HumiThread from '@/components/assistant/humi-thread';
import { useHumiChat } from '@/hooks/use-humi-chat';
import { useHumiLiveFacts } from '@/hooks/use-humi-live-facts';
import { useHumiMood } from '@/hooks/use-humi-mood';
import { useHumiSeenPage } from '@/hooks/use-humi-seen-page';
import type { PageContext } from '@/lib/assistant/chat-request';
import { contextLabel } from '@/lib/assistant/page-context';
import { startersFor } from '@/lib/assistant/suggestions';
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
    initialText = null,
}: {
    slug: string;
    page: PageContext;
    fullPage: boolean;
    onClose: (() => void) | null;
    /** A conversation of the history to open with what was kept of it. */
    openId?: string | null;
    /** A question to leave written in the box, for the person to send. */
    initialText?: string | null;
}) {
    const { seen, drop } = useHumiSeenPage(page);
    const chat = useHumiChat(slug, seen, openId);
    const { failure, dismiss } = chat;
    const streaming =
        chat.status === 'streaming' || chat.status === 'submitted';
    const [writing, setWriting] = useState(false);
    const facts = useHumiLiveFacts(slug);
    const expression = useHumiMood(streaming, failure !== null, writing);

    useEffect(() => {
        if (failure?.reason !== 'rate' || failure.retryAfter === null) {
            return;
        }

        const timer = window.setTimeout(dismiss, failure.retryAfter * 1000);

        return () => window.clearTimeout(timer);
    }, [failure, dismiss]);

    return (
        <div
            data-full={fullPage}
            className="group/humi flex h-full min-h-0 flex-col bg-background"
        >
            <HumiHeader
                expression={expression}
                context={contextLabel(seen)}
                fullPageHref={fullPage ? null : assistantPage.url(slug)}
                onNew={fullPage ? null : chat.reset}
                onClose={onClose}
            />
            <HumiThread
                chat={chat}
                starters={startersFor(seen, facts)}
                onPick={chat.send}
            />
            <HumiComposer
                key={initialText ?? ''}
                initialText={initialText ?? ''}
                streaming={streaming}
                closedText={closedTextOf(failure?.reason)}
                onSend={chat.send}
                onStop={chat.stop}
                onWriting={setWriting}
                context={seen === null ? null : contextLabel(seen)}
                onDropContext={drop}
            />
        </div>
    );
}
