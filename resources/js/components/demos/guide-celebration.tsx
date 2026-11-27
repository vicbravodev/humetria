import HumiAvatar from '@/components/humetria/humi-avatar';

/** The line that celebrates the step just done or the end of the session; always there, so it is announced politely. */
export default function GuideCelebration({ text }: { text: string | null }) {
    return (
        <div role="status" className="empty:hidden">
            {text !== null && (
                <p className="flex items-center gap-2.5 rounded-lg bg-accent px-3 py-2.5 text-sm text-accent-foreground">
                    <HumiAvatar
                        size={32}
                        expression="listo"
                        className="bg-card"
                    />
                    {text}
                </p>
            )}
        </div>
    );
}
