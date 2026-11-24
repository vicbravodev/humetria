import AreaRow from '@/components/tenancy/area-row';
import { SECTIONS, areasFor } from '@/lib/permission-areas';
import type { PermissionGroup } from '@/types';

/**
 * The areas of the roles screen in the sidebar's groups (General, Cumple la norma, Escucha a tu gente, Ordena tu operación),
 * one card each. `held` is every permission in play (a person's role included); `onChange` gets them back with one area
 * changed, and is missing when the screen is only read.
 */
export default function AreaSections({
    groups,
    held,
    base,
    onChange,
}: {
    groups: PermissionGroup[];
    held: string[];
    /** A person's role: its levels are the floor. */
    base?: string[];
    onChange: ((next: string[]) => void) | undefined;
}) {
    const items = areasFor(groups);

    return (
        <div className="flex flex-col gap-7">
            {SECTIONS.map((section) => {
                const rows = items.filter(
                    (item) => item.area.section === section.key,
                );

                return (
                    rows.length > 0 && (
                        <section
                            key={section.key}
                            aria-labelledby={`areas-${section.key}`}
                            className="flex flex-col gap-2.5"
                        >
                            <h2
                                id={`areas-${section.key}`}
                                className="text-lg font-semibold"
                            >
                                {section.label}
                            </h2>
                            <ul className="divide-y overflow-hidden card">
                                {rows.map((item) => (
                                    <AreaRow
                                        key={item.area.key}
                                        item={item}
                                        held={held}
                                        context={{ groups, base }}
                                        onChange={onChange}
                                    />
                                ))}
                            </ul>
                        </section>
                    )
                );
            })}
        </div>
    );
}
