// Builds the package /design-sync reads (.ds-sync/pkg) from the app's own design-system components:
// resources/js/components/ui (shadcn) and resources/js/components/humetria. Humetría is an app, not a library, so this
// script plays the part of a library build. It only reads the app: no file under resources/ is written.
//
//   dist/index.js     ESM bundle of every PascalCase export of those two folders (react external)
//   dist/types/       declarations emitted by tsc from the same entry
//   dist/styles.css   resources/css/app.css compiled by Tailwind over the app's sources and .design-sync/previews
//   fonts/            Figtree, as the app's own build self-hosts it (public/build)
//
// Needs `composer worktree:setup` first (vendor, .env, node_modules and `npm run build`: Wayfinder's generated routes
// that resources/js/lib/results.ts imports, and the self-hosted fonts). Run from the repo root:
//   node .design-sync/build-dist.mjs
import { execFileSync } from 'node:child_process';
import {
    cpSync,
    existsSync,
    mkdirSync,
    readdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { createRequire } from 'node:module';
import { basename, join, resolve } from 'node:path';

const ROOT = process.cwd();
const OUT = resolve('.ds-sync/pkg');
const JS = resolve('resources/js');
const DIRS = ['components/ui', 'components/humetria'];
// The frames: what surrounds every page of the panel and of the worker's portal (design-system.md, «Marcos»).
const FRAMES = [
    'layouts/app/app-sidebar-layout.tsx',
    'components/app-sidebar.tsx',
    'components/app-sidebar-header.tsx',
    'components/app-breadcrumbs.tsx',
    'components/mobile-tab-bar.tsx',
    'components/company-mark.tsx',
    'components/person-frame.tsx',
    'components/worker-access/worker-frame.tsx',
    'components/worker-access/portal-tab-bar.tsx',
];
const requireDs = createRequire(resolve('.ds-sync/package.json'));
const requireApp = createRequire(resolve('package.json'));

if (!existsSync(resolve('resources/js/routes'))) {
    console.error(
        '[BUILD_DIST] resources/js/routes is missing: run `composer worktree:setup` (it runs `npm run build`, which generates Wayfinder).',
    );
    process.exit(1);
}

rmSync(OUT, { recursive: true, force: true });
mkdirSync(join(OUT, 'src'), { recursive: true });

// -- 1. entry: every PascalCase value export of the two folders --------------------------------------------------------
const seen = new Map();
const lines = [];
const files = [
    ...DIRS.flatMap((dir) =>
        readdirSync(join(JS, dir))
            .filter((f) => f.endsWith('.tsx'))
            .sort()
            .map((f) => `${dir}/${f}`),
    ),
    ...FRAMES,
];
{
    for (const rel of files) {
        const text = readFileSync(join(JS, rel), 'utf8');
        const spec = `@/${rel.replace(/\.tsx$/, '')}`;
        const named = new Set();
        let def = null;
        const d = /export\s+default\s+function\s+([A-Z]\w*)/.exec(text);
        if (d) def = d[1];
        for (const m of text.matchAll(
            /export\s+(?:function|const)\s+([A-Z]\w*)/g,
        ))
            named.add(m[1]);
        for (const m of text.matchAll(/export\s*\{([^}]*)\}/g)) {
            for (const part of m[1].split(',')) {
                const p = part.trim();
                if (!p || p.startsWith('type ')) continue;
                const name = p
                    .split(/\s+as\s+/)
                    .pop()
                    .trim();
                if (/^[A-Z]/.test(name)) named.add(name);
            }
        }
        const take = (name) => {
            if (seen.has(name)) {
                console.error(
                    `! ${name} in ${spec} skipped: already exported from ${seen.get(name)}`,
                );
                return false;
            }
            seen.set(name, spec);
            return true;
        };
        if (def && take(def))
            lines.push(`export { default as ${def} } from '${spec}';`);
        const keep = [...named].filter((n) => n !== def && take(n));
        if (keep.length)
            lines.push(`export { ${keep.join(', ')} } from '${spec}';`);
    }
}
const entry = join(OUT, 'src/index.ts');
writeFileSync(entry, lines.join('\n') + '\n');
console.error(
    `  entry: ${seen.size} exports from ${DIRS.join(' and ')} and ${FRAMES.length} frame files`,
);

