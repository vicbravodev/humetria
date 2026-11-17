import HumiInvitation from '@/components/assistant/humi-invitation';
import { useHumiInvitation } from '@/hooks/use-humi-invitation';
import { invitationKey } from '@/lib/assistant/invitation-store';
import { invitationQuestion } from '@/lib/assistant/page-context';
import { index as assistantPage } from '@/routes/tenant/assistant';

/**
 * Where the closed bubble invites: a polite announcement that is always there (so a screen reader hears what arrives in
 * it, without moving the focus) and the invitation itself while it shows. «Preguntarle a Humi» opens Humi with the
 * question of the page written in its box, never sent.
 */
export default function HumiInvitationSlot({
    slug,
    userId,
    path,
    humiOpen,
    onAsk,
}: {
    slug: string;
    userId: number;
    path: string;
    humiOpen: boolean;
    onAsk: (question: string | null) => void;
}) {
    const { invitation, dismiss, notNow, neverAgain, accept } =
        useHumiInvitation({
            slug,
            storageKey: invitationKey(userId, slug),
            path,
            humiOpen,
        });

    return (
        <>
            <p role="status" className="sr-only">
                {invitation === null ? '' : `Humi: ${invitation.message}`}
            </p>
            {invitation !== null && (
                <HumiInvitation
                    key={invitation.page}
                    message={invitation.message}
                    assistantHref={assistantPage.url(slug)}
                    onAccept={() => {
                        accept();
                        onAsk(invitationQuestion(invitation.page));
                    }}
                    onNotNow={notNow}
                    onNeverAgain={neverAgain}
                    onClose={dismiss}
                />
            )}
        </>
    );
}
