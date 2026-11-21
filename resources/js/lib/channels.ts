import type { CompanyChannelsPage } from '@/types';

/** What is sent when the form is saved. */
export type ChannelsPayload = {
    mail_enabled: boolean;
    whatsapp_enabled: boolean;
    preferred: 'mail' | 'whatsapp';
    whatsapp_monthly_cap: number | null;
    consent_accepted: boolean;
};

/** What the owner has chosen in the form. */
export type ChannelChoice = {
    mail: boolean;
    whatsapp: boolean;
    preferred: 'mail' | 'whatsapp';
    cap: string;
    accepted: boolean;
};

/** Where the form starts: what the company has now. */
export function initialChoice(page: CompanyChannelsPage): ChannelChoice {
    const mail = page.channels.find((row) => row.channel === 'mail');
    const whatsapp = page.channels.find((row) => row.channel === 'whatsapp');
    const first = page.channels[0]?.channel ?? 'mail';

    return {
        mail: mail?.enabled ?? true,
        whatsapp: whatsapp?.enabled ?? false,
        preferred: first,
        cap:
            whatsapp?.monthlyCap === null || whatsapp === undefined
                ? ''
                : String(whatsapp.monthlyCap),
        accepted: false,
    };
}

/** Whether the owner has to accept the words: WhatsApp is on in the form, and it was off or accepted another version. */
export function needsConsent(
    page: CompanyChannelsPage,
    choice: ChannelChoice,
): boolean {
    const current = page.channels.find((row) => row.channel === 'whatsapp');

    return (
        choice.whatsapp &&
        !(
            current?.enabled === true &&
            current.consentVersion === page.consent.version
        )
    );
}

/** What is sent: explicit yes and no for each channel, a preferred channel that is on, and the cap or nothing. */
export function channelsPayload(choice: ChannelChoice): ChannelsPayload {
    const preferred =
        choice.preferred === 'whatsapp' && !choice.whatsapp
            ? 'mail'
            : choice.preferred;
    const cap = choice.cap.trim();

    return {
        mail_enabled: choice.mail,
        whatsapp_enabled: choice.whatsapp,
        preferred: choice.mail || !choice.whatsapp ? preferred : 'whatsapp',
        whatsapp_monthly_cap:
            choice.whatsapp && cap !== '' ? Number(cap) : null,
        consent_accepted: choice.accepted,
    };
}
