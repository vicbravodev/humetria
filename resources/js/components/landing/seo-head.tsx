import { Head } from '@inertiajs/react';
import { cspNonce } from '@/lib/csp-nonce';
import type { Seo } from '@/types';

// The commit this bundle was built from (the Dockerfile sets VITE_BUILD_REVISION). A local build has none and prints no tag.
const buildRevision: string = import.meta.env['VITE_BUILD_REVISION'] || '';

/**
 * The tags of the head of a public page, all from `seo`. Each carries the `head-key` that resources/views/partials/seo-fallback.blade.php
 * prints as data-inertia, so when the app starts the client keeps or replaces each tag instead of adding a second one.
 */
export default function SeoHead({ seo }: { seo: Seo }) {
    const { image } = seo;

    return (
        <Head>
            <title>{seo.title}</title>
            <meta
                head-key="description"
                name="description"
                content={seo.description}
            />
            <link head-key="canonical" rel="canonical" href={seo.canonical} />
            <meta head-key="og-url" property="og:url" content={seo.canonical} />
            <meta head-key="og-type" property="og:type" content="website" />
            <meta
                head-key="og-site-name"
                property="og:site_name"
                content={seo.siteName}
            />
            <meta head-key="og-title" property="og:title" content={seo.title} />
            <meta
                head-key="og-description"
                property="og:description"
                content={seo.description}
            />
            <meta
                head-key="og-locale"
                property="og:locale"
                content={seo.locale}
            />
            <meta head-key="og-image" property="og:image" content={image.url} />
            <meta
                head-key="og-image-width"
                property="og:image:width"
                content={String(image.width)}
            />
            <meta
                head-key="og-image-height"
                property="og:image:height"
                content={String(image.height)}
            />
            <meta
                head-key="og-image-alt"
                property="og:image:alt"
                content={image.alt}
            />
            <meta
                head-key="twitter-card"
                name="twitter:card"
                content="summary_large_image"
            />
            <meta
                head-key="twitter-title"
                name="twitter:title"
                content={seo.title}
            />
            <meta
                head-key="twitter-description"
                name="twitter:description"
                content={seo.description}
            />
            <meta
                head-key="twitter-image"
                name="twitter:image"
                content={image.url}
            />
            {buildRevision === '' ? null : (
                <meta
                    head-key="build-revision"
                    name="build-revision"
                    content={buildRevision}
                />
            )}
            <script
                head-key="json-ld"
                type="application/ld+json"
                {...(cspNonce === undefined ? {} : { nonce: cspNonce })}
                dangerouslySetInnerHTML={{ __html: seo.jsonLd }}
            />
        </Head>
    );
}
