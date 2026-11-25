import { fileURLToPath } from 'node:url';

import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

import { frontendArchitecture } from './frontend-architecture';

// The service worker is its own build (`npm run build:sw`): one classic file with no import, named by hand, served from
// /me/{slug}/sw.js (an import would resolve against that address and the worker would never activate).
const workerConfig = defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        outDir: 'public/build/pwa',
        emptyOutDir: false,
        copyPublicDir: false,
        rolldownOptions: {
            input: 'resources/js/sw/sw.ts',
            output: { format: 'iife', entryFileNames: 'sw.js' },
        },
    },
});

const appConfig = defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.tsx',
                'resources/js/offline.tsx',
            ],
            ssr: 'resources/js/app.tsx',
            refresh: true,
            fonts: [
                bunny('Figtree', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    // The `ssr` image carries no node_modules: the bundle brings its dependencies with it.
    ssr: { noExternal: true },
    build: {
        rolldownOptions: {
            output: {
                // The first response lists the chunks of the page's frame, and its name must not say «mobile»: pages that
                // carry no phone number assert that word never reaches them (Compensation's report).
                chunkFileNames: (chunk) =>
                    `assets/${chunk.name.replaceAll('mobile', 'narrow')}-[hash].js`,
            },
        },
    },
    server: {
        // Outside Docker: Vite's default address, but written to public/hot by name: the CSP
        // (SecurityHeaders) must allow the dev server, and CSP rejects IPv6 literals like [::1].
        // In Sail, laravel-vite-plugin listens on 0.0.0.0 and writes http://localhost:PORT itself.
        ...(process.env.LARAVEL_SAIL ? {} : { host: 'localhost' }),
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            // Only pure logic is gated. Hooks and components need a DOM
            // environment; the browser tests cover them for now.
            include: ['resources/js/lib/**'],
            thresholds: {
                lines: 90,
                functions: 90,
                branches: 90,
                statements: 90,
            },
        },
    },
    lint: {
        ...frontendArchitecture,
        ignorePatterns: [
            // Like the formatter: docs/ holds design prototypes (docs/rediseno), not code of the app.
            'docs/**',
            'vendor/**',
            'node_modules/**',
            'public/**',
            // Remotion project of the hero video: own package.json and tsconfig.
            'video/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            '.claude/**',
            'docs/**',
            'video/**',
            'AGENTS.md',
            'CLAUDE.md',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});

export default process.env['PWA_WORKER'] === 'sw' ? workerConfig : appConfig;
