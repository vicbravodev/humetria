import { describe, expect, it } from 'vitest';
import { channelsPayload, initialChoice, needsConsent } from '@/lib/channels';
import type { CompanyChannelsPage } from '@/types';

const page = (
    over: Partial<CompanyChannelsPage> = {},
): CompanyChannelsPage => ({
    channels: [
        {
            channel: 'mail',
            enabled: true,
            preference: 1,
            monthlyCap: null,
            consentVersion: null,
        },
        {
            channel: 'whatsapp',
            enabled: false,
            preference: 2,
            monthlyCap: null,
            consentVersion: null,
        },
    ],
    whatsAppAvailable: true,
    hasPlan: true,
    consent: { version: '2026-11-18', text: 'Acepto' },
    costNotice: 'Cuesta',
    whatsAppQuota: { used: 0, quota: 1000 },
    isDemo: false,
    ...over,
});

describe('initialChoice', () => {
    it('starts from what the company has, with the words not accepted', () => {
        expect(initialChoice(page())).toEqual({
            mail: true,
            whatsapp: false,
            preferred: 'mail',
            cap: '',
            accepted: false,
        });
    });

    it('starts with the channel that goes first and the cap the owner set', () => {
        const choice = initialChoice(
            page({
                channels: [
                    {
                        channel: 'whatsapp',
                        enabled: true,
                        preference: 1,
                        monthlyCap: 300,
                        consentVersion: '2026-11-18',
                    },
                    {
                        channel: 'mail',
                        enabled: true,
                        preference: 2,
                        monthlyCap: null,
                        consentVersion: null,
                    },
                ],
            }),
        );

        expect(choice).toEqual({
            mail: true,
            whatsapp: true,
            preferred: 'whatsapp',
            cap: '300',
            accepted: false,
        });
    });
});

describe('needsConsent', () => {
    it('asks for the words when WhatsApp is turned on', () => {
        expect(
            needsConsent(page(), {
                mail: true,
                whatsapp: true,
                preferred: 'mail',
                cap: '',
                accepted: false,
            }),
        ).toBe(true);
    });

    it('does not ask while WhatsApp stays off', () => {
        expect(
            needsConsent(page(), {
                mail: true,
                whatsapp: false,
                preferred: 'mail',
                cap: '',
                accepted: false,
            }),
        ).toBe(false);
    });

    it('does not ask when the current version was already accepted, and asks again for another', () => {
        const accepted = page({
            channels: [
                {
                    channel: 'mail',
                    enabled: true,
                    preference: 1,
                    monthlyCap: null,
                    consentVersion: null,
                },
                {
                    channel: 'whatsapp',
                    enabled: true,
                    preference: 2,
                    monthlyCap: null,
                    consentVersion: '2026-11-18',
                },
            ],
        });
        const old = page({
            channels: [
                {
                    channel: 'mail',
                    enabled: true,
                    preference: 1,
                    monthlyCap: null,
                    consentVersion: null,
                },
                {
                    channel: 'whatsapp',
                    enabled: true,
                    preference: 2,
                    monthlyCap: null,
                    consentVersion: '2025-01-01',
                },
            ],
        });
        const choice = {
            mail: true,
            whatsapp: true,
            preferred: 'mail' as const,
            cap: '',
            accepted: false,
        };

        expect(needsConsent(accepted, choice)).toBe(false);
        expect(needsConsent(old, choice)).toBe(true);
    });
});

describe('channelsPayload', () => {
    it('sends an explicit yes or no for each channel and a numeric cap', () => {
        expect(
            channelsPayload({
                mail: true,
                whatsapp: true,
                preferred: 'whatsapp',
                cap: ' 250 ',
                accepted: true,
            }),
        ).toEqual({
            mail_enabled: true,
            whatsapp_enabled: true,
            preferred: 'whatsapp',
            whatsapp_monthly_cap: 250,
            consent_accepted: true,
        });
    });

    it('sends no cap when WhatsApp is off or the field is empty', () => {
        expect(
            channelsPayload({
                mail: true,
                whatsapp: false,
                preferred: 'mail',
                cap: '100',
                accepted: false,
            }).whatsapp_monthly_cap,
        ).toBeNull();
        expect(
            channelsPayload({
                mail: true,
                whatsapp: true,
                preferred: 'mail',
                cap: '',
                accepted: true,
            }).whatsapp_monthly_cap,
        ).toBeNull();
    });

    it('never prefers a channel that is off', () => {
        expect(
            channelsPayload({
                mail: true,
                whatsapp: false,
                preferred: 'whatsapp',
                cap: '',
                accepted: false,
            }).preferred,
        ).toBe('mail');
        expect(
            channelsPayload({
                mail: false,
                whatsapp: true,
                preferred: 'mail',
                cap: '',
                accepted: true,
            }).preferred,
        ).toBe('whatsapp');
    });
});
