# F0 · Infraestructura antes de producción — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que ninguna respuesta lleve encabezados que un proxy rechace, que `/horizon` cargue, y que las páginas del trabajador no descarguen el marco del panel.

**Architecture:** Tres cambios en el kernel compartido, sin módulos. Se quita el middleware que duplica en un encabezado `Link` lo que `@vite` ya emite como `<link rel="modulepreload">`; la CSP se afloja solo en la ruta de Horizon; los layouts del panel se cargan con import dinámico.

**Tech Stack:** Laravel 13, Vite, Inertia + React, Pest. Sin dependencias nuevas.

**Spec:** `docs/superpowers/plans/2026-11-12-launch-readiness.md` (hallazgos E-03, SA-02, E-06).

## Global Constraints

- TDD estricto: test que falla por la razón correcta, código mínimo, verde, commit. Un commit = un comportamiento, a nombre del usuario, sin atribución a IA.
- PHPStan `max` sin supresiones; TypeScript strictest sin `as`, `!`, `any` ni `@ts-ignore`.
- Aflojar la CSP exige explicar el motivo en el PR (CLAUDE.md, «Seguridad»): la tarea 2 lo deja escrito.
- Rama `fix/launch-infra`. Este frente entra a `main` antes de que empiece F3.
- Verificación acotada por tarea; `composer ci:check` solo al cierre.

## Review Focus

1. **Una página con muchos chunks detrás de un proxy.** El encabezado `Link` no debe existir aunque Vite reporte 80 assets precargados. → Task 1.
2. **La CSP relajada se cuela fuera de Horizon.** `/admin`, `/survey/{token}` y `/horizon-otra-cosa` no deben recibir `'unsafe-eval'`. → Task 2.
3. **Un usuario que no es superadmin pide `/horizon`.** Sigue recibiendo 403 y la CSP estricta. → Task 2.
4. **Primera carga de una página del panel con el layout diferido.** Sin parpadeo de contenido sin marco y sin errores de hidratación. → Task 3.
5. **Navegar del panel a una página sin layout y volver.** El layout diferido no se vuelve a pedir ni pierde su estado (sidebar plegado). → Task 3.

---

### Task 1: Sin encabezado `Link` de precarga (E-03)

**Files:**
- Modify: `bootstrap/app.php:52` (quitar `AddLinkHeadersForPreloadedAssets::class` y su `use`)
- Test: `tests/Feature/ResponseHeadersTest.php` (nuevo)

- [ ] **Step 1: Test que falla.** `it('never sends a preload Link header, however many chunks a page has')`: registra 80 assets con `Vite::preloadedAssets()` simulado (`Vite::partialMock()->shouldReceive('preloadedAssets')->andReturn([...80 urls...])`), hace `GET route('login')` y afirma `$response->assertHeaderMissing('Link')`. Segundo caso: la suma de bytes de todos los encabezados de esa respuesta es menor que `4096`.
- [ ] **Step 2:** `php artisan test --compact tests/Feature/ResponseHeadersTest.php` → FALLA: la respuesta trae `Link`.
- [ ] **Step 3:** Quitar el middleware del grupo `web`. No se usa `::using(n)`: el `<link rel="modulepreload">` del Blade ya cubre la precarga.
- [ ] **Step 4:** El test pasa. `vendor/bin/pint --test bootstrap/app.php` y `vendor/bin/phpstan analyse bootstrap/app.php`.
- [ ] **Step 5: Commit** `Respuestas sin encabezado Link de precarga: detrás de un proxy pasaba de 10 KB`.

### Task 2: `/horizon` carga con su propia CSP (SA-02)

**Files:**
- Modify: `app/Http/Middleware/SecurityHeaders.php` (método `policy()`)
- Test: `tests/Feature/HorizonTest.php`

