import { Link } from '@inertiajs/react';
import { ListChecks, Users } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import type { CenterNextStepData } from '@/types';

/** What a center lacks before it can start: its Comisión, then a published list (criterio C-17), with the way there. */
export default function CenterNextStep({ step }: { step: CenterNextStepData }) {
    return (
        <EmptyState
            icon={step.kind === 'committee' ? Users : ListChecks}
            title={step.title}
            {...(step.href === null
                ? {}
                : {
                      action: (
                          <Button asChild>
                              <Link href={step.href}>{step.cta}</Link>
                          </Button>
                      ),
                  })}
        >
            {step.body}
        </EmptyState>
    );
}
