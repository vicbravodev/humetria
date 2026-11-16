import { DoorClosed, Users } from 'lucide-react';
import Logo from '@/components/humetria/logo';
import StatusMessage from '@/components/humetria/status-message';

export type DoorState = 'closed' | 'full';

const MESSAGES = {
    closed: {
        icon: DoorClosed,
        title: 'Este código ya no está abierto',
        body: 'La demostración en vivo no está recibiendo personas con este código. Pídale a quien presenta el código vigente.',
    },
    full: {
        icon: Users,
        title: 'La sala está llena',
        body: 'Ya no quedan trabajadores de demostración libres. Pídale a quien presenta que libere lugares; en un momento podrá volver a escanear el código.',
    },
} as const satisfies Record<DoorState, object>;

/** What the door of a live demo says when it lets nobody in. For the audience: it speaks «usted». */
export default function DoorMessage({ state }: { state: DoorState }) {
    const message = MESSAGES[state];

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <header className="px-4 py-7 sm:px-10">
                <Logo className="h-[26px]" />
            </header>
            <main className="flex flex-1 items-center justify-center px-4 pt-6 pb-30 sm:px-6">
                <StatusMessage
                    icon={message.icon}
                    caption="Demostración en vivo"
                    title={message.title}
                >
                    {message.body}
                </StatusMessage>
            </main>
        </div>
    );
}
