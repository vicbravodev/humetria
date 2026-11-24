import { describe, expect, it } from 'vitest';
import { FAMILIES } from '@/components/landing/landing-families';
import { TOOLS } from '@/components/landing/landing-tools';
import { PATHS } from '@/components/humetria/tool-icon';
import publicNav from '@/lib/public-nav.json';

// The Blade header of the blog draws its tools menu from public-nav.json; the React one from the landing's own data.
// If the two ever disagree this fails, so the header is the same page on the landing and in the blog.
describe('public-nav.json', () => {
    it('lists the tools as the landing does: same names, hints, families and icons, in the same order', () => {
        const fromLanding = FAMILIES.flatMap((family) =>
            family.tools.map((key) => {
                const tool = TOOLS.find((candidate) => candidate.key === key);

                return {
                    key,
                    name: tool?.name,
                    hint: tool?.hint,
                    family: family.key,
                    paths: PATHS[key],
                };
            }),
        );

        expect(
            publicNav.tools.map(({ key, name, hint, family, paths }) => ({
                key,
                name,
                hint,
                family,
                paths,
            })),
        ).toEqual(fromLanding);
    });

    it('names the three families as the landing does', () => {
        expect(publicNav.families).toEqual(
            FAMILIES.map((family) => ({
                key: family.key,
                name: family.name,
                hint: family.hint,
            })),
        );
    });
});