// -- 2. bundle --------------------------------------------------------------------------------------------------------
const esbuild = requireDs('esbuild');
const flagsDir = resolve('node_modules/country-flag-icons/3x2');
const appPlugin = {
    name: 'humetria-app',
    setup(build) {
        // The app's `@/` alias (vite.config.ts, tsconfig.json paths).
        build.onResolve({ filter: /^@\// }, (args) =>
            build.resolve('./' + args.path.slice(2), {
                resolveDir: JS,
                kind: args.kind,
            }),
        );
        // Vite's import.meta.glob of the phone field's flags: each flag inlined as a data URL, loaded the same lazy way.
        build.onLoad({ filter: /hooks[\\/]use-phone-field\.ts$/ }, (args) => {
            const src = readFileSync(args.path, 'utf8');
            const entries = readdirSync(flagsDir)
                .filter((f) => f.endsWith('.svg'))
                .map((f) => {
                    const url =
                        'data:image/svg+xml;base64,' +
                        readFileSync(join(flagsDir, f)).toString('base64');
                    return `${JSON.stringify('/node_modules/country-flag-icons/3x2/' + f)}: () => Promise.resolve(${JSON.stringify(url)})`;
                });
            const contents = src.replace(
                /import\.meta\.glob<string>\([\s\S]*?\);/,
                `({${entries.join(',\n')}});`,
            );
            return { contents, loader: 'ts' };
        });
        // Inertia's usePage throws outside its app, and the frames and a few pieces read the shared props (tenant, auth,
        // brand, support…). In the bundle every import of '@inertiajs/react' goes through a wrapper whose usePage falls back to
        // `window.HumetriaPage` ({ props, url }); the rest of Inertia is the real one. Inside the app nothing changes.
        const WRAPPER = 'humetria-inertia';
        build.onResolve({ filter: /^@inertiajs\/react$/ }, (args) =>
            args.importer === WRAPPER
                ? undefined
                : { path: WRAPPER, namespace: 'humetria' },
        );
        build.onLoad(
            { filter: /^humetria-inertia$/, namespace: 'humetria' },
            () => ({
                resolveDir: ROOT,
                loader: 'js',
                contents: `export * from '@inertiajs/react';
import { usePage as inertiaUsePage } from '@inertiajs/react';
export function usePage() {
  try { return inertiaUsePage(); }
  catch {
    const page = globalThis.HumetriaPage || {};
    return { component: '', url: page.url || '/', version: null, props: page.props || {}, flash: {}, rememberedState: {} };
  }
}`,
            }),
        );
        // Logo loads /brand/<file>.svg from the app's public/ folder, which a design system page does not serve: the
        // same files, inlined as data URLs.
        build.onLoad(
            { filter: /components[\\/]humetria[\\/]logo\.tsx$/ },
            (args) => {
                const brandDir = resolve('public/brand');
                const map = Object.fromEntries(
                    readdirSync(brandDir)
                        .filter((f) => f.endsWith('.svg'))
                        .map((f) => [
                            f.replace(/\.svg$/, ''),
                            'data:image/svg+xml;base64,' +
                                readFileSync(join(brandDir, f)).toString(
                                    'base64',
                                ),
                        ]),
                );
                const src = readFileSync(args.path, 'utf8');
                const contents =
                    `const BRAND_FILES: Record<string, string> = ${JSON.stringify(map)};\n` +
                    src.replace(
                        /`\/brand\/\$\{(\w+)\}\.svg`/g,
                        'BRAND_FILES[$1]',
                    );
                return { contents, loader: 'tsx' };
            },
        );
    },
};
await esbuild.build({
    entryPoints: [entry],
    outfile: join(OUT, 'dist/index.js'),
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: 'es2022',
    jsx: 'automatic',
    external: ['react', 'react-dom', 'react/jsx-runtime', 'react-dom/client'],
    define: {
        'import.meta.env.SSR': 'false',
        'import.meta.env.DEV': 'false',
        'import.meta.env.PROD': 'true',
    },
    plugins: [appPlugin],
    nodePaths: [resolve('node_modules')],
    logLevel: 'warning',
});

// -- 3. declarations --------------------------------------------------------------------------------------------------
const tsconfig = join(OUT, 'tsconfig.json');
writeFileSync(
    tsconfig,
    JSON.stringify(
        {
            extends: resolve('tsconfig.json'),
            compilerOptions: {
                noEmit: false,
                declaration: true,
                emitDeclarationOnly: true,
                noEmitOnError: false,
                skipLibCheck: true,
                rootDir: ROOT,
                outDir: join(OUT, 'dist/types'),
                paths: { '@/*': [join(JS, '*')] },
            },
            include: [],
            files: [
                entry,
                resolve('resources/js/types/global.d.ts'),
                resolve('resources/js/types/vite-env.d.ts'),
            ].filter((f) => existsSync(f)),
        },
        null,
        2,
    ),
);
try {
    execFileSync(requireApp.resolve('typescript/bin/tsc'), ['-p', tsconfig], {
        stdio: 'pipe',
    });
} catch (e) {
    // Type errors don't stop the emit (noEmitOnError false); only a missing output does.
    const n = String(e.stdout ?? '')
        .split('\n')
        .filter((l) => l.includes('error TS')).length;
    console.error(
        `  tsc: ${n} type errors reported while emitting declarations (emit continues)`,
    );
}
const typesEntry = join(OUT, 'dist/types/.ds-sync/pkg/src/index.d.ts');
if (!existsSync(typesEntry)) {
    console.error(`[BUILD_DIST] tsc emitted no ${typesEntry}`);
    process.exit(1);
}
// The entry's d.ts imports `@/...`: rewrite to the emitted tree so consumers resolve it without the alias.
writeFileSync(
    join(OUT, 'dist/index.d.ts'),
    readFileSync(typesEntry, 'utf8').replace(
        /'@\/([^']+)'/g,
        (_, p) => `'./types/resources/js/${p}'`,
    ),
);
// Same inside the emitted tree.
const fixAliases = (dir) => {
    for (const f of readdirSync(dir, { withFileTypes: true })) {
        const p = join(dir, f.name);
        if (f.isDirectory()) fixAliases(p);
        else if (f.name.endsWith('.d.ts')) {
            const rel = p.slice(
                join(OUT, 'dist/types/resources/js').length + 1,
            );
            const up = rel.split('/').length - 1;
            const prefix = up ? '../'.repeat(up) : './';
            writeFileSync(
                p,
                readFileSync(p, 'utf8').replace(
                    /(['"])@\/([^'"]+)\1/g,
                    (_, q, s) => `${q}${prefix}${s}${q}`,
                ),
            );
        }
    }
};
if (existsSync(join(OUT, 'dist/types/resources/js')))
    fixAliases(join(OUT, 'dist/types/resources/js'));

// -- 4. stylesheet ----------------------------------------------------------------------------------------------------
const { compile } = requireApp('@tailwindcss/node');
const { Scanner } = requireApp('@tailwindcss/oxide');
const cssPath = resolve('resources/css/app.css');
const compiler = await compile(readFileSync(cssPath, 'utf8'), {
    base: resolve('resources/css'),
    from: cssPath,
    onDependency: () => {},
});
const sources = [
    ...compiler.sources,
    { base: JS, pattern: '**/*', negated: false },
    ...(existsSync(resolve('.design-sync/previews'))
        ? [
              {
                  base: resolve('.design-sync/previews'),
                  pattern: '**/*',
                  negated: false,
              },
          ]
        : []),
];
const scanner = new Scanner({ sources });
const css = compiler.build(scanner.scan());
mkdirSync(join(OUT, 'dist'), { recursive: true });

// Figtree, self-hosted by the app's build (laravel-vite-plugin fonts): the @font-face rules and their files.
mkdirSync(join(OUT, 'fonts'), { recursive: true });
const built = resolve('public/build');
const fontFiles = [];
const walk = (d) => {
    if (!existsSync(d)) return;
    for (const f of readdirSync(d, { withFileTypes: true })) {
        const p = join(d, f.name);
        if (f.isDirectory()) walk(p);
        else if (/figtree/i.test(f.name) && /\.(woff2?|ttf)$/.test(f.name))
            fontFiles.push(p);
    }
};
walk(built);
const faces = fontFiles
    .filter((p) => p.endsWith('.woff2'))
    .map((p) => {
        cpSync(p, join(OUT, 'fonts', basename(p)));
        const weight = /-(\d00)/.exec(basename(p))?.[1] ?? '400';
        return `@font-face{font-family:'Figtree';font-style:normal;font-weight:${weight};font-display:swap;src:url('../fonts/${basename(p)}') format('woff2');}`;
    });
if (!faces.length)
    console.error(
        '! no self-hosted Figtree under public/build: the stylesheet names Figtree with no @font-face',
    );
writeFileSync(join(OUT, 'dist/styles.css'), faces.join('\n') + '\n' + css);

writeFileSync(
    join(OUT, 'package.json'),
    JSON.stringify(
        {
            name: 'humetria',
            version:
                JSON.parse(readFileSync('package.json', 'utf8')).version ??
                '1.0.0',
            private: true,
            type: 'module',
            module: './dist/index.js',
            main: './dist/index.js',
            types: './dist/index.d.ts',
            style: './dist/styles.css',
        },
        null,
        2,
    ),
);
console.error(
    `✓ .ds-sync/pkg: ${seen.size} exports, ${faces.length} font faces, ${(css.length / 1024).toFixed(0)} KB of CSS`,
);
