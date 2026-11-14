import { usePage } from '@inertiajs/react';
import LandingFooter from '@/components/landing/landing-footer';
import PrivacyNoticeArticle from '@/components/landing/privacy-notice-article';
import { privacySections } from '@/components/landing/privacy-notice-content';
import PrivacyNoticeHeader from '@/components/landing/privacy-notice-header';
import SeoHead from '@/components/landing/seo-head';
import { home } from '@/routes';
import type { Seo } from '@/types';

/** The notice of privacy of the public site: fixed text, with the controller's legal name and address from the server. */
export default function PrivacyNotice({
    legalName,
    legalAddress,
    seo,
}: {
    legalName: string | null;
    legalAddress: string | null;
    seo: Seo;
}) {
    const { support } = usePage().props;
    const sections = privacySections({
        legalName,
        legalAddress,
        contact: support.email,
    });

    return (
        <>
            <SeoHead seo={seo} />
            <PrivacyNoticeHeader />
            <main>
                <PrivacyNoticeArticle sections={sections} />
            </main>
            <LandingFooter sectionBase={home().url} />
        </>
    );
}
