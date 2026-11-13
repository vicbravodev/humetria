import type { ModulesChange } from '@/types';

/** The tools a plan being typed switches off (and from when) and on: said before it is saved. */
export default function ModulesNote({ modules }: { modules: ModulesChange }) {
    return (
        <>
            {modules.removed.length > 0 && (
                <p
                    data-test="plan-modules-removed"
                    className="rounded-lg bg-accent px-3 py-2 text-sm"
                >
                    Se quitan el {modules.removedOn}:{' '}
                    {modules.removed.join(', ')}.
                </p>
            )}
            {modules.added.length > 0 && (
                <p data-test="plan-modules-added" className="text-sm">
                    Se activan hoy: {modules.added.join(', ')}.
                </p>
            )}
        </>
    );
}
