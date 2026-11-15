import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { index } from '@/routes/platform-users';
import type { PlatformUserFilters } from '@/types';

const toQuery = (filters: PlatformUserFilters) => {
    const q = filters.q.trim();

    return q === '' ? {} : { q };
};

/** Finds a user by name or email, in any order of the words and without accents. */
export default function UsersToolbar({
    filters,
}: {
    filters: PlatformUserFilters;
}) {
    const { text, type } = useQueryFilters(index.url(), filters, toQuery);

    return (
        <div role="search">
            <SearchField
                label="Buscar por nombre o correo"
                value={text}
                onChange={type}
                className="w-full md:w-[380px]"
            />
        </div>
    );
}
