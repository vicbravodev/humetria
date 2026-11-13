import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import { rosterHint } from '@/lib/telework/roster';

const ROOT = join(process.cwd(), 'resources/js');

function sources(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);

        return statSync(path).isDirectory() ? sources(path) : [path];
    });
}

describe('the texts about a person in NOM-037', () => {
    it('does not use a pronoun with the gender of a person it does not know', () => {
        const files = [
            ...sources(join(ROOT, 'components/telework')),
            ...sources(join(ROOT, 'lib/telework')),
            ...sources(join(ROOT, 'pages/telework')),
        ];
        const found = files.filter((file) =>
            /\b(registrarla|registrarlo|pasarla|pasarlo)\b/.test(
                readFileSync(file, 'utf8'),
            ),
        );

        expect(found).toEqual([]);
    });

    it('says to move «a esta persona» to the roster', () => {
        expect(rosterHint('aspirant', true, undefined)).toBe(
            'Tiene un lugar validado y vigente: ya puedes pasar a esta persona al padrón.',
        );
    });
});
