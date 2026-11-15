import { TriangleAlert } from 'lucide-react';
import type { ModulesChange } from '@/types';

/** The tools a plan being typed switches off (and from when) and on: said before it is saved. */
export default function ModulesNote({ modules }: { modules: ModulesChange }) {
    return (
        <>
            {modules.removed.length > 0 && (
                <div
                    role="status"
                    data-test="plan-modules-removed"
                    className="flex items-start gap-2.5 rounded-lg bg-warning px-3 py-2.5 text-sm text-warning-foreground"
                >
                    <TriangleAlert
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <p>
                        <strong className="font-semibold">
                            Se quitan el {modules.removedOn}:
                        </strong>{' '}
                        {modules.removed.join(', ')}.
                    </p>
                </div>
            )}
            {modules.added.length > 0 && (
                <p data-test="plan-modules-added" className="text-sm">
                    Se activan hoy: {modules.added.join(', ')}.
                </p>
            )}
        </>
    );
}
