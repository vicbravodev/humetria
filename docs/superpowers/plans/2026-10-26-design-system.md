# Design system de Humetría: plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicar a la app existente los tokens, la tipografía, los ajustes de shadcn y la marca del design system de Humetría.

**Architecture:** El paquete de Claude Design se copia a `docs/design-system/` como fuente de verdad. Los tokens
entran a `resources/css/app.css`. Los componentes shadcn existentes reciben los 5 ajustes. Un componente `Logo`
reemplaza al logo de Laravel. Las reglas se protegen con tests de Pest: contraste, reglas sobre el código de
`ui/*`, HTML servido y navegador.

**Tech Stack:** Laravel 13, Inertia v3 + React 19, Tailwind v4, shadcn/ui, Pest 5 (+ Browser), `laravel-vite-plugin/fonts` (Bunny).

**Spec:** `docs/superpowers/specs/2026-10-26-design-system-design.md`

**Paquete fuente:** `humetria-design-system.zip`, extraído en `/tmp/humetria-ds.zveg/humetria-design-system/`
(en adelante `$DS`). Si ya no existe, se vuelve a extraer del upload de la conversación en un directorio vacío nuevo.

## Global Constraints

- Sin dependencias nuevas (composer ni npm).
- "Humetría" con acento en la interfaz; `humetria` en código, rutas y nombres de archivo.
- Los valores de color se copian **exactos** de `$DS/styles/globals.css`. No se inventan ni se ajustan.
- Cada cambio de producción lo exige un test que primero se vio fallar (CLAUDE.md, regla 1).
- Terminado = `composer test`, `composer test:mutate` y `npm run types:check` en verde, con `npm run build` hecho antes.
- Commits a nombre del usuario, sin atribución a Claude.
- PR 1 = Tasks 1–2 (rama `feature/design-system-tokens`). PR 2 = Tasks 3–4 (rama `feature/design-system-brand`,
  creada desde `main` después de mergear el PR 1).

## Review Focus

1. **Desfase entre el build y los tests:** el test de fuentes lee el HTML que genera el build. Si no se corre
   `npm run build`, el test falla aunque el código esté bien. Task 1, Step 6 lo hace explícito.
2. **`.env` local con `APP_NAME=Laravel`:** el test del `<title>` falla en máquinas con `.env` viejo. Task 3
   actualiza `.env.example` y el `.env` del worktree, y el PR lo avisa.
3. **Sidebar colapsado:** el logo horizontal mide más de 96 px y no cabe en el modo de solo iconos. En ese
   modo se muestra el símbolo. Lo verifica el test de navegador de Task 4.
4. **Variantes de shadcn que usan tokens eliminados:** quitar `--destructive-foreground` rompe `alert.tsx` y
   `dropdown-menu.tsx` en silencio, con clases que no existen. Lo detecta la regla de Task 2.
5. **Modo oscuro sin destello:** el `<style>` inline de `app.blade.php` tiene que usar los fondos nuevos
   (`#FBFDFC` y `#0F1716`). Task 3 lo afirma en el HTML.

---

### Task 1: Tokens, Figtree y documentación del design system (PR 1)

**Files:**
- Create: `docs/design-system/` ← copiar de `$DS`: `docs/design-system.md`, `docs/tokens-shadcn.md`,
  `docs/decisiones.md`, `docs/componentes/`, `referencia/capturas/` (como `docs/design-system/capturas/`) y
  `tokens.json`. Nada más del paquete.
- Modify: `resources/css/app.css` (todo, menos las líneas `@import` y `@source`)
- Modify: `vite.config.ts:16` (fuente)
- Modify: `CLAUDE.md` (sección nueva "Design system")
- Test: `tests/Unit/DesignSystemTest.php`, `tests/Feature/DesignSystemTest.php`

**Interfaces:**
- Produces: los tokens CSS `--<token>` de `$DS/styles/globals.css` en `:root` y `.dark` (49 cada uno) y las
  utilidades Tailwind `bg-on-destructive`/`text-on-destructive`, `*-brand-soft`, `*-brand-deep` y `*-risk-*`.
  Tasks 2–4 usan esos nombres.

