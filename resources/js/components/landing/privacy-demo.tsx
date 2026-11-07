import { AnimatePresence, m } from 'motion/react';
import { useState } from 'react';
import PrivacyAreaButton from '@/components/landing/privacy-area-button';
import PrivacyResults from '@/components/landing/privacy-results';
import { PRIVACY_AREAS } from '@/components/landing/privacy-areas';
import type { PrivacyAreaKey } from '@/components/landing/privacy-areas';
import { EASE_OUT } from '@/components/landing/landing-motion';

/** Filter as HR would: every filter shrinks the group, and below 5 answers the group disappears. */
export default function PrivacyDemo() {
    const [chosen, setChosen] = useState<PrivacyAreaKey>('planta');
    const area = PRIVACY_AREAS.find((candidate) => candidate.key === chosen);

    return (
        <div data-test="privacy-demo" className="card rounded-2xl shadow-float">
            <div className="flex flex-col gap-3 border-b p-5">
                <p className="text-sm text-muted-foreground">
                    Pruébalo: filtra los resultados de Planta Querétaro
                </p>
                <div className="flex flex-wrap gap-2">
                    {PRIVACY_AREAS.map((option) => (
                        <PrivacyAreaButton
                            key={option.key}
                            area={option}
                            pressed={option.key === chosen}
                            onPress={() => setChosen(option.key)}
                        />
                    ))}
                </div>
            </div>
            <div className="min-h-[264px] p-5" aria-live="polite">
                <AnimatePresence mode="wait" initial={false}>
                    <m.div
                        key={chosen}
                        initial={{ opacity: 0, filter: 'blur(4px)' }}
                        animate={{ opacity: 1, filter: 'blur(0px)' }}
                        exit={{ opacity: 0, filter: 'blur(4px)' }}
                        transition={{ duration: 0.18, ease: EASE_OUT }}
                    >
                        {area === undefined ? null : (
                            <PrivacyResults area={area} />
                        )}
                    </m.div>
                </AnimatePresence>
            </div>
            <p className="border-t px-5 py-3 text-caption text-muted-foreground">
                Datos de ejemplo
            </p>
        </div>
    );
}
