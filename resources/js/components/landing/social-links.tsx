import { usePage } from '@inertiajs/react';
import { Facebook, Instagram, Linkedin } from 'lucide-react';

const networks = {
    linkedin: { label: 'LinkedIn', Icon: Linkedin },
    facebook: { label: 'Facebook', Icon: Facebook },
    instagram: { label: 'Instagram', Icon: Instagram },
};

/** The company's profiles that are configured (SOCIAL_*_URL); nothing at all while none is. A plain <a>, never a <Link>: the landing lives in its own document. */
export default function SocialLinks() {
    const { social } = usePage().props;

    if (social.length === 0) {
        return null;
    }

    return (
        <ul aria-label="Redes sociales" className="flex gap-1">
            {social.map(({ network, url }) => {
                const { label, Icon } = networks[network];

                return (
                    <li key={network}>
                        <a
                            href={url}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={`Humetría en ${label}`}
                            data-test={`social-${network}`}
                            className="inline-flex size-11 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        >
                            <Icon aria-hidden className="size-5" />
                        </a>
                    </li>
                );
            })}
        </ul>
    );
}
