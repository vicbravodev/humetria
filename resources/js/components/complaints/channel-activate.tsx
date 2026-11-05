import ChannelSettingsForm from '@/components/complaints/channel-settings-form';
import type { ChannelBounds, ChannelSettings } from '@/types';

/** A channel not yet open: the procedure and the deadlines to review, and the button that opens it and designates you. */
export default function ChannelActivate({
    settings,
    bounds,
}: {
    settings: ChannelSettings;
    bounds: ChannelBounds;
}) {
    return (
        <section
            aria-labelledby="activate"
            className="flex flex-col gap-5 card p-6"
        >
            <div className="flex flex-col gap-1">
                <h2 id="activate" className="text-xl leading-7 font-semibold">
                    Activa el canal
                </h2>
                <p className="text-muted-foreground">
                    Revisa cómo se atiende y los plazos. Al activarlo quedas
                    designada o designado para atender las quejas; después
                    puedes sumar a otras personas.
                </p>
            </div>
            <ChannelSettingsForm
                settings={settings}
                bounds={bounds}
                activating
            />
        </section>
    );
}
