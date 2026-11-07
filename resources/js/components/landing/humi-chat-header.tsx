import LandingHumi from '@/components/landing/landing-humi';

/** The top of Humi's panel: its face and what it is. */
export default function HumiChatHeader() {
    return (
        <div className="flex items-center gap-3 border-b px-5 py-3.5">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent">
                <LandingHumi size={26} />
            </span>
            <span>
                <span className="block font-semibold">Humi</span>
                <span className="block text-sm text-muted-foreground">
                    Asistente de Humetría
                </span>
            </span>
        </div>
    );
}