**Interfaces:**
- Produces: `SecurityHeaders::policy()` agrega `'unsafe-eval'` a `script-src` y `https://fonts.bunny.net` a `style-src` y `font-src` **solo** cuando `$request->is(config('horizon.path'), config('horizon.path').'/*')`.

- [ ] **Step 1: Tests que fallan** en `HorizonTest.php`:
  - `it('lets Horizon compile its templates')`: superadmin, `GET /horizon` → la cabecera `Content-Security-Policy` contiene `'unsafe-eval'` y `https://fonts.bunny.net`.
  - `it('keeps the strict policy everywhere else')`: con `dataset` de `/admin/login`, la consola de empresas y `/horizonte` (ruta inexistente que empieza igual, espera 404) → la cabecera **no** contiene `'unsafe-eval'` ni `bunny`.
  - `it('still forbids Horizon to anyone but the superadmin')`: owner → 403.
- [ ] **Step 2:** Correr el archivo → falla el primero.
- [ ] **Step 3:** Implementar la rama en `policy()`. La condición usa `config('horizon.path')`, no el literal.
- [ ] **Step 4:** Verde. Pint y PHPStan sobre el archivo.
- [ ] **Step 5:** Revisar en el navegador `http://localhost/horizon` como superadmin: tablero visible, consola sin violaciones.
- [ ] **Step 6: Commit** `Horizon: la CSP permite unsafe-eval y su fuente solo en /horizon, donde su build los necesita`. En la descripción del PR: Horizon compila su plantilla con `new Function` y no ofrece un build sin eval; la excepción vive en una ruta que solo ve el superadmin.

### Task 3: Las páginas sin marco no descargan el marco (E-06)

**Files:**
- Modify: `resources/js/app.tsx:8-10` (imports estáticos de `AppLayout`, `AuthLayout`, `SettingsLayout`)
- Create: `resources/js/layouts/deferred-layout.tsx` (un componente: recibe `name: 'app' | 'auth' | 'settings'` y `children`, resuelve el layout con `React.lazy` e `import()` y lo envuelve en `Suspense` con `fallback={null}`)
- Test: `tests/Unit/FrontendArchitectureTest.php`, `tests/Browser/SurveyTest.php`, `tests/Browser/SmokeTest.php`

- [ ] **Step 1: Test que falla** en `FrontendArchitectureTest.php`: `it('loads the app frame on demand')` lee `resources/js/app.tsx` y afirma que no contiene `from '@/layouts/app` ni `from '@/layouts/auth` ni `from '@/layouts/settings` como import estático.
- [ ] **Step 2:** Correr → falla.
- [ ] **Step 3:** Implementar `DeferredLayout` y usarlo donde `app.tsx` asigna `layout`. `lib/page-frame.ts` sigue decidiendo qué página es standalone; esas no montan `DeferredLayout`.
- [ ] **Step 4:** `npm run build` y comprobar a mano en `public/build/manifest.json` que la entrada `resources/js/pages/campaigns/survey.tsx` ya no arrastra `app-sidebar`, `humi-dock`, `command-palette` ni `org-chart`. Anotar en el PR el peso antes y después.
- [ ] **Step 5:** `php artisan test --compact tests/Browser/SurveyTest.php tests/Browser/SmokeTest.php tests/Browser/DashboardTest.php` → verde (sin errores de JS ni de CSP).
- [ ] **Step 6:** `npm run types:check` y `npm run check`. Revisar en el navegador el Panel (claro, oscuro, 375 px): sin parpadeo al entrar ni al navegar entre páginas.
- [ ] **Step 7: Commit** `El marco del panel se carga bajo demanda: la encuesta y el portal ya no lo descargan`.

### Cierre del frente

- [ ] `composer ci:check`, `npm run types:check`, `npm run test:js` en verde.
- [ ] Prueba manual: `curl -sI` autenticado al Panel y a una encuesta; total de encabezados por debajo de 4 KB.
- [ ] Preguntar «¿Creo el PR?».
