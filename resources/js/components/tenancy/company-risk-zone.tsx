import { useState } from 'react';
import RiskRestrictionDialog from '@/components/tenancy/risk-restriction-dialog';
import { Button } from '@/components/ui/button';
import type { CompanyDetail } from '@/types';

const STOPS: ('suspended' | 'archived')[] = ['suspended', 'archived'];

/** What stops a company, apart and in the destructive color: each asks the reason and its name typed. Only the superadmin. */
export default function CompanyRiskZone({
    company,
}: {
    company: CompanyDetail;
}) {
    const [open, setOpen] = useState<'suspended' | 'archived' | null>(null);
    const choices = STOPS.filter(
        (restriction) => company.status !== restriction,
    );

    return (
        <section
            aria-labelledby="company-risk"
            className="card border-destructive"
        >
            <h2
                id="company-risk"
                className="border-b px-5 py-4 text-base font-semibold text-destructive"
            >
                Zona de riesgo
            </h2>
            <div className="grid gap-2.5 px-5 py-4">
                {choices.map((restriction) => (
                    <div key={restriction}>
                        <Button
                            variant="outline"
                            className="w-full border-destructive text-destructive"
                            onClick={() => setOpen(restriction)}
                        >
                            {restriction === 'suspended'
                                ? 'Suspender empresa…'
                                : 'Archivar empresa…'}
                        </Button>
                        <RiskRestrictionDialog
                            company={company}
                            restriction={restriction}
                            open={open === restriction}
                            onOpenChange={(isOpen) =>
                                setOpen(isOpen ? restriction : null)
                            }
                        />
                    </div>
                ))}
                <p className="text-caption text-muted-foreground">
                    Cada una pide el motivo y escribir el nombre de la empresa.
                </p>
            </div>
        </section>
    );
}
