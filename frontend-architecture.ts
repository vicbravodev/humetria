import { readdirSync } from 'node:fs';

/*
 * Frontend architecture rules, enforced by `vp lint` (see vite.config.ts) and
 * pinned by tests/Unit/FrontendArchitectureTest.php. Thresholds follow the
 * ESLint defaults, except 100 lines per function (React components carry JSX)
 * and a cyclomatic complexity of 10 (McCabe).
 *
 * resources/js/components/{module}/ mirrors a backend module: it may use the
 * shared folders (ui, humetria, hooks, lib, types) but never another module.
 */

const COMPONENTS = 'resources/js/components';
const SHARED_COMPONENT_FOLDERS = ['ui', 'humetria'];

type ImportPattern = { group: string[]; message: string };

const everywhere: ImportPattern[] = [
    {
        group: ['../*', './../*'],
        message: 'Usa el alias @/ en lugar de subir carpetas.',
    },
    {
        group: ['@/pages', '@/pages/**'],
        message: 'Las páginas no se importan: son el último eslabón.',
    },
];

function restrictImports(...extra: ImportPattern[]) {
    return ['error', { patterns: [...everywhere, ...extra] }] as const;
}

function componentModules(): string[] {
    return readdirSync(COMPONENTS, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name)
        .filter((name) => !SHARED_COMPONENT_FOLDERS.includes(name));
}

function moduleBoundaries() {
    const modules = componentModules();

    return modules.map((module) => ({
        files: [`${COMPONENTS}/${module}/**`],
        rules: {
            'no-restricted-imports': restrictImports(
                {
                    group: modules
                        .filter((other) => other !== module)
                        .map((other) => `@/components/${other}/**`),
                    message: 'Un módulo no importa componentes de otro módulo.',
                },
                {
                    group: ['@/layouts/**'],
                    message: 'Los componentes no dependen de layouts.',
                },
            ),
        },
    }));
}

/*
 * Starter-kit files written before these rules. Only the welcome page is left:
 * the landing page replaces it and then deletes this list; never add a file to it.
 */
const PENDING_REFACTOR = ['resources/js/pages/welcome.tsx'];

export const frontendArchitecture = {
    plugins: ['typescript', 'unicorn', 'oxc', 'import', 'react'],
    rules: {
        'max-lines': ['error', { max: 300 }],
        'max-lines-per-function': [
            'error',
            { max: 100, skipBlankLines: true, skipComments: true },
        ],
        complexity: ['error', 10],
        'max-depth': ['error', 4],
        'max-params': ['error', 3],
        'react/no-multi-comp': 'error',
        'unicorn/filename-case': ['error', { case: 'kebabCase' }],
        'import/no-cycle': 'error',
        'no-restricted-imports': restrictImports(),
    },
    overrides: [
        {
            files: [`${COMPONENTS}/humetria/**`],
            rules: {
                'no-restricted-imports': restrictImports({
                    group: [
                        '@/components/**',
                        '!@/components/ui/**',
                        '@/layouts/**',
                    ],
                    message:
                        'El design system solo usa components/ui, hooks y lib.',
                }),
            },
        },
        {
            files: [
                'resources/js/hooks/**',
                'resources/js/lib/**',
                'resources/js/types/**',
            ],
            rules: {
                'no-restricted-imports': restrictImports({
                    group: ['@/components/**', '@/layouts/**'],
                    message:
                        'hooks, lib y types son la capa base: no importan componentes ni layouts.',
                }),
            },
        },
        ...moduleBoundaries(),
        {
            files: PENDING_REFACTOR,
            rules: {
                'max-lines': 'off',
                'max-lines-per-function': 'off',
                'react/no-multi-comp': 'off',
            },
        },
    ],
};
