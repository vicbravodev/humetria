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
        <div
            data-test="privacy-demo"
            className="overflow-hidden rounded-3xl border bg-card shadow-raised"
        >
            <div className="flex flex-col gap-3 bg-accent p-[18px] pb-4">
                <p className="text-meta text-muted-foreground">
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
            <div aria-live="polite">
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
            <p className="flex justify-between gap-3 border-t px-[18px] py-3 text-caption text-muted-foreground">
                <span>Datos de ejemplo</span>
                <span className="tabular-nums">{area?.answers} respuestas</span>
            </p>
        </div>
    );
}
