import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { builtinModules } from 'node:module';
import { test } from 'node:test';

const bundle = new URL('../../../bootstrap/ssr/', import.meta.url);

// The `ssr` image carries no node_modules: everything the bundle needs is inside it (ssr.noExternal).
void test('the bundle imports only Node built-ins and its own files', () => {
    const files = [
        'app.js',
        ...readdirSync(new URL('assets/', bundle)).map(
            (name) => `assets/${name}`,
        ),
    ].filter((name) => name.endsWith('.js'));
    const specifiers = files.flatMap((file) =>
        [
            ...readFileSync(new URL(file, bundle), 'utf8').matchAll(
                /^(?:import|export)\b[^"'\n]*?["']([^"']+)["']/gm,
            ),
        ].map((match) => match[1]),
    );
    const outside = [...new Set(specifiers)].filter(
        (name) =>
            !name.startsWith('.') &&
            !name.startsWith('node:') &&
            !builtinModules.includes(name),
    );

    assert.ok(specifiers.length > 0, 'the bundle has imports');
    assert.deepEqual(outside, []);
});