- [ ] **Step 1: Write the failing contrast test** en `tests/Unit/DesignSystemTest.php`

```php
/** @return array<string, string> token => #hex */
function themeTokens(string $selector): array // lee el bloque `$selector {…}` de resources/css/app.css,
                                              // regex `--([\w-]+):\s*(#[0-9A-Fa-f]{6})`

function contrastRatio(string $hexA, string $hexB): float // WCAG 2.x, luminancia relativa sRGB

dataset('themes', [':root', '.dark']);

it('defines the same 49 color tokens in light and dark', function (): void {
    expect(array_keys(themeTokens(':root')))->toHaveCount(49)
        ->toEqualCanonicalizing(array_keys(themeTokens('.dark')));
});

it('keeps text at 4.5:1 or more', function (string $theme, string $text, string $surface): void {
    $tokens = themeTokens($theme);
    expect(contrastRatio($tokens[$text], $tokens[$surface]))->toBeGreaterThanOrEqual(4.5);
})->with('themes')->with([
    ['foreground', 'background'], ['card-foreground', 'card'], ['popover-foreground', 'popover'],
    ['primary', 'background'], ['primary', 'card'], ['primary-foreground', 'primary'],
    ['secondary-foreground', 'secondary'], ['muted-foreground', 'background'], ['muted-foreground', 'card'],
    ['muted-foreground', 'muted'], ['accent-foreground', 'accent'], ['destructive', 'card'],
    ['destructive', 'background'], ['on-destructive', 'destructive'], ['sidebar-foreground', 'sidebar'],
    ['sidebar-primary-foreground', 'sidebar-primary'], ['sidebar-accent-foreground', 'sidebar-accent'],
    ['risk-nulo-fg', 'risk-nulo-bg'], ['risk-bajo-fg', 'risk-bajo-bg'], ['risk-medio-fg', 'risk-medio-bg'],
    ['risk-alto-fg', 'risk-alto-bg'], ['risk-muy-alto-fg', 'risk-muy-alto-bg'],
]);

it('keeps control borders, focus and data marks at 3:1 or more', function (string $theme, string $mark, string $surface): void {
    $tokens = themeTokens($theme);
    expect(contrastRatio($tokens[$mark], $tokens[$surface]))->toBeGreaterThanOrEqual(3.0);
})->with('themes')->with([
    ['input', 'background'], ['input', 'card'], ['ring', 'background'], ['ring', 'card'],
    ['sidebar-ring', 'sidebar'], ['sidebar-primary', 'sidebar'],
    ['chart-1', 'card'], ['chart-2', 'card'], ['chart-3', 'card'], ['chart-4', 'card'], ['chart-5', 'card'],
    ['risk-nulo', 'card'], ['risk-bajo', 'card'], ['risk-medio', 'card'], ['risk-alto', 'card'], ['risk-muy-alto', 'card'],
]);

it('measures contrast like WCAG', function (): void {
    expect(round(contrastRatio('#0B7A6F', '#FFFFFF'), 2))->toBe(5.21)
        ->and(round(contrastRatio('#FFFFFF', '#000000'), 2))->toBe(21.0);
});
```

- [ ] **Step 2: Write the failing font test** en `tests/Feature/DesignSystemTest.php`

```php
it('loads Figtree and no longer Instrument Sans', function (): void {
    $this->get(route('login'))->assertOk()
        ->assertSee('figtree-400-normal', false)
        ->assertDontSee('instrument-sans', false);
});
```

- [ ] **Step 3: Run both to verify they fail**

Run: `php artisan test --compact tests/Unit/DesignSystemTest.php tests/Feature/DesignSystemTest.php`
Expected: FAIL. Contraste: `:root` hoy está en `oklch`, así que `themeTokens` devuelve 0 tokens. Fuente: el HTML
precarga `instrument-sans-*`. `measures contrast like WCAG` sí pasa.

- [ ] **Step 4: Replace the theme in `resources/css/app.css`**

Conserva `@import 'tailwindcss'`, `@import 'tw-animate-css'`, las dos líneas `@source` y `@custom-variant dark`.
Todo lo demás se reemplaza por los bloques `:root`, `.dark`, `@theme inline` y `@layer base` de
`$DS/styles/globals.css`, copiados tal cual. Se elimina el mapeo `--color-destructive-foreground`.

- [ ] **Step 5: Switch the font** en `vite.config.ts`: `bunny('Figtree', { weights: [400, 500, 600] })`.

- [ ] **Step 6: Build and run the tests**

Run: `npm run build && php artisan test --compact tests/Unit/DesignSystemTest.php tests/Feature/DesignSystemTest.php`
Expected: PASS.

- [ ] **Step 7: Copy the docs and add the `CLAUDE.md` section**

Copia los archivos listados en **Files** a `docs/design-system/`. En `CLAUDE.md`, después de "Idioma y tiempo",
agrega `## Design system` con 5 viñetas:
- la fuente de verdad es `docs/design-system/`, y se compara con `capturas/` en claro y oscuro;
- un solo acento (`primary`); `brand-soft` y `brand-deep` solo dentro del logo;
- texto ≥ 4.5:1 y controles y foco ≥ 3:1, con `tests/Unit/DesignSystemTest.php` como protección: un color nuevo
  entra ahí con su par;
- los 5 ajustes a shadcn se aplican después de cada `npx shadcn add`, y `tests/Unit/ShadcnAdjustmentsTest.php`
  los hace cumplir;
- el panel tutea y la encuesta trata de "usted"; el riesgo siempre con `RiskLevel`; la encuesta nunca avanza sola.

- [ ] **Step 8: Run the definition of done and commit**

Run: `composer test && npm run types:check`. Expected: todo en verde.

```bash
git add resources/css/app.css vite.config.ts CLAUDE.md docs/design-system docs/superpowers tests/Unit/DesignSystemTest.php tests/Feature/DesignSystemTest.php
git commit -m "Adopt the Humetría design tokens and Figtree"
```

### Task 2: Los 5 ajustes a los componentes shadcn (PR 1)

**Files:**
- Modify: `resources/js/components/ui/` `button.tsx`, `input.tsx`, `select.tsx`, `checkbox.tsx`, `badge.tsx`,
  `toggle.tsx`, `toggle-group.tsx`, `card.tsx`, `input-otp.tsx`, `alert.tsx`, `dropdown-menu.tsx`
- Test: `tests/Unit/ShadcnAdjustmentsTest.php`

**Interfaces:**
- Consumes: los tokens `ring`, `input`, `card`, `destructive` y `on-destructive` de Task 1.

- [ ] **Step 1: Write the failing rule test**

Lee cada `resources/js/components/ui/*.tsx` como texto.

```php
it('leaves no shadcn default that breaks the design system', function (string $forbidden): void {
    foreach (glob(base_path('resources/js/components/ui/*.tsx')) as $file) {
        expect(file_get_contents($file))->not->toContain($forbidden, basename($file));
    }
})->with([
    'ring-[3px]', 'ring-ring/50', 'shadow-xs', 'md:text-sm', 'dark:bg-input/30',
    'destructive-foreground', 'dark:bg-destructive/60',
]);

it('keeps shadows only on floating surfaces', function (): void {
    foreach (['button', 'input', 'card', 'select', 'checkbox', 'badge', 'toggle'] as $component) {
        expect(file_get_contents(base_path("resources/js/components/ui/{$component}.tsx")))->not->toContain('shadow-sm');
    }
});

it('sizes controls to 44 px and focuses with a solid outline', function (string $component): void {
    expect(file_get_contents(base_path("resources/js/components/ui/{$component}.tsx")))
        ->toContain('h-11')
        ->toContain('focus-visible:outline-ring');
})->with(['button', 'input', 'select']);
```

Si es un test unitario sin la app, usa `__DIR__.'/../../resources/...'` en lugar de `base_path`.

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --compact tests/Unit/ShadcnAdjustmentsTest.php`
Expected: FAIL, nombrando los archivos del mapa de arriba.

- [ ] **Step 3: Apply the adjustments** (detalle en `docs/design-system/tokens-shadcn.md`, sección "Ajustes")

1. **Foco:** `focus-visible:ring-[3px] focus-visible:ring-ring/50` pasa a
   `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring`. Se eliminan las clases
   `ring-destructive/20|40` hermanas y se conserva `aria-invalid:border-destructive`.
2. **Destructive:** `bg-destructive text-white` pasa a `bg-destructive text-on-destructive`, y se elimina
   `dark:bg-destructive/60` (badge y button). En `alert.tsx` y `dropdown-menu.tsx`, `destructive-foreground`
   pasa a `destructive`.
3. **Bordes:** `input`, `select` (trigger), `checkbox` y el botón `outline` usan `border-input bg-card`. Se quita
   `dark:bg-input/30`.
4. **Tamaño:** `h-9` pasa a `h-11`, con `px-4` y `text-base` en button (default), input y select trigger. Se
   elimina `md:text-sm`. Los tamaños `sm` y `lg` del botón se quedan.
5. **Sombras:** se eliminan `shadow-xs` y `shadow-sm` de button, input, select trigger, checkbox, toggle,
   toggle-group, card e input-otp. El contenido de select, dropdown y dialog usa
   `shadow-[0_8px_24px_rgb(20_33_31/0.08)]`.

- [ ] **Step 4: Run the test, the definition of done, and look**

Run: `php artisan test --compact tests/Unit/ShadcnAdjustmentsTest.php && npm run build && composer test && npm run types:check`
Expected: todo en verde. Abre `/login`, `/register` y `/settings/profile` en claro y oscuro y compáralos con
`docs/design-system/capturas/Button|Input|Select|Checkbox.*.png`.

- [ ] **Step 5: Commit and open PR 1**

```bash
git add resources/js/components/ui tests/Unit/ShadcnAdjustmentsTest.php
git commit -m "Apply the design system adjustments to shadcn components"
```

### Task 3: Marca en la app, logo, favicons y nombre (PR 2)

**Files:**
- Create: `public/brand/*.svg` ← `$DS/public/brand/` (15 SVG)
- Create: `public/favicon.svg`, `public/favicon-16.png`, `public/favicon-32.png`, `public/favicon-48.png`,
  `public/apple-touch-icon-180.png`, `public/app-icon-512.png`, `public/app-icon.svg` ← `$DS/public/favicon/`
- Delete: `public/favicon.ico`, `public/apple-touch-icon.png`, `resources/js/components/app-logo.tsx`,
  `resources/js/components/app-logo-icon.tsx`
- Create: `resources/js/components/humetria/logo.tsx`
- Modify: `resources/views/app.blade.php:23-35` (fondo inline y `<link>`), `.env.example:1`,
  `resources/js/app.tsx:9`, y quienes usaban `AppLogo` y `AppLogoIcon`: `app-sidebar.tsx`, `app-header.tsx` y
  `layouts/auth/auth-{card,simple,split}-layout.tsx`
- Test: `tests/Feature/BrandTest.php`

**Interfaces:**
- Produces: `export default function Logo({ kind = 'logo', className }: { kind?: 'logo' | 'symbol' | 'seal'; className?: string })`.
  Pinta dos `<img>`: la versión clara con `dark:hidden` y la oscura con `hidden dark:block`, las dos con
  `alt="Humetría"`. Archivos: `logo` = `humetria-horizontal-color(-oscuro).svg`, `symbol` =
  `humetria-simbolo-color(-oscuro).svg`, `seal` = `humetria-sello-hecho-con-gris|blanco.svg`.
  La altura la decide `className`.

- [ ] **Step 1: Write the failing brand test**

```php
it('names the app Humetría', function (): void {
    $this->get(route('login'))->assertSee('<title>Humetría</title>', false);
});

it('links favicons that exist', function (string $href): void {
    $this->get(route('login'))->assertSee("href=\"{$href}\"", false);
    expect(public_path(ltrim($href, '/')))->toBeFile();
})->with(['/favicon.svg', '/favicon-32.png', '/apple-touch-icon-180.png']);

it('paints the first frame with the design system backgrounds', function (): void {
    $this->get(route('login'))->assertSee('#FBFDFC', false)->assertSee('#0F1716', false);
});

it('ships the official brand files the Logo component loads', function (string $file): void {
    expect(public_path("brand/{$file}"))->toBeFile();
})->with([
    'humetria-horizontal-color.svg', 'humetria-horizontal-color-oscuro.svg',
    'humetria-simbolo-color.svg', 'humetria-simbolo-color-oscuro.svg',
    'humetria-sello-hecho-con-gris.svg', 'humetria-sello-hecho-con-blanco.svg',
]);
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --compact tests/Feature/BrandTest.php`
Expected: FAIL. El `<title>` dice `Laravel` y faltan los archivos.

- [ ] **Step 3: Implement**

1. Copia los assets listados en **Files** y borra los viejos.
2. `app.blade.php`: `html { background-color: #FBFDFC; } html.dark { background-color: #0F1716; }`, más los
   `<link>` de favicon (`/favicon.svg` como `image/svg+xml`, `/favicon-32.png` con `sizes="32x32"` y
   `/apple-touch-icon-180.png`). Se quita el de `favicon.ico`.
3. `.env.example`: `APP_NAME="Humetría"`. En el `.env` del worktree también. `app.tsx`: el respaldo `'Laravel'`
   pasa a `'Humetría'`.
4. Crea `Logo` según **Interfaces**.
5. Reemplaza `AppLogo` por `<Logo className="h-7" />` en sidebar y header. En el sidebar, con
   `group-data-[collapsible=icon]`, muestra `<Logo kind="symbol" className="size-8" />` en lugar del horizontal.
   Reemplaza `AppLogoIcon` por `<Logo kind="symbol" className="size-10" />` en los tres layouts de auth.
6. Borra `app-logo.tsx` y `app-logo-icon.tsx`.

- [ ] **Step 4: Run the tests**

Run: `npm run build && php artisan test --compact tests/Feature/BrandTest.php && npm run types:check`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A public resources .env.example tests/Feature/BrandTest.php
git commit -m "Brand the app as Humetría: logo, favicons and name"
```

### Task 4: Accesibilidad en navegador (PR 2)

**Files:**
- Test: `tests/Browser/DesignSystemTest.php`

**Interfaces:**
- Consumes: el componente `Logo` de Task 3 (`img[alt="Humetría"]`).

- [ ] **Step 1: Write the browser test**

```php
it('renders guest pages accessibly in light and dark, desktop and mobile', function (string $path): void {
    foreach ([visit($path), visit($path)->inDarkMode(), visit($path)->on()->mobile(), visit($path)->on()->mobile()->inDarkMode()] as $page) {
        $page->assertVisible('img[alt="Humetría"]')
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues(1);
    }
})->with(['/login', '/register']);

it('renders the dashboard accessibly with the logo in the sidebar', function (): void {
    $this->actingAs(User::factory()->create());

    foreach ([visit('/dashboard'), visit('/dashboard')->inDarkMode(), visit('/dashboard')->on()->mobile()] as $page) {
        $page->assertVisible('img[alt="Humetría"]')
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues(1);
    }
});
```

`assertNoAccessibilityIssues(1)` reprueba las violaciones críticas y graves de axe, que incluyen `color-contrast`.
Si `inDarkMode()` no se puede encadenar después de `on()->mobile()`, usa el orden que acepte la API: el criterio
es cubrir las cuatro combinaciones.

- [ ] **Step 2: Run it**

Run: `npm run build && php artisan test --compact tests/Browser/DesignSystemTest.php`
Expected: PASS. Si falla por una violación de axe, la corrección va en el componente o en la página, nunca
relajando el nivel (CLAUDE.md, regla 2). Si la violación viene de código del starter kit ajeno a este proyecto,
se reporta antes de tocarlo.

- [ ] **Step 3: Run the definition of done, compare with the captures, commit and open PR 2**

Run: `composer test && composer test:mutate && npm run types:check`. Compara el sidebar con
`docs/design-system/capturas/Logo.*.png` y `PanelRH.*.png`.

```bash
git add tests/Browser/DesignSystemTest.php
git commit -m "Guard accessibility of branded pages in light and dark"
```
