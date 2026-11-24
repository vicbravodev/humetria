import { Copy, Download, Smartphone } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useInstallPrompt } from '@/hooks/use-install-prompt';
import { guideKind, readPlatform } from '@/lib/pwa/platform';

const TITLES = {
    iphone: 'Instale la app en su teléfono',
    android: 'Instale la app en su teléfono',
    'in-app': 'Ábralo en Safari o Chrome',
} as const;

const IPHONE_STEPS = [
    'Toque Compartir.',
    'Elija «Agregar a pantalla de inicio».',
    'Toque «Agregar».',
] as const;

/**
 * How to put the app on the phone, short and at the end of a screen: the steps of an iPhone, the button (or the menu) of an
 * Android, or, in the browser inside a mail or a social network, which cannot install it, «Ábralo en Safari o Chrome» with the
 * link to copy. It says nothing once the app is installed or on a computer. No Inertia: it also shows where there is none.
 */
export default function InstallGuide() {
    const headingId = useId();
    const [platform] = useState(() => readPlatform(window));
    const prompt = useInstallPrompt();
    const [copied, copy] = useClipboard();
    const kind = prompt.installed ? 'hidden' : guideKind(platform);

    if (kind === 'hidden') {
        return null;
    }

    return (
        <section
            aria-labelledby={headingId}
            className="flex flex-col gap-3 card p-4"
        >
            <div className="flex items-center gap-3.5">
                <span
                    aria-hidden
                    className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary"
                >
                    <Smartphone className="size-5" />
                </span>
                <h2 id={headingId} className="text-base font-semibold">
                    {TITLES[kind]}
                </h2>
            </div>
            {kind === 'iphone' && (
                <ol className="flex list-decimal flex-col gap-1 pl-6 text-base">
                    {IPHONE_STEPS.map((step) => (
                        <li key={step}>{step}</li>
                    ))}
                </ol>
            )}
            {kind === 'android' &&
                (prompt.available ? (
                    <Button
                        size="lg"
                        className="w-full text-base"
                        onClick={prompt.install}
                    >
                        <Download aria-hidden className="size-5" />
                        Instalar la app
                    </Button>
                ) : (
                    <p className="text-base">
                        Abra el menú (⋮) del navegador y elija «Instalar app».
                    </p>
                ))}
            {kind === 'in-app' && (
                <>
                    <p className="text-base text-muted-foreground">
                        Este navegador no puede instalar la app. Copie el enlace
                        y péguelo en Safari o en Chrome.
                    </p>
                    <Button
                        variant="outline"
                        size="lg"
                        className="w-full text-base"
                        onClick={() => copy(window.location.href)}
                    >
                        <Copy aria-hidden className="size-5" />
                        {copied === null
                            ? 'Copiar el enlace'
                            : 'Enlace copiado'}
                    </Button>
                </>
            )}
            {kind !== 'in-app' && (
                <p className="text-base text-muted-foreground">
                    El ícono abre Marcar.
                </p>
            )}
        </section>
    );
}
