# SEO, SSR de Inertia, vista previa al compartir y medición de la landing (v2)

*   **Author:** Victor Jesus Bravo de la Peña
*   **Status:** Decisiones del dueño aplicadas (sección 18). Pendiente de su aprobación final; solo sigue abierta Q12 (`APP_URL` y redirección de `www`, tarea de despliegue). Nada está implementado.
*   **Date:** 2026-11-14
*   **Sustituye a:** [`2026-11-14-landing-seo-design.md`](2026-11-14-landing-seo-design.md) (v1). Especificación: [`2026-11-14-landing-seo-handoff.md`](2026-11-14-landing-seo-handoff.md). El brief original está en [`2026-11-14-landing-seo-brief.md`](2026-11-14-landing-seo-brief.md).

Convenciones de este documento:

*   Cada afirmación sobre el código lleva `archivo:línea`. **Comprobado** significa que el autor abrió el archivo en esta revisión. **Sin verificar** significa que no se pudo abrir o ejecutar; si una decisión depende de ello, hay una pregunta abierta con la prueba que lo resuelve.
*   "Cabecera HTTP" es `X-Robots-Tag` y similares. "Etiquetas del `<head>`" son `<title>`, `<meta>`, `<link>` y el JSON-LD. "Nombre de ruta" es `home`; "path" es `/`; "URL" es la dirección absoluta.
*   "Dueño" es el propietario de Humetría, que autoriza los cambios de comportamiento. "Autor" es quien firma e implementa.
*   Los números de entrada de este plan son nuevos (`E-1`, `E-2`…). Ninguno viene del plan v1: cada archivo se abrió o se comprobó que no existe.
*   "S-A" y "S-B" son las alternativas de ubicación del proceso SSR del handoff 8.1. Las letras A a P son las alternativas del plan v1 (sección 15 y apéndice B).

--------------------------------------------------------------------------------

## 1. Resumen ejecutivo

**Problema.** `curl https://humetria.com.mx/` devuelve un documento sin título propio, sin descripción y sin texto: la landing se escribe en el navegador con React. Buscadores que no ejecutan JavaScript, redes sociales y asistentes de IA no ven qué hace Humetría. El plan v1 resolvía las etiquetas con Blade y el texto con un bloque `<noscript>` escrito a mano; el dueño rechazó el `<noscript>` porque duplica el contenido comercial.

**Decisión del dueño (handoff 1).** La landing usa SSR real de Inertia: los mismos componentes React se renderizan en Node, el HTML trae el contenido y React lo hidrata. Solo las páginas comerciales autorizadas, de forma selectiva y deny-by-default.

**Arquitectura propuesta, en una página.**

1.  **Quién pasa por SSR.** Una lista cerrada, `App\Http\SsrPages`, con dos páginas: `home` (componente `landing`) y `privacy-notice`. La regla se aplica con `Inertia::disableSsr(closure)`, que exige además que nadie tenga sesión y que el componente que se va a renderizar esté en la lista (así una página de error servida en `/` no pasa). Es una lista distinta de `IndexablePages` (sección 4).
2.  **Qué recibe Node.** Un objeto de página con solo las propiedades compartidas de un visitante anónimo. Una prueba afirma el conjunto exacto de llaves.
3.  **Etiquetas del `<head>`.** Salen del `<Head>` de Inertia con SSR, a partir de una sola fuente de datos: la propiedad `seo` que arma PHP desde `lang/es/seo.php`. Cuando Node no responde, Inertia ya imprime el contenido del slot de `<x-inertia::head>` (documentado: el slot se usa solo cuando el SSR no está activo); ese slot recibe las mismas etiquetas, escritas desde la misma propiedad `seo` y con las mismas llaves `data-inertia`, para que el cliente las reemplace y no queden duplicadas.
4.  **Contenido.** Se corrigen los componentes, no se duplican: las nueve herramientas quedan en el HTML como paneles de pestañas (WAI-ARIA), uno visible y ocho ocultos con una clase que solo aplica con JavaScript (no con `hidden`: el preflight de Tailwind lo fuerza a `display: none !important`, así que sin JavaScript no se podrían leer); las entradas animadas dejan de escribir `style` en el servidor (CSS en una hoja de estilos en su lugar); los videos se montan solo en el navegador; el tema y el año del pie se resuelven sin diferencias de hidratación.
5.  **Operación.** Un servicio `ssr` aparte en `compose.production.yaml`, con una imagen solo de Node construida del mismo commit, sin `.env`, en una red interna compartida solo con `web`, con un healthcheck que renderiza la landing de verdad y un vigilante que reinicia el proceso si se cuelga. Si Node falla, la landing responde 200 sin cuerpo renderizado y con las etiquetas del slot, y el fallo queda registrado y se detecta desde fuera: una comprobación sintética después de cada despliegue y cada 15 minutos.
6.  **Medición.** GA4 con `gtag.js` directo, solo en el documento de la landing para visitantes sin sesión, con el consentimiento en `denied` por omisión hasta que el abogado del dueño decida (por lo que hoy no hay medición). La atribución pasa de "primer toque en una sesión de 120 minutos" a primer y último toque en una cookie propia de 90 días que solo se escribe con consentimiento: mientras esté en `denied`, la atribución se limita a la sesión.

**Lo que se conserva del plan v1:** `noindex` por defecto, lista de páginas indexables, sitemap estático, aviso de privacidad, GA4 solo en la landing, procedencia de la solicitud de demo, redirección de `www`.

**Lo que se retira del plan v1:** el `<noscript>`, `landing-text.blade.php`, la prueba que compara cadenas entre PHP y TSX, y la idea de que el SSR es "mejora futura".

**Cambios de comportamiento ya autorizados por el dueño** (sección 18): nueve paneles de herramientas (Q1, con el cambio de `tests/Browser/LandingTest.php:309` y las aserciones nuevas de 5.5), animaciones de entrada en CSS (Q2, C1), servicio e imagen de Node (Q4, S-B) y la llave `first_contact` de la consola de demos (Q10).

--------------------------------------------------------------------------------

## 2. Investigación del repositorio

### 2.1 Versiones (comprobado)

| Pieza | Versión | Fuente |
| :-- | :-- | :-- |
| PHP | `^8.3` en composer; la imagen de producción usa 8.5 | `composer.json:8`, `Dockerfile:7` |
| Laravel | 13.42.0 | `composer.lock` |
| `inertiajs/inertia-laravel` | 3.5.2 | `composer.lock` |
| `@inertiajs/react`, `@inertiajs/core`, `@inertiajs/vite` | 3.9.1 | `package-lock.json:493,512,527` |
| React, React DOM | 19.3.1 | `package-lock.json:5602,5633` |
| `motion` | 14.10.1 | `package-lock.json:5211` |
| `laravel-vite-plugin` | 3.2.0 | `package-lock.json:4790` |
| Vite | `vite-plus` 1.4.0 (`vite` aliasado a `@voidzero-dev/vite-plus-core@1.4.0`) | `package.json:70`, `package-lock.json:6436` |
| Node en el build | `node:24-trixie-slim` (solo en la etapa `assets`) | `Dockerfile:8,33-39` |
| Node en la imagen final | **ninguno** | `Dockerfile:42-56` |
| Pest / Pest Browser | 5.3.0 / 5.1.2 | `composer.lock` |

La documentación consultada es la de Inertia v3 (herramienta `search-docs` de Laravel Boost, resultados de `inertiajs/inertia-laravel@3.x`) y el código instalado en `vendor/` y `node_modules/`. No se consultó la de React, `motion`, Traefik ni Dokploy: lo que dependa de ellas está marcado "sin verificar". No se propone actualizar ninguna versión.

### 2.2 Hallazgos que el plan v1 afirmaba y ahora están comprobados

| # | Afirmación | Evidencia |
| :-- | :-- | :-- |
| H1 | La configuración activa el SSR pero no existe bundle ni proceso en producción | `config/inertia.php:21` (`'enabled' => true`); `bootstrap/ssr` está en `.gitignore:2`; `Dockerfile:33-56` solo corre `npm run build` (`:39`) y no copia Node ni `bootstrap/ssr`; `compose.production.yaml:40-117` no tiene servicio SSR. Existe el script `build:ssr` (`package.json:6`) pero nadie lo llama. |
| H2 | `app.tsx` no puede correr en Node | `resources/js/app.tsx:15-19`: `await preloadFrame(initialPageName(document.querySelector(...)))` en el nivel superior; `document` no existe en Node. |
| H3 | El plugin de Inertia usa `app.tsx` como entrada del SSR y envuelve `createInertiaApp` | `node_modules/@inertiajs/vite/dist/index.js:619-650,692-722` (la transformación solo ocurre en el build SSR). El servidor que arranca escucha en `0.0.0.0` por omisión y expone `/shutdown` sin autenticación, y cualquier método HTTP: `node_modules/@inertiajs/core/dist/server.js` (`host = "0.0.0.0"`, rutas `/health`, `/shutdown`, `/render`). El plugin acepta `inertia({ ssr: { host, port, cluster, formatErrors, entry } })` (`index.js:729`). |
| H4 | `laravel-vite-plugin` usaría `[css, app.tsx]` como entrada SSR | `node_modules/laravel-vite-plugin/dist/index.js:1516` (`ssr: config.ssr ?? config.input`) y `:1530-1538`. Se corrige con la opción `ssr` del plugin. |
| H5 | `HttpGateway` devuelve `null` ante cualquier fallo | `vendor/inertiajs/inertia-laravel/src/Ssr/HttpGateway.php:46-88`. Casos: SSR apagado, bundle ausente (`:52`), respuesta fallida (`:63-67`), JSON vacío (`:69`), excepción de conexión (`:77-87`). Solo se relanzan `StrayRequestException` y `SsrException`. La documentación oficial lo confirma: "A failed render is not fatal to the request". |
| H6 | Con un closure en `Inertia::disableSsr()` el paquete deja de leer `inertia.ssr.enabled` | `HttpGateway.php:175-181`. Existen también `Inertia::withoutSsr(paths)` (`ResponseFactory.php:231`) y `configureSsrRequestUsing` (`:245`). |
| H7 | Se despacha el objeto de página completo | `Ssr/SsrState.php:44-52`: `app(Gateway::class)->dispatch($this->page)`. `Head` y `App` lo llaman en su constructor (`View/Components/Head.php:13-16`, `App.php:17-24`). El evento `SsrRenderFailed` lleva `page` completo (`SsrRenderFailed.php`). |
| H8 | `HandleInertiaRequests::share()` comparte datos en todas las páginas | `app/Http/Middleware/HandleInertiaRequests.php:43-67`: `auth.user` (el modelo completo), `isSuperadmin`, `isStaff`, `impersonator`, `tenant`, `support.email`, `sidebarOpen` desde una cookie. |
| H9 | `<x-inertia::head>` sustituye su contenido con lo que devuelve Node; el slot solo se usa sin SSR | `View/Components/Head.php:20-26`; documentación v3, "SSR Fallback". |
| H10 | Con SSR, el cuerpo trae el JSON de página y el `<div id="app" data-server-rendered>` | `node_modules/@inertiajs/core/dist/index.js:5510-5513`. El cliente hidrata solo si existe `data-server-rendered`: `node_modules/@inertiajs/react/dist/index.js:326-330`. |
| H11 | El gestor de `<head>` del cliente solo toca elementos con `data-inertia` y los empareja por esa llave | `core/dist/index.js:4180-4230`; `react/dist/index.js:1208` (`head-key` pasa a `data-inertia`). Un `<title>` sin `data-inertia` se elimina si el cliente genera uno. Consecuencia: las etiquetas del slot de Blade solo se reemplazan, y no se duplican, si llevan `data-inertia="<llave>"`. |
| H12 | `useAppearance` calcula el tema resuelto con una lectura distinta del servidor | `resources/js/hooks/use-appearance.tsx:110-112` llama a `isDarkMode()` (que lee `matchMedia`) en el render; el snapshot de servidor solo cubre `appearance` (`:104-108`). |
| H13 | `landing-footer.tsx` calcula el año al cargar el módulo | `resources/js/components/landing/landing-footer.tsx:8`. En un proceso Node de larga duración queda congelado hasta el siguiente reinicio. Hallazgo nuevo respecto a v1. |
| H14 | `motion` escribe el estado inicial como atributo `style` en el servidor | Usos de `initial=` con `opacity: 0`: `reveal.tsx:17-21`, `hero-headline.tsx:16-20`, `demo-next-steps.tsx:21`, `inspection-line.tsx:17`, `how-it-works-step.tsx:23,43`, `loop-video.tsx:49`, `privacy-demo.tsx:38`, `tool-stage.tsx:21-25`, `how-it-works-section.tsx:53,62`, `step-bar.tsx:17`. La CSP no admite estilos en atributo: `SecurityHeaders.php:112-123` no define `style-src-attr`, y `style-src` solo admite `'self'`, el nonce y dos hashes de sonner; un nonce no autoriza atributos. Que el navegador bloquee esos atributos al analizar el HTML es el comportamiento de CSP3; el efecto exacto en la landing está **sin verificar** (medido, sección 17). |
| H15 | La sección de herramientas monta un solo panel | `tools-section.tsx:20-22,49`; `tool-tabs.tsx:30-31` muestra solo las tres herramientas de la familia elegida. La prueba existente `tests/Browser/LandingTest.php:251-257` afirma `querySelectorAll("#herramientas [data-test=tool-panel]").length === 1`; `:309` lee `getElementById("tool-panel")`. |
| H16 | Las pruebas bloquean peticiones externas | `tests/TestCase.php:18` (`Http::preventStrayRequests()`); `HttpGateway.php:78-80` relanza `StrayRequestException`. |
| H17 | Ningún workflow construye la imagen en los PR; `deploy.yml` no comprueba nada después de desplegar | `.github/workflows/tests.yml` (jobs `checks`, `tests`, `browser`, `ci`); `.github/workflows/deploy.yml:24-56`. |
| H18 | `.env.example` debe contener toda llave que lea `config/` | `tests/Unit/EnvExampleTest.php` existe (contenido no releído en esta revisión; el plan v1 y `CLAUDE.md` lo afirman). |
| H19 | No existe herramienta de alertas ni métricas en el repositorio | `composer.json` no lista Pulse, Nightwatch ni Telescope (solo aparecen como variables en `phpunit.xml:44-49`). Los logs van en JSON a stderr y se leen en la pestaña Logs de Dokploy. El patrón de aviso que sí existe es "el workflow falla y GitHub manda un correo" (`CLAUDE.md`, sección de mutación). |

### 2.3 Hallazgos nuevos que el plan v1 no tenía

*   **N-1. Nunca se ejercitaría el SSR en las pruebas actuales.** `phpunit.xml` debe forzar el SSR apagado (el intento lanzaría `StrayRequestException`) y las pruebas de navegador corren contra el build normal sin Node. Ninguna prueba de la suite actual vería un fallo de hidratación. Hace falta una prueba de navegador con el servidor SSR real (sección 13).
*   **N-2. Docker no reinicia un contenedor "unhealthy".** `restart: unless-stopped` actúa cuando el proceso sale, no cuando el healthcheck falla. Un Node colgado (bucle infinito) seguiría "unhealthy" sin reiniciarse. Por eso la propuesta incluye un vigilante dentro del contenedor (sección 7.5). Es conocimiento general de Docker, no verificado en esta revisión contra Dokploy.
*   **N-3. El evento `SsrRenderFailed` y el log de Node llevan la URL y la pila.** `core/dist/server.js` escribe `URL: ...` y la pila con `console.error`; el evento lleva `page` completo. La landing puede recibir una query con UTM; ninguna de las dos páginas con SSR lleva tokens, pero el listener debe registrar solo el path.
*   **N-4. El año congelado y cualquier estado de módulo** se vuelven un riesgo de proceso largo (sección 5.7).
*   **N-5. Inertia v3 trae `WhenMounted`** (`react/dist/index.js:2050-2063`): renderiza `fallback` en el servidor y durante la hidratación, y los hijos después. Resuelve los videos sin escribir un hook propio.
*   **N-6. `x-inertia::head` documenta el fallback por slot.** Es el mecanismo oficial para la degradación de las etiquetas (sección 6).

--------------------------------------------------------------------------------

## 3. Arquitectura SSR

### 3.1 Flujo

```text
GET /   (navegador, buscador, red social, asistente de IA)
  │
  ▼
Traefik (Dokploy) ── www → 301 a humetria.com.mx  (tarea de despliegue, sección 7.8)
  │
  ▼
web: nginx → php-fpm (Laravel)
  SecurityHeaders (global, primero):  CSP con nonce · X-Robots-Tag salvo IndexablePages
  Web middleware:  HandleInertiaRequests::share() → props compartidas
  Route home →  Inertia::render('landing', ['seo' => PageSeo::for('home')])
  Vista app.blade.php
     ├─ <x-inertia::head>                   ← Head::__construct() llama a SsrState::dispatch()
     │        └─ SsrPages::allows(request, page) ?
     │             no  → sin SSR: imprime el slot de Blade (título y etiquetas desde `seo`)
     │             sí  → POST http://ssr:13714/render  (page JSON, timeout 2 s)
     │                      │
     │                      ▼
     │        ssr (Node, sin .env, red interna)
     │          bundle SSR (app.tsx envuelto por @inertiajs/vite)
     │          renderToString(<Landing/>) → { head: [<title…>, <meta…>, <script ld+json>], body }
     │             ├─ 200 → head y body se imprimen en el documento
     │             └─ falla o tarda → null, evento SsrRenderFailed → listener registra ssr.render_failed
     │                                 → el documento sale como CSR con el slot de Blade (200)
     └─ <x-inertia::app />                  ← SsrState devuelve la misma respuesta (caché por petición)
           body SSR: <script data-page …json…></script><div id="app" data-server-rendered="true">HTML</div>
           sin SSR:  <script data-page></script><div id="app"></div>

Navegador: app.tsx → createInertiaApp
   data-server-rendered presente → hydrateRoot (reusa el DOM)
   ausente                       → createRoot (CSR, también si Node falló)
   El gestor de <head> empareja por data-inertia: el slot de Blade y lo de React no se duplican.
```

### 3.2 Entrada SSR, Vite y bundles

*   **Entrada.** No se crea `ssr.tsx`: el plugin de Inertia usa `resources/js/app.tsx` y envuelve su `createInertiaApp` con el arranque del servidor (`@inertiajs/vite/dist/index.js:16-45` para React). Dos condiciones, ambas por corregir: (a) el nivel superior de `app.tsx` no puede leer `document` ni hacer `await` sobre él (H2); (b) el plugin de Laravel debe declarar `resources/js/app.tsx` como única entrada SSR con `laravel({ input: [...], ssr: 'resources/js/app.tsx' })` (H4; la opción existe: `index.js:1489,1516`). La salida SSR va a `bootstrap/ssr` (`index.js:1517`). **Medido (sección 17):** con esos dos cambios `npm run build:ssr` termina sin errores.
*   **Nombre del bundle (medido).** La entrada queda en `bootstrap/ssr/app.js` y `BundleDetector` la encuentra sin `inertia.ssr.bundle` (sección 17).
*   **Dependencias dentro del bundle (medido).** `ssr: { noExternal: true }` en `vite.config.ts` funciona con `vite-plus` 1.4.0: el bundle solo importa módulos propios de Node y el directorio pasa de 15 MB a 32 MB, de modo que la imagen de Node no necesita `node_modules`.
*   **Resolución de páginas.** El plugin transforma la resolución de páginas (`index.js:723-748`); el bundle SSR contendrá, como trozos perezosos, todas las páginas. No se ejecutan salvo que se les pida renderizar, y la política de la sección 4 solo permite dos. Aun así, el bundle contiene el código de páginas privadas: por eso la imagen de Node no recibe `.env`, ni base de datos, ni red externa, y el arranque medido en la sección 17 no falla al importar ningún módulo en Node (`inertia:start-ssr` arranca y `/health` responde).
*   **Providers.** `withApp` (`app.tsx:30-37`) envuelve en `TooltipProvider` y `Toaster`. `sonner` protege su inyección de estilos con `typeof document` (`node_modules/sonner/dist/index.mjs`, comprobado con `grep`), así que importarlo en Node no falla. Los hashes de la CSP para sonner siguen vigentes (`SecurityHeaders.php:29`); la hidratación con el `Toaster` en claro se midió sin errores (sección 17).
*   **Layouts.** `layout: frameFor` (`app.tsx:23`) decide el marco por nombre de página. `landing` y `privacy-notice` son páginas de marco propio (`PageFrame::STANDALONE_NAMES`, `app/Http/PageFrame.php:15-36`), de modo que el servidor no renderiza el marco del panel.

### 3.3 Hidratación

*   React hidrata con `hydrateRoot` cuando el HTML trae `data-server-rendered` (`react/dist/index.js:326-330`). Con `strictMode: true` (`app.tsx:24`) el desarrollo muestra advertencias adicionales.
*   React 19 repara diferencias de **texto** y de estructura, pero **no parcha atributos distintos** en producción (comportamiento conocido de React, **sin verificar** contra esta versión; la causa exacta del error 418 medido en oscuro no se aisló: la sospecha es el póster de los videos, sección 17). Por eso los atributos que dependen del navegador (rutas de video, póster, `autoplay`) no se renderizan en el servidor (sección 5.4).
*   Criterios de aceptación de hidratación: cero errores de React en consola; cero violaciones de CSP; ningún elemento duplicado; el formulario conserva lo escrito si la hidratación llega tarde; sin parpadeo del texto principal.

--------------------------------------------------------------------------------

## 4. Estrategia selectiva (deny-by-default)

### 4.1 Política

**Solo se renderiza en Node una petición que cumple las cinco condiciones a la vez:**

1.  `config("inertia.ssr.enabled")`, que lee `INERTIA_SSR_ENABLED`, es verdadero;
2.  el nombre de ruta y el componente de la página están en `App\Http\SsrPages::ALLOWED`, que asocia cada nombre de ruta con su componente: `home` → `landing`, `privacy-notice` → `privacy-notice`;
3.  nadie tiene sesión (`$request->user() === null`);
4.  es una carga inicial de documento (las visitas XHR de Inertia no llaman al gateway: lo confirma el código, que solo despacha desde `Head` y `App`).
5.  el cortacircuitos está cerrado: tras un fallo de conexión con Node, `SsrPages` devuelve falso durante 30 segundos (7.6, punto 5).

Todo lo demás conserva su comportamiento de hoy: `/admin`, panel de empresa, consola de superadmin, encuestas, buzón de quejas, portales, reportes compartidos, enlaces con token, reloj checador, login, páginas de error.

**Mecanismo.** `Inertia::disableSsr(fn (): bool => ! SsrPages::allows(request(), app(SsrState::class)->page))` registrado en `AppServiceProvider::boot()` (el closure se evalúa de forma perezosa: `HttpGateway.php:178`). El interruptor va dentro de `SsrPages::allows()`, que lee `config('inertia.ssr.enabled')` (no `env()`: la configuración se cachea) porque, con un closure registrado, el paquete ya no lee `inertia.ssr.enabled` (H6). `SsrState::page` es pública (`Ssr/SsrState.php:12`), por lo que el closure puede leer el componente. **Comprobado:** el paquete registra `SsrState` como `scoped` (`vendor/inertiajs/inertia-laravel/src/ServiceProvider.php:32`), así que `app(SsrState::class)` devuelve el mismo objeto que usan `Head` y `App` dentro de una petición. La pieza 3 lo fija además con un caso que renderiza la página de error en `/`.

`SsrPages` es una clase `final` del kernel compartido, junto a `PageFrame` e `IndexablePages`, sin importar nada de ningún módulo.

### 4.2 Separación de responsabilidades

| Lista | Responde | Quién la lee en ejecución | Quién la compara |
| :-- | :-- | :-- | :-- |
| `IndexablePages::ROUTES` | ¿Esta respuesta puede indexarse? | `SecurityHeaders` (cabecera HTTP), `PageSeo` (etiquetas) | `IndexablePagesTest` compara el sitemap |
| `SsrPages::ALLOWED` | ¿Esta página se renderiza en Node? | el closure de `disableSsr` | `SsrPagesTest` |

Hoy las dos listas tienen los mismos dos elementos. No se deriva una de la otra: una página futura (por ejemplo `/herramientas/nom-035`) puede ser indexable y renderizarse en Node, pero una página con SSR **nunca** es indexable por omisión ni al revés. Una prueba exige que `SsrPages::ALLOWED` sea subconjunto de las rutas públicas GET sin parámetros (el mismo criterio que `IndexablePages`) y que cada nombre exista.

### 4.3 Pruebas de la política (se detallan en la sección 13)

*   Prueba de tabla: para cada ruta GET registrada que se pueda resolver (con parámetros inventados para las de token), con `Http::fake()` y SSR encendido, no sale ninguna petición hacia el servidor SSR salvo `home` y `privacy-notice` para un visitante.
*   Con sesión iniciada, `GET /` no hace petición a Node.
*   La página de error servida en `/` (500 forzado) no hace petición.
*   Una ruta nueva sin alta en la lista no hace petición (la prueba de tabla es introspectiva: recorre `Route::getRoutes()`).

--------------------------------------------------------------------------------

## 5. Plan de compatibilidad

### 5.1 Matriz de componentes incompatibles con SSR (auditoría del handoff 3.3)

Severidad: **Bloqueante** (el bundle no carga o la página no se renderiza), **Alta** (se renderiza pero rompe hidratación, CSP o el requisito de contenido), **Media**, **Baja**. "Comprobado" significa que el autor leyó la línea.

| # | Archivo | Problema | Causa | Severidad | Corrección propuesta |
| :-- | :-- | :-- | :-- | :-- | :-- |
| 1 | `resources/js/app.tsx:15-19` | El bundle lanza `ReferenceError: document is not defined` al cargar, antes de renderizar nada | `await preloadFrame(... document.querySelector(...))` en el nivel superior | Bloqueante | Ejecutar la precarga solo si existe `document`. Con `typeof document !== 'undefined'` en una función aparte, sin `await` de nivel superior en Node. El archivo sigue sin importar los marcos de `@/layouts/*` (lo exige `tests/Unit/FrontendArchitectureTest.php:229-233`). |
| 2 | `app.tsx:11,43-44` | `__webpack_nonce__` y `initializeTheme()` | `cspNonce` usa `globalThis.document` y `initializeTheme` ya sale si no hay `window` (`csp-nonce.ts:17`, `use-appearance.tsx:80-82`) | Baja (ya compatible) | Sin cambio; una prueba Vitest sobre `readCspNonce(undefined)` ya existe o se agrega (`resources/js/lib`). |
| 3 | `use-appearance.tsx:110-112` | El tema resuelto cambia entre servidor y cliente; en un navegador oscuro el icono del menú y las rutas de video difieren | `resolvedAppearance` llama a `isDarkMode()` (lee `matchMedia`) en el render; el snapshot de servidor solo protege `appearance` | Alta | Obtener `resolvedAppearance` con `useSyncExternalStore(subscribe, getClient, () => 'light')`; durante la hidratación React usa el snapshot de servidor y vuelve a renderizar con el real. Un cambio de `matchMedia` ya dispara `notify`. |
| 4 | `loop-video.tsx:31,41-72`, `tool-film-player.tsx:13,26-52` | `<video autoplay poster src>` dependen del tema y de `prefers-reduced-motion` | `useAppearance`, `useReducedMotion` y `autoPlay={!still}`: atributos que React no corrige al hidratar | Alta | Montar el `<video>` solo en el navegador con `WhenMounted` de `@inertiajs/react` (comprobado: `react/dist/index.js:2050-2063`), con un `fallback` del mismo tamaño (`aspect-video`). Sin dependencia nueva. El texto del video (`figcaption`, `sr-only description`) sí sale en el servidor. |
| 5 | `landing-footer.tsx:8` | Año congelado en el proceso Node hasta que reinicie | `new Date().getFullYear()` al cargar el módulo | Media | Calcularlo al renderizar (`new Date().getFullYear()` dentro del componente). Servidor y cliente coinciden salvo el 31 de diciembre a medianoche: se acepta (React repara texto). |
| 6 | `landing-hero.tsx:11-15,37-74`, `reveal.tsx:16-33`, `hero-headline.tsx:14-39`, `demo-next-steps.tsx:19-22`, `inspection-line.tsx:16-18`, `how-it-works-step.tsx:21-44`, `loop-video.tsx:48-50`, `tool-stage.tsx:19-33`, `privacy-demo.tsx:35-39`, `step-bar.tsx:16-22`, `how-it-works-section.tsx:52-63` | El servidor escribe `style="opacity:0;transform:…"` en el HTML del texto principal. La CSP no admite estilos en atributo: el navegador bloquea el atributo (violación) y, tras hidratar, `motion` vuelve a ocultar y animar. Sin JavaScript el resultado depende de ese bloqueo, no de un diseño. | `m.*` con `initial={{ opacity: 0 }}` se serializa a atributo `style` | Alta (CSP, legibilidad sin JavaScript, parpadeo); medido: 53 atributos `style`, 42 con `opacity:0`, 53 violaciones | Sección 5.3 (C1, decidido). |
| 7 | `tools-section.tsx:20-22,49`, `tool-tabs.tsx:30-31`, `tool-stage.tsx:11-17` | El HTML solo trae una herramienta y los nombres de las tres de su familia | Se monta el panel de una sola herramienta; el resto de la información existe solo tras el clic | Alta (requisito de contenido); medido: un solo `role="tabpanel"` | Sección 5.5 (nueve paneles, decidido). |
| 8 | `use-hash-tab.ts` | Si la URL trae `#herramienta-x` el cliente cambia de herramienta después de hidratar | La lectura de `window.location.hash` es un efecto (comprobado: `useEffect`), no el render | Baja | Sin cambio: el efecto corre después de la hidratación y no causa diferencia. Se afirma con la prueba existente "opens on the tool the link names" (`LandingTest.php:~280`). |
| 9 | `landing-theme-menu.tsx`, Radix `DropdownMenu` | Radix usa `useId` y portales | El portal solo se monta abierto (sin verificar para la versión instalada) | Baja | La hidratación en claro se midió sin errores (sección 17); el menú abierto no se midió. |
| 10 | `use-media-query.ts`, `use-scrolled.ts` | Lecturas de `window` | Ya tienen snapshot de servidor (`() => false`) | Baja (ya compatible) | Sin cambio. |
| 11 | `hero-families.tsx:22-30` | `window`, `document` | Dentro de un manejador `onClick`, nunca durante el render | Baja (ya compatible) | Sin cambio. |
| 12 | `film-chapters.tsx:48` | `style={{ transform }}` en el capítulo activo | Solo aparece con el video montado (cliente) | Media | Queda fuera del servidor al montar el reproductor con `WhenMounted` (fila 4). Si el reproductor se montara en el servidor, el atributo violaría la CSP. |
| 13 | `landing.tsx:19-22`, `LazyMotion strict`, `MotionConfig nonce` | `useReducedMotion` en el servidor y `MotionConfig reducedMotion="user"` | `useReducedMotion` solo se usa en `loop-video.tsx:31` y `tool-film-player.tsx:13`, que pasan a `WhenMounted` (fila 4): el servidor nunca lo evalúa | Baja | No hace falta medirlo. |
| 14 | `app.tsx:32-35` (`Toaster`, `TooltipProvider`) | `sonner` inyecta estilos al importar | Guarda `typeof document` (comprobado con `grep` en `node_modules/sonner/dist/index.mjs`) | Baja | Sin cambio. Los hashes de la CSP de `SecurityHeaders.php:29` siguen vigentes. |
| 15 | `useId` en componentes del marco | Identificadores inconsistentes | React 19 los hace estables entre servidor y cliente si el árbol coincide | Baja | Sin cambio; la hidratación en claro se midió sin errores (sección 17). |
| 16 | Importaciones con efectos secundarios | `leaflet`, `@xyflow/react`, `driver.js` | Se cargan al importar páginas del panel, que son trozos perezosos del bundle | Baja | el arranque medido en la sección 17 no falló al importar ningún módulo. |
| 17 | Portales | `createPortal` en la landing | Sin usos encontrados en `components/landing/`; solo Radix al abrir | Baja | Sin cambio. |
| 18 | `app.tsx:26-28` | `window.location.href` | Dentro de la función `visitOptions` (solo navegación) | Baja | Sin cambio. |

No se encontraron en `components/landing/`: `localStorage`/`sessionStorage` durante el render, `Math.random`, `Date.now` ni `navigator`.

### 5.2 Tema claro y oscuro

*   El documento ya recibe `class="dark"` desde Blade según la cookie `appearance` (`resources/views/app.blade.php:2`, `HandleAppearance.php`) y un script con nonce añade `dark` si el sistema lo pide (`app.blade.php:12-24`). Ese script corre antes de pintar y no se toca.
*   El marcado del servidor es el del tema claro en lo que depende de JavaScript (icono del menú, selector del pie); el CSS de Tailwind usa la clase `dark` del `<html>`, de modo que colores y fondos ya son correctos antes de hidratar.
*   Los videos eligen su archivo por tema: solo en el navegador (fila 4).
*   Prueba: claro y oscuro, escritorio (1280) y 375 px, con la suite de navegador normal y con la suite SSR (sección 13).

### 5.3 Animaciones (decidido: C1, entradas en CSS)

**Decisión (Q2, sección 18).** Las entradas se reescriben en CSS y no se abre `style-src-attr`. Se decidió después de medir el estado actual: 53 atributos `style` en el HTML del servidor (42 con `opacity:0`, incluidos los diez `<span>` del `<h1>`) y 53 violaciones de `style-src-attr` (sección 17). C2 (abrir la directiva) y C3 (`div` plano que se vuelve `m.div` al hidratar) quedan descartadas: C2 contradice el handoff 6.2 y 6.3; C3 remonta lo que envuelve y parpadea.

**Estado por defecto: visible.** Sin JavaScript, o antes de que cargue, el contenido se ve completo. JavaScript solo agrega la animación.

**Tres mecanismos, ningún atributo `style` escrito por el servidor:**

1.  **Entrada al cargar (héroe).** Clases con `@keyframes` en `resources/css/app.css` y `animation-fill-mode: both`; los retrasos se escriben como clases estáticas (por ejemplo `[animation-delay:150ms]`), porque un `style` por palabra sería un atributo.
2.  **Revelado al desplazarse (bajo el pliegue).** El componente lleva `data-reveal` y una clase de tipo (`reveal-rise`, etc.) con `transition`. El hook `useScrollReveal()` (nuevo, `resources/js/hooks/use-scroll-reveal.ts`, instalado en `pages/landing.tsx`) corre en un efecto después de hidratar: para cada `[data-reveal]` cuyo borde superior queda bajo el pliegue pone `data-reveal="pending"` (el CSS lo oculta con la transformación de su tipo) y lo observa con `IntersectionObserver` (`threshold: 0.2`, como el `amount: 0.2` de `landing-motion.ts:10`); al entrar pasa a `data-reveal="done"` y deja de observarlo. Lo que ya está a la vista no se oculta, así que no hay parpadeo. `setAttribute` y `classList` no son estilos en atributo y la CSP no los bloquea.
3.  **Interacción (cambio de herramienta, `privacy-demo`).** `tool-stage.tsx` usa una clase de `@keyframes` que se reproduce al montar el elemento (el `key` por herramienta ya lo remonta); `privacy-demo.tsx` envuelve `AnimatePresence` en `WhenMounted` con el resultado sin animar como `fallback`.

**Por qué no `animation-timeline: view()`.** Las animaciones ligadas al desplazamiento no tienen fin hasta que se recorren: `visitPage()` espera `animation.finished` de todo `document.getAnimations()` (`tests/Pest.php:329`) y se quedaría esperando. Las transiciones de CSS son finitas y esa espera termina.

**Valores exactos** (tomados de los componentes actuales; `EASE_OUT` = `cubic-bezier(0.23, 1, 0.32, 1)`, `landing-motion.ts:4`; `ENTER` = 0,6 s, `:7`):

| Componente (línea) | Estado inicial → final | Duración y retraso | Disparo | Clase CSS |
| :-- | :-- | :-- | :-- | :-- |
| `hero-headline.tsx:14-39`, 10 palabras | `opacity:0`, `translateY(0.35em)`, `blur(6px)` → `1`, `0`, `0` | 0,7 s; retraso `0,1 + i×0,05 s` (0,10 a 0,55 s) | al cargar | `enter-word` + 10 clases de retraso literales |
| `landing-hero.tsx:11-15` (`after`), cuatro bloques | `opacity:0`, `translateY(12px)` → `1`, `0` | 0,6 s; retrasos 0,55 (párrafo), 0,65 (botones), 0,2 (película), 0,75 (familias) s | al cargar | `enter-after` + 4 retrasos |
| `reveal.tsx:16-33` | `opacity:0`, `translateY(16px)`, `blur(4px)` → `1`, `0`, `0` | 0,6 s; retraso = prop `delay` (mapeada a una tabla de clases literales) | al desplazarse, una vez | `reveal-rise` |
| `demo-next-steps.tsx:19-29` | `opacity:0`, `translateX(-8px)` → `1`, `0` | 0,6 s; retraso `0,1 + i×0,08 s` | al desplazarse | `reveal-slide-8` |
| `inspection-line.tsx:16-18` | `opacity:0`, `translateX(-10px)` → `1`, `0` | 0,4 s; retraso `i×0,08 s` | al desplazarse | `reveal-slide-10` |
| `how-it-works-step.tsx:21-30` (número) | `opacity:0`, `scale(0.6)` → `1`, `1` | resorte de 0,5 s con rebote 0,3 y retraso `i×0,12 s`; en CSS `cubic-bezier(0.34, 1.56, 0.64, 1)` (aproximación, sin verificar al ojo); umbral 0,6 | al desplazarse | `reveal-pop` |
| `how-it-works-step.tsx:42-50` (tarjeta) | `opacity:0`, `translateY(20px)` → `1`, `0` | 0,7 s; retraso `0,1 + i×0,12 s` | al desplazarse | `reveal-rise-20` |
| `loop-video.tsx:48-51` | `opacity:0`, `scale(0.96)` → `1`, `1` | 0,9 s; retraso 0,2 s | al desplazarse | `reveal-scale` |
| `step-bar.tsx:16-31` | `scaleX(0)` → `scaleX(value)` | 0,9 s; retraso del prop (0,3 por omisión; 0,2 en `inspection-section.tsx:94`) | al desplazarse | `reveal-bar`; la barra pasa a ser un `<svg>` con un `<rect width="{value×100}%">` (atributo de presentación, no `style`) cuya transformación `scaleX` parte de 0 |
| `how-it-works-section.tsx:61-70` (riel de escritorio) | `scaleX(0)` → `1` | 1,2 s; retraso 0,2 s | al desplazarse | `reveal-bar` |
| `how-it-works-section.tsx:52-54` (riel móvil, `scaleY: scrollYProgress`) | proporcional al desplazamiento | continuo | al desplazarse | el `<span>` deja de ser `m.span`: `useScroll` sigue y `useMotionValueEvent` escribe `--p` con `element.style.setProperty` (CSSOM, que la CSP permite) y el CSS usa `transform: scaleY(var(--p, 0))` |
| `tool-stage.tsx:19-33` (detalles) | `opacity:0`, `translateY(8px)`, `blur(2px)` → `1`, `0`, `0` | 0,3 s | al montar (cada cambio de herramienta) | `enter-details` (`@keyframes`) |
| `privacy-demo.tsx:35-45` | intercambio con `blur(4px)` | 0,18 s | solo cliente | `WhenMounted` |

**Movimiento reducido.** Hoy `MotionConfig reducedMotion="user"` (`landing.tsx:19-22`) apaga transformaciones y deja las opacidades. En CSS: `@media (prefers-reduced-motion: reduce)` quita `transform` y `filter` de todas las clases anteriores y deja la transición de opacidad. Las pruebas existentes de movimiento reducido se refieren a video (`LandingTest.php:220`, `:317`, `:522`) y no cambian.

**Qué queda de `motion`.** `LazyMotion` y `MotionConfig` se quedan en `landing.tsx` para lo que se monte solo en el navegador (`privacy-demo`, `film-chapters`). Ningún `m.*` con estado inicial se renderiza en el servidor. `tests/Unit/LandingServerSafetyTest.php` (E-43) lo hace cumplir.

**Costo reconocido.** Es una reescritura de ≈10 componentes del rediseño #86; el resorte del número y el escalonado por índice se aproximan. Se revisan contra las capturas de `docs/design-system/pantallas/` en claro, oscuro y 375 px.

### 5.4 Videos

`LoopVideo` y `ToolFilmPlayer` se envuelven en `WhenMounted` con un `fallback` que conserva la caja (`aspect-video`, mismas clases). El servidor entrega el `<figure>`, su `figcaption` y la descripción para lectores de pantalla. Al hidratar aparece el `<video>` en el estado de hoy. Elegir la película con CSS (`dark:`) se descarta: dejaría dos `<video>` en el DOM y rompería las pruebas de navegador que leen el póster y las fuentes del primero (`tests/Browser/LandingTest.php:177,219-221,316-320`).

### 5.5 Las nueve herramientas en el HTML (decidido: nueve paneles)

**Decisión (Q1, sección 18).** Nueve paneles `role="tabpanel"` en el DOM, uno visible. Es el patrón de pestañas de WAI-ARIA: el contenido existe para todos y la persona lo revela con la pestaña; no es contenido para rastreadores. El diseño rompe el menor número de aserciones existentes.

**Diseño.**

*   **Se queda igual:** `FamilyTabs` (`family-tabs.tsx:52`, `aria-controls="tool-rail"`), la única `tool-rail` y las pestañas de la familia elegida (`tool-tabs.tsx:27-28,30-31`), `useToolTabs`, `useHashTab` y los `id="tool-tab-<key>"`. El HTML trae los nueve nombres y descripciones en los paneles, no en las pestañas.
*   **`ToolStage` pasa a `ToolPanels`** (`components/landing/tool-panels.tsx`): renderiza nueve `<div role="tabpanel" id="tool-panel-<key>" aria-labelledby="tool-panel-title-<key>">`. Cada uno lleva `<h3 id="tool-panel-title-<key>">` con el nombre, el `hint`, "Aplica a", los puntos y la línea de Humi. `aria-labelledby` apunta al `<h3>` del panel y no a una pestaña, porque las pestañas de otras familias no están en el DOM.
*   **`tool-tabs.tsx:44`:** `aria-controls={`tool-panel-${tool.key}`}`.
*   **El panel seleccionado** lleva `data-test="tool-panel"` y monta `ToolFilmPlayer` (con `WhenMounted`) y los detalles animados. **Los ocho restantes** llevan `data-test="tool-panel-idle"`, no montan video y llevan la clase `[html.js_&]:hidden` (selector `html.js .clase`, `display: none` solo con JavaScript).
*   **Por qué no el atributo `hidden`.** El preflight de Tailwind define `[hidden]:where(:not([hidden='until-found'])) { display: none !important }` (`node_modules/tailwindcss/preflight.css:396-398`): una clase no lo anula y, sin JavaScript, los ocho paneles seguirían ocultos.
*   **`html.js`.** El script inline que ya existe (`resources/views/app.blade.php:12-24`, con nonce) agrega `document.documentElement.classList.add('js')` al inicio. Sin JavaScript, la clase no está y los nueve paneles se ven apilados, cada uno con su `<h3>`. Con JavaScript, los ocho sobrantes quedan ocultos antes del primer pintado. Ninguna prueba afirma la lista de clases de `<html>` salvo que contenga `dark` (confirmado por la revisión).
*   **Sin contenido oculto para rastreadores:** el mismo texto lo lee una persona al elegir la pestaña o al desactivar JavaScript.

**Aserciones de `tests/Browser/LandingTest.php` afectadas** (se abrió el archivo; las líneas son las actuales):

| Línea | Valor actual | Valor nuevo | Cubierto por la autorización |
| :-- | :-- | :-- | :-- |
| `:256` | `querySelectorAll("#herramientas [data-test=tool-panel]").length` es `1` | **Sin cambio de valor**: sigue siendo `1`, porque solo el panel actual lleva `data-test="tool-panel"` | `:251-257` |
| (nuevas, después de `:256`) | no existen | `querySelectorAll("#herramientas [role=tabpanel]").length` es `9`; los paneles con `getComputedStyle(...).display !== 'none'` son `1` | `:251-257` |
| `:309` | `document.getElementById("tool-panel").getBoundingClientRect()` | `document.querySelector("#herramientas [data-test=tool-panel]").getBoundingClientRect()` (el mismo elemento; el `id` ya no es `tool-panel`) | `:309` |
| (nueva, en `LandingTest.php`) | no existe | cada pestaña revela su panel y oculta el anterior (`display` de `#tool-panel-<key>`) | nueva |
| (nueva, en `tests/Ssr/node/nojs.test.mjs`) | no existe | sin JavaScript, los nueve paneles son visibles | nueva |

**No cambian** (y por qué): `:257` (tres pestañas de familia, no se tocan); `:260` (`[data-test=tool-rail] [role=tab]` cuenta las pestañas de la familia: la rail sigue siendo una); `:264` (`[data-test=tool-tab-x] [data-test=tool-hint]`: la pestaña conserva su `tool-hint`; el panel usa otro marcado); `:266` (`#herramientas` no contiene "próximamente": ningún texto de `landing-tools.ts` lo trae, 0 coincidencias); `:265` y `:284` (`$panel` y `assertSeeIn('[data-test=tool-panel]', …)` toman el primer `[data-test=tool-panel]`, que es el seleccionado); `:292-295` y `:308` (la única `tool-rail`); `:318-337`, `:383-435` y `:457-458` (video, pestañas y familias). Tampoco los archivos de otras suites: `tests/` no menciona `tool-panel` fuera de `LandingTest.php`.

### 5.6 CSP

*   No cambia la política salvo los orígenes de Google (sección 9) y solo en `GET /` con ID de medición y sin sesión. La CSP de `SecurityHeaders.php:105-124` queda igual para el HTML SSR: el JSON de página y el JSON-LD son bloques de datos; el script de tema y su `<style>` llevan nonce; ningún `style` en atributo (C1); `sonner` conserva sus hashes.
*   Pruebas: `SecurityHeadersTest` afirma que la CSP de la landing con SSR es idéntica a la de hoy (sin `style-src-attr`, sin `unsafe-inline`, sin `unsafe-eval`); la prueba de navegador SSR afirma `cspViolations() === []`.
*   No se abre `style-src-attr` (decisión Q2). Cualquier excepción futura exige motivo, alcance mínimo, prueba y la autorización del dueño.

### 5.7 Estado de módulo y año

El año se calcula en el render (fila 5). Ningún módulo de `components/landing/`, `hooks/` o `lib/` que el servidor toque tiene estado mutable. Lo hacen cumplir dos pruebas PHP estáticas, sin herramienta nueva: `tests/Unit/LandingModuleStateTest.php` (sin `let` ni `new Date()` de módulo en `components/landing/`) y `tests/Unit/LandingServerSafetyTest.php` (E-43).

--------------------------------------------------------------------------------

## 6. Metadatos: fuente única de verdad

### 6.1 Decisión

Las etiquetas del `<head>` salen del `<Head>` de Inertia con SSR (la preferencia del handoff). La fuente de datos es **una sola**: la propiedad `seo` que arma PHP.

*   `lang/es/seo.php` (nuevo) guarda los textos por nombre de ruta: título, descripción, texto alternativo de la imagen.
*   `App\Http\PageSeo::for(string $routeName): array` (nuevo, kernel compartido) devuelve un arreglo plano y serializable: `title`, `description`, `canonical` (URL absoluta armada con `config('app.url')` sin barra final más el path, sin query), `image` (`{url, width: 1200, height: 630, alt}`), `locale` (`es_MX`), `siteName`, `jsonLd` (cadena JSON con `Organization` y `WebSite`).
*   La ruta `home` y la ruta `privacy-notice` pasan `seo` como propiedad de la página. El archivo `routes/web.php` es el único sitio que llama a `PageSeo::for`.
*   `resources/js/components/landing/seo-head.tsx` (nuevo) lee `seo` y escribe un `<Head>` con `<title>`, `<meta>`, `<link rel="canonical">` y `<script type="application/ld+json">`; cada elemento lleva `head-key` (que Inertia convierte en `data-inertia`). No contiene un solo texto.
*   `resources/views/partials/seo-fallback.blade.php` (nuevo) escribe **las mismas etiquetas, con los mismos `data-inertia`**, leyendo `$page['props']['seo']`. Va **dentro del slot** de `<x-inertia::head>`.

### 6.2 Por qué hay dos renderizadores y una sola fuente (excepción justificada)

El handoff pide no mantener una versión de los metadatos en Blade y otra en React, y admite una excepción si es indispensable y hay una sola fuente de datos. Aquí es indispensable por la tensión del handoff 8.5: si Node falla, `HttpGateway` devuelve `null` (H5) y el documento sale sin las etiquetas del `<Head>`; WhatsApp y LinkedIn guardan la vista previa por URL y no la rehacen de inmediato. El slot es el mecanismo oficial para ese caso. Para que no sea una segunda fuente: ambos renderizadores leen el mismo arreglo (`seo`, que viaja en el objeto de página) y una prueba compara el conjunto de etiquetas que produce cada uno (sección 13, prueba `SeoParityTest`): si alguien añade una etiqueta en uno y no en el otro, falla.

### 6.3 Sin duplicados después de hidratar

*   Con SSR correcto: Node devuelve `head` con `data-inertia`; el slot no se imprime (H9).
*   Sin SSR (fallo, o sesión iniciada): se imprime el slot con `data-inertia="<llave>"`; al arrancar, el cliente genera el mismo conjunto con las mismas llaves y reemplaza o conserva cada elemento (`core/dist/index.js:4195-4228`), y elimina el `<title>` que no lleva `data-inertia` si el cliente genera uno propio (`:4199`). El `<title>` del slot también lleva `data-inertia=""` para que se compare igual.
*   Caso "visitante con JavaScript, Node caído": la prueba de navegador cuenta `document.querySelectorAll('title').length === 1`, `meta[name=description]` uno solo, `link[rel=canonical]` uno solo, y JSON-LD uno solo.
*   JSON-LD es un bloque de datos (`type="application/ld+json"`), no un script ejecutable: la CSP no lo evalúa. Es el comportamiento de la especificación, **sin verificar** en el navegador de la suite: lo detecta `cspViolations()` en la prueba de navegador.

### 6.4 Etiquetas requeridas

| Etiqueta | Origen | Nota |
| :-- | :-- | :-- |
| `<title>` | `seo.title` + " - " + `seo.siteName` | Una sola; el sufijo lo pone `titleCallback` de `app.tsx:22`, igual que hoy. |
| `description` | `seo.description` | ≤ 160 caracteres; debe conservar el nombre de las nueve herramientas (lo exige `tests/Browser/LandingTest.php:162-168`, número de línea de v1, **por reconfirmar en la implementación**). |
| `canonical`, `og:url` | `seo.canonical` | De `config('app.url')`, sin query: una visita `/?utm_source=x` declara `/`. |
| `og:type`, `og:site_name`, `og:title`, `og:description`, `og:locale` | `seo` | `es_MX`. |
| `og:image`, `og:image:width`, `og:image:height`, `og:image:alt` | `seo.image` | `/og-v1.png` de 1200×630, con versión en el nombre. |
| `twitter:card` (`summary_large_image`), `twitter:title`, `twitter:description`, `twitter:image` | `seo` | |
| JSON-LD | `seo.jsonLd` | `Organization` (logo `public/app-icon-512.png`, comprobado que existe) y `WebSite` (`inLanguage: es-MX`). Sin `SoftwareApplication`, precio ni valoraciones (el pie dice que Humetría no certifica cumplimiento). |
| `<html lang>` | sin cambio | `es`; dos pruebas existentes lo afirman (`tests/Feature/LocalizationTest.php:87`, `tests/Feature/Tenancy/AcceptInvitationTest.php:293`, citadas del plan v1; **por reconfirmar**). |

### 6.5 Validación real

`curl`, un parser HTML sin JavaScript, Playwright con y sin JavaScript, y un extractor independiente: se detallan en la sección 13 (F). Las herramientas de LinkedIn y Meta se usan a mano después del despliegue. Que Google indexe la página no se infiere de ninguna prueba local.

### 6.6 Aviso de privacidad

Misma mecánica (`PageSeo::for('privacy-notice')`). Esta página sí pasa por SSR, porque el handoff la autoriza; su cuerpo es texto fijo y se lee sin JavaScript (corrige el límite que v1 aceptaba para el aviso).

--------------------------------------------------------------------------------

## 7. Infraestructura: Docker, Compose, Dokploy

Contexto mínimo: Dokploy despliega `compose.production.yaml` como un servicio Docker Compose; Traefik (de Dokploy) recibe el tráfico por la red externa `dokploy-network` y solo enruta lo que se declara en la pestaña Domains para el servicio `web`; dentro de la imagen, nginx sirve los archivos de `public/` y pasa lo demás a php-fpm (`Dockerfile:5`, `compose.production.yaml:1-12`). La imagen la construye GitHub Actions y la publica en GHCR con las etiquetas `latest` y `sha-<commit>` (`deploy.yml:24-45`); Dokploy descarga `latest` por webhook.

### 7.1 Dónde corre Node (handoff 8.1)

| Criterio | S-A. Proceso dentro del contenedor `web`, bajo un supervisor | S-B. Servicio `ssr` aparte, privado, con su propia imagen |
| :-- | :-- | :-- |
| Complejidad operativa | Una pieza menos en el compose, pero hay que añadir Node (~120 MB) a la imagen de PHP y un servicio al supervisor de la imagen base (`serversideup/php` usa s6-overlay; que acepte servicios propios en la versión fijada es **sin verificar**) | Un servicio nuevo en el compose y una segunda imagen, pero cada una hace una sola cosa |
| Aislamiento | Node comparte usuario `www-data`, `.env` (APP_KEY, base de datos, cuentas de correo) y sistema de archivos con PHP: un fallo del renderizador está en el mismo dominio de confianza que los secretos | Node corre sin `.env`, sin acceso a la base ni a internet, en su propio contenedor |
| Seguridad | `/shutdown` sin autenticación queda en `localhost` del mismo contenedor, accesible a cualquier código PHP | `/shutdown` queda accesible solo para `web`, en una red interna (sección 7.2) |
| Memoria y CPU | Compite con php-fpm sin límite propio; un renderizador desbocado ahoga el sitio | Límite de memoria y CPU propio; si cae, `web` sigue |
| Reinicio | El supervisor reinicia el proceso | `restart: unless-stopped` + vigilante (sección 7.5) |
| Disponibilidad | Si el contenedor `web` se reinicia, Node también | `web` y `ssr` se reinician por separado; la degradación es segura |
| Consistencia de versiones | Garantizada: mismo sistema de archivos | Garantizada por construcción **y** verificada: mismo commit, mismo `docker build`, etiquetas emparejadas, meta de revisión (sección 7.3) |
| Escalabilidad | Escala con `web` | `ssr` se escala o se mueve a otro nodo sin tocar `web` |
| Diagnóstico | Logs mezclados en el mismo contenedor | Logs separados en la pestaña Logs de Dokploy por servicio |
| Compatibilidad con Dokploy | Igual | Un servicio más del compose; **sin verificar** que Dokploy respete `internal: true` en la red y no la renombre al aislar el despliegue (E3) |

**Recomendación: S-B.** El costo es una segunda imagen y un servicio; el beneficio es que el proceso que renderiza JavaScript de terceros nunca ve un secreto. No se introduce ningún servicio externo administrado.

### 7.2 Seguridad del servicio `ssr`

*   **Red.** Una red `ssr` con `internal: true`, compartida solo por `web` y `ssr`. El servicio `ssr` no está en `default` ni en `dokploy-network`, no declara `ports` ni etiquetas de Traefik, y no se le asigna un dominio en Dokploy. Como `/shutdown` no tiene autenticación (H3), solo `web` puede alcanzarlo; el riesgo que queda (código PHP comprometido apaga Node) degrada a CSR.
*   **Escucha.** El servidor escucha en `0.0.0.0` dentro de su red; el plugin permite `inertia({ ssr: { host } })` (`@inertiajs/vite/dist/index.js:729`). No se necesita: la red ya lo limita, y fijar una IP no es portable.
*   **Usuario y archivos.** `USER node` (uid 1000, de la imagen oficial), `read_only: true`, `tmpfs: /tmp`, `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]`, sin volúmenes.
*   **Entorno.** Sin `env_file`. Solo `NODE_ENV=production` y `NODE_OPTIONS=--max-old-space-size=256`.
*   **Recursos.** `mem_limit: 384m`, `cpus: 1.0`, `pids_limit: 100`. Estos números son un límite: la memoria medida es de 98 a 115 MB (sección 17), así que quedan holgados; E4 repite la medición con la imagen real.
*   **Señales.** `init: true` (tini): Node como PID 1 ignora SIGTERM sin manejador.

### 7.3 Construcción: un commit, dos imágenes, un par de etiquetas

*   **Dockerfile** (entradas E-46 y E-47):
    *   La etapa `assets` (`Dockerfile:33-39`) recibe `ARG GIT_SHA=unknown` y `ENV VITE_BUILD_REVISION=$GIT_SHA`, y corre `npm run build:ssr` (script que ya existe, `package.json:6`: `vp build && vp build --ssr`) en lugar de `npm run build` (`:39`). El cliente y el bundle SSR salen de la misma ejecución.
    *   Una etapa nueva `ssr` se define **antes** de `app`: la etapa final sigue siendo `app` (`:42`) y `docker build .` sin `target` sigue produciendo la imagen web. Parte de `${NODE_IMAGE}` (la misma `node:24-trixie-slim`, `:8`), `WORKDIR /srv/ssr`, copia **solo** `bootstrap/ssr` desde `assets` y `scripts/ssr`, crea un `package.json` con `{"type":"module"}` (el bundle `app.js` es ESM y la imagen no trae `package.json`), usa `USER node` y arranca `node scripts/ssr/supervisor.mjs`. No copia `.env`, `vendor` ni `app/`.
    *   `app` agrega `ENV APP_REVISION=${GIT_SHA}` junto al `ARG GIT_SHA` ya existente (`:54`).
    *   `.dockerignore` agrega `bootstrap/ssr`. Ya excluye `tests` y `docs`: por eso el fixture y los scripts de la imagen viven en `scripts/ssr/`.
*   **Revisión.** `SeoHead` escribe `<meta name="build-revision">` desde `import.meta.env['VITE_BUILD_REVISION']` (viene del bundle SSR y del cliente) y Blade escribe `<meta name="app-revision">` desde `config('app.revision')`, que lee `APP_REVISION` (E-25; `.env.example` lleva la llave porque `EnvExampleTest` lo exige). La comprobación sintética exige que las dos metas existan, sean iguales y coincidan con el commit que se acaba de desplegar. Antes, `deploy.yml:37-45` no pasaba `build-args` y `GIT_SHA` valía `unknown` (`Dockerfile:54`): la comparación habría sido `unknown` contra `unknown`; E-51 agrega `build-args: GIT_SHA=…` a los dos objetivos. La revisión es un hash de commit de un repositorio ya visible en GHCR, no un secreto. **Sin verificar:** que `vite-plus` exponga `VITE_*` desde un `ENV` de la etapa (se confirma en la primera construcción de la pieza 6; la alternativa es pasarlo como variable de entorno del comando `npm run build:ssr`).
*   **Publicación.** `deploy.yml` construye `target: app` y `target: ssr` con el mismo `sha`, cada uno con su caché (`scope=app`, `scope=ssr`) para que no se pisen, y publica `humetria:sha-<sha>`/`latest` y `humetria-ssr:sha-<sha>`/`latest`. Si cualquiera falla, el workflow falla antes del webhook.
*   **Compose.** El ancla `x-app` (`compose.production.yaml:20-31`) la comparten `web`, `horizon` y `scheduler`; la etiqueta nueva se pone **en el ancla**: `'${HUMETRIA_IMAGE:-ghcr.io/vicbravodev/humetria}:${HUMETRIA_TAG:-latest}'`, así los tres siguen juntos al mismo `HUMETRIA_TAG` también tras un rollback. `ssr` usa `'${HUMETRIA_SSR_IMAGE:-ghcr.io/vicbravodev/humetria-ssr}:${HUMETRIA_TAG:-latest}'`. `HUMETRIA_IMAGE` pasa de referencia completa a repositorio sin etiqueta; hoy solo aparece comentado (`.env.production.example:65`), que se actualiza.
*   **Ventana entre versiones.** Al recrear los servicios, un `web` nuevo con un `ssr` viejo (o al revés) conviven unos segundos. Efecto: el HTML del bundle viejo se hidrata con JavaScript nuevo, React avisa del desajuste y vuelve a renderizar en el cliente (la página funciona). La comprobación sintética lo ve por la diferencia de revisión. No se evita con `depends_on` (7.6).
*   **Reinicio y rollback.** Reiniciar `ssr` carga el bundle de su imagen; no hay estado. Rollback: `HUMETRIA_TAG=sha-<commit anterior>` en la pestaña Environment de Dokploy y redeploy (las imágenes vuelven juntas). Interruptor inmediato: `INERTIA_SSR_ENABLED=false` en `web` y reinicio de `web` (la configuración se cachea al arrancar por `AUTORUN`); la landing sigue en CSR con las etiquetas del slot.

### 7.4 Salud (handoff 8.4)

| Qué se comprueba | Cómo | Dónde corre | Detecta |
| :-- | :-- | :-- | :-- |
| Proceso activo | el contenedor existe y no sale | Docker (`restart: unless-stopped`) | caída total |
| Puerto accesible en la red interna | `GET /health` de Node (la misma que usa `php artisan inertia:check-ssr`: `HttpGateway.php:187-198`, `Commands/CheckSsr.php`, comprobado) | a mano desde `web` o en la verificación manual (12.10) | red mal configurada |
| Bundle existente y ejecutable | el contenedor no arranca sin él; `node bootstrap/ssr/app.js` falla al importar | Docker y `supervisor.test.mjs` | imagen mala |
| **Capacidad real de renderizar** | `scripts/ssr/healthcheck.mjs` envía `POST /render` con `scripts/ssr/fixtures/landing-page.json` (el mismo objeto que usa `render.test.mjs`, E-39) y afirma que el cuerpo contiene el `<h1>` y los nueve nombres; sale 0 o 1 | `healthcheck:` del compose, cada 30 s, `timeout` 5 s, 3 reintentos, `start_period` 20 s | bundle roto, componente que lanza, bucle |
| Respuesta dentro del plazo | el mismo script, con un máximo de 2 s (medido: 4 a 5 ms por render) | igual | proceso lento |

`inertia:check-ssr` solo sondea `/health`: un puerto abierto no prueba que renderice. Por eso es la comprobación del lado `web`, no la salud del servicio. El fixture se copia a la imagen porque vive en `scripts/ssr/`; una prueba del banco (`page-keys.test.mjs`, E-55) afirma que sus llaves coinciden con `tests/Ssr/fixtures/page-keys.json`, para que no envejezca.

### 7.5 Vigilante (reinicio automático real)

Docker no reinicia un contenedor "unhealthy" (N-2). La imagen `ssr` arranca `scripts/ssr/supervisor.mjs`, de Node y sin dependencias: lanza `node bootstrap/ssr/app.js` como proceso hijo, repite el mismo `POST /render` del healthcheck cada 30 segundos con un plazo de 2 s y, tras tres fallos o plazos vencidos seguidos, termina con código 1; `restart: unless-stopped` lo relanza (en el peor caso, a unos 100 s de un cuelgue). También sale si el hijo sale. Los intervalos y el número de fallos se pueden bajar por variables de entorno para las pruebas. Es el único código de Node nuevo; lo prueba `tests/Ssr/node/supervisor.test.mjs` (bundle bueno, uno que lanza al importar y uno cuyo `/render` no responde: E-49). Que el vigilante, en otro proceso, alcance su plazo mientras el hijo está en un bucle síncrono es cierto por construcción (procesos distintos) y se confirma en E4 con la imagen real. La alternativa, una imagen de reinicio por salud de terceros (`autoheal`), es una dependencia nueva que monta el socket de Docker: no se recomienda.

### 7.6 Política de degradación (handoff 8.5)

Lo que ocurre si Node se detiene, tarda o devuelve basura: `HttpGateway` devuelve `null` y Laravel imprime el documento sin cuerpo renderizado (H5). Eso es lo oficial y es inaceptable **sin señal**. Opciones:

| Opción | Personas | SEO y vistas previas | Recuperación | Privacidad | Veredicto |
| :-- | :-- | :-- | :-- | :-- | :-- |
| **D1. Documento CSR 200 con etiquetas del slot (recomendada)** | La página funciona igual (React la pinta) | Rastreadores que ejecutan JavaScript ven todo; los que no, ven las etiquetas del `<head>` pero no el texto; las vistas previas siguen correctas | Inmediata al volver Node; sin acción manual | Nada sale | Recomendada, con las señales de abajo |
| D2. Responder 503 con `Retry-After` | La landing no existe mientras Node falle: una caída de Node tira todo el sitio comercial | Google trata 503 como temporal y no desindexa si dura poco; las redes no guardan la vista previa | Depende de reiniciar Node | Nada sale | Descartada: convierte un componente auxiliar en punto único de falla |
| D3. 503 solo para rastreadores (por `User-Agent`) | Normal | Los rastreadores reciben un error | Inmediata | — | Descartada: detectar rastreadores por `User-Agent` y servirles otra cosa es la práctica que los buscadores sancionan (cloaking), y falla con los extractores nuevos |
| D4. Servir la última respuesta SSR buena desde caché durante N minutos | Normal | Mantiene el texto | Inmediata | La clave debe ser el hash del objeto de página; solo visitantes anónimos (garantizado por la política), pero exige probar que no hay datos por visitante | Posible mejora (P2): reduce también la carga de Node. No ahora. |

**Señales obligatorias de que SSR falló (D1):**

1.  Un listener de `Inertia\Ssr\SsrRenderFailed` escribe `ssr.render_failed` con `type` (el enum `SsrErrorType`: `browser-api`, `component-resolution`, `render`, `connection`, `unknown`), `component`, `path` (sin query) y `hint`; **no** escribe `page`, `stack` ni `error` completo (puede citar propiedades). Como mucho una línea por minuto por tipo: `Cache::add("ssr:failed:{type}", 1, 60)`; el resto incrementa un contador (`Cache::increment`).
2.  Un middleware de dos líneas pone la cabecera HTTP `X-Rendered-By: ssr` cuando `SsrState::response` no es `null` y `X-Rendered-By: csr` cuando la petición era elegible y no hubo respuesta. Solo en las rutas de `SsrPages`.
3.  La comprobación sintética (sección 13, F) exige `X-Rendered-By: ssr`, el `<h1>` y las nueve herramientas en el HTML; si falla, el workflow falla y GitHub manda un correo (el patrón que ya usa la mutación semanal). Corre después de cada despliegue y cada 15 minutos.
4.  `web` no declara `depends_on: ssr`: un Node roto no impide arrancar el sitio.
5.  **Cortacircuitos.** Tras un fallo de tipo `connection` (Node caído o colgado) `SsrPages::allows()` devuelve falso durante 30 segundos (`Cache::put('ssr:circuit-open', true, 30)`), de modo que un Node colgado no cuesta el plazo de 2 s a cada visita del worker de PHP; pasado el plazo vuelve a intentar una petición.

Prueba que lo demuestra (sección 13, E): SSR detenido, lento (el servidor de prueba responde tarde), bundle ausente, bundle roto y reinicio: en todos, `GET /` responde 200 con las etiquetas del slot, `X-Rendered-By: csr` y exactamente una línea `ssr.render_failed` por tipo y minuto; sin ninguna línea con contenido de página.

### 7.7 Variables y configuración

*   `config/inertia.php` (E-15): `ssr.enabled` ← `INERTIA_SSR_ENABLED` (defecto `false`); `ssr.url` ← `INERTIA_SSR_URL` (`http://humetria-ssr:13714` en producción); `ssr.timeout` ← `INERTIA_SSR_TIMEOUT` (2); **`ssr.ensure_bundle_exists` ← `INERTIA_SSR_ENSURE_BUNDLE`**, con `false` en `.env.production.example`. Sin esto, el gateway devuelve `null` siempre en producción: el contenedor `web` no contiene `bootstrap/ssr` (`HttpGateway.php:52`; la documentación de Inertia v3 lo prevé para contenedores). `ssr.throw_on_error` se queda en `false` (`HttpGateway.php:167`). `SsrPages` lee `config()`, no `env()`.
*   `.env.example` y `.env.production.example` ganan las llaves (`tests/Unit/EnvExampleTest.php` lo exige para `.env.example`).
*   `phpunit.xml`: `INERTIA_SSR_ENABLED=false` forzado; la suite `Ssr` lo enciende por variables de entorno de `scripts/ssr/test.sh` y con `config([...])` en la prueba.
*   Los registros de Node (`formatErrors`) incluyen la URL y la pila en el stderr de su contenedor; el contenedor `ssr` solo recibe las dos páginas autorizadas y Dokploy los muestra en su propia pestaña de logs. Se acepta.
*   **Cortacircuitos.** Ver 7.6 (punto 5). Las llaves de `Cache` que usa son `ssr:circuit-open`, `ssr:failed:{type}` y `ssr:failed:count`.

### 7.8 Redirección de `www` y `APP_URL` (conservado de v1)

`www.humetria.com.mx` redirige con 301 a `humetria.com.mx` conservando ruta y query, en Traefik (alternativa O de v1). Si la pestaña Domains de Dokploy no ofrece la redirección para un servicio Compose (**sin verificar**), se hace con etiquetas de Traefik en `compose.production.yaml`. Precondición: `APP_URL` en Dokploy debe ser `https://humetria.com.mx` (el identificador de las llaves de acceso sale de su host: `config/fortify.php:146-148`); lo confirma el dueño (Q12).

--------------------------------------------------------------------------------

## 8. Seguridad: privacidad, datos compartidos, tokens e indexabilidad

### 8.1 Lo que no cambia

El anonimato de las respuestas no se toca: la procedencia vive en `demo_requests`, dato de la plataforma. Ninguna página con token pasa por SSR (sección 4). Las respuestas de encuestas no se escriben en logs (`ssr.render_failed` no lleva props).

### 8.2 Aislamiento de datos, estado compartido y bundle

Auditoría de datos (handoff 4.3):

#### 8.2.1 Qué recibe Node

Para un visitante anónimo en `/`, el objeto de página contiene hoy (comprobado en `HandleInertiaRequests.php:43-67`): `errors`, `name`, `support.email`, `auth` con `user: null`, `isSuperadmin: false`, `isStaff: false`, `impersonator: null`, `tenant: null`, `tenants: []`, `inCourse` y `firstSteps` (cierres que devuelven `null` sin empresa), `sidebarOpen` (cookie `sidebar_state`), más las props de la página (`seo`). Ninguno es un dato sensible para un anónimo, pero el contrato debe fijarse, porque una prop compartida futura llegaría a Node sin que nadie lo note.

**Contrato.** `tests/Feature/SsrPropsTest.php` envía `GET /` y `GET /privacy-notice` con SSR encendido y `Http::fake()`, captura el cuerpo del `POST /render` y afirma: el conjunto **exacto** de llaves de primer nivel de `props`; que `auth.user` es `null`; que no aparece ninguna cookie ni cabecera (el cliente HTTP de Laravel no las reenvía; la prueba lo afirma sobre la petición capturada); que `url` es el path y la query de la petición. Añadir una prop compartida rompe la prueba y obliga a decidir.

**Con sesión.** El closure devuelve SSR apagado: nadie con sesión, ni el superadmin que suplanta a una empresa, manda su usuario a Node. La landing se renderiza en el navegador para ellos, como hoy.

#### 8.2.2 Estado compartido entre solicitudes

El proceso Node es de larga duración. Estado de módulo mutable encontrado (comprobado):

*   `use-appearance.tsx:13-14`: `listeners` y `currentAppearance` son variables de módulo. En el servidor solo se leen (`updateAppearance` solo se llama por una acción del usuario) y el snapshot de servidor es constante (`'system'`). Se afirma con una prueba de contaminación (job `ssr` de la pieza 5).
*   `landing-footer.tsx:8`: constante de módulo (el año). Se corrige (sección 5.7).
*   Lo demás de la landing no declara estado de módulo (`grep` de `let`/`new Date`/`Math.random` en `components/landing/` solo encontró el año). `useId` de React es determinista entre servidor y cliente.

**Prueba de contaminación.** Un script de Node (en `tests/Ssr/`) levanta el bundle y envía en paralelo 50 peticiones `POST /render` con marcas distintas en `props.errors` y `url`; cada respuesta debe contener solo su marca. Se ejecuta en el job `ssr` de CI (sección 13).

#### 8.2.3 Qué importa el bundle

Ver sección 3.2: el bundle contiene páginas privadas como trozos perezosos; la imagen que lo ejecuta no tiene `.env`, ni base de datos, ni salida a internet. El **módulo de arranque** no ejecuta ningún trozo de página privada; el arranque medido en la sección 17 no falló al importar ningún módulo.


### 8.3 Indexabilidad (handoff 9)

Se conserva la arquitectura de v1 y se comprueba su interacción con SSR.

*   `App\Http\IndexablePages::ROUTES` (`home`, `privacy-notice`). `SecurityHeaders::handle()` agrega `X-Robots-Tag: noindex, nofollow` salvo que se cumplan las tres condiciones: ruta en la lista, respuesta 200 y entorno de producción. El middleware es global, corre primero y respeta una cabecera ya puesta (`SecurityHeaders.php:68-72`, comprobado). SSR no interviene: el middleware solo mira la ruta y el estado.
*   Errores: un 404 (ruta inexistente) y un 500 (también en `/`) llevan `noindex`. Una página de error servida en `/` tiene ruta resuelta `home` pero estado ≠ 200: lleva `noindex`.
*   Rutas con token: nunca están en la lista, así que llevan `noindex` aunque el enlace sea válido, vencido o inexistente. `noindex` no sustituye la autenticación: las rutas con token siguen protegidas por el propio token.
*   `public/sitemap.xml` estático con las dos URLs canónicas; `public/robots.txt` conserva `Disallow:` vacío y agrega `Sitemap:`. `robots.txt` no bloquea ninguna ruta, para que un buscador pueda leer los `noindex`. Una prueba compara el sitemap con la lista.
*   Archivos estáticos que sirve nginx (`/build/*`, `/video/*`, `/storage/*`, `robots.txt`, sitemap): no pasan por Laravel y no llevan la cabecera. Se **auditan por separado** (handoff 9): se revisa con `curl -I` una vez por despliegue que no hay datos privados en ellos (los logos de `/storage/*` son públicos por diseño) y se documenta como límite aceptado. Poner la cabecera en nginx exigiría editar la imagen base de `serversideup/php`: no se propone aquí.
*   Una ruta nueva no activa SSR ni indexación por omisión: no está en ninguna de las dos listas, y dos pruebas lo exigen (sección 13, D).

--------------------------------------------------------------------------------

## 9. Analytics: medición, navegación, consentimiento y conversiones

### 9.1 Instrumentación

*   GA4 con `gtag.js` directo, sin Google Tag Manager (alternativa J de v1, conservada).
*   **Una sola función decide.** `App\Http\Analytics::measurementId(Request): ?string` devuelve el ID solo si: la ruta es `home`; el ID configurado (`services.google_analytics.measurement_id`, de `GOOGLE_ANALYTICS_ID`) tiene la forma `G-` + mayúsculas o dígitos (una cadena vacía es "sin ID"); nadie tiene sesión; y el consentimiento lo permite (sección 9.3). La llaman dos sitios, `app.blade.php` y `SecurityHeaders`, que añade los orígenes de Google a la CSP en esas condiciones y solo en ellas. Una prueba exige que script y CSP coincidan en cada caso.
*   **SSR no ejecuta Analytics.** El script se escribe en Blade con nonce, fuera de React: Node no lo ve. El bundle SSR no importa `gtag`. `seo` no lleva identificadores de visitante: la prueba de contrato `SsrPropsTest` lo afirma.
*   **Navegación.** Salir de la landing recarga el documento: todos los enlaces que salen de la landing hacia otras rutas (los dos "Iniciar sesión", `landing-header.tsx:57` y `landing-footer.tsx:45`, que hoy son `<Link href={dashboard()}>`, y los dos del aviso de privacidad) son `<a href>` normales. Así el script y la CSP ampliada no viven en el login ni en `/admin`. Para que una visita a la landing desde otra ruta se mida, **todo enlace cuyo destino es `home` también es un `<a href>` normal** (si fuera un `<Link>`, Blade no volvería a renderizar y la visita no cargaría `gtag`). `tests/Unit/PlainLinksTest.php` (E-64, PHP leyendo `resources/js`) rechaza `<Link` con `href={home(` o `href="/"` fuera del logo de `landing-header.tsx:34-35` y con `href={dashboard` en la landing. Hoy hay seis infractores (`landing-header.tsx:57`, `landing-footer.tsx:45`, `components/tenant-switcher.tsx:66`, `components/error-page-action.tsx:23`, `layouts/auth/auth-split-layout.tsx:15`, `pages/error.tsx:47`; el logo `landing-header.tsx:35` es la excepción).
*   **Sin medición duplicada.** `gtag('config', id)` envía un `page_view` por carga de documento; no hay navegación Inertia dentro de la landing que genere otro. La hidratación no repite el script (está en el `<head>`, no en React).
*   **Cookie `_ga`.** Se guarda para todo el dominio; el navegador la envía a `/c/*` (buzón de quejas) y `/workers/*` (páginas que no guardan sesión ni cookies: `resources/js/pages/complaints-public/form.tsx:6`, `resources/js/components/disclosure/public-complaint-card.tsx:5`, citadas de v1, **por reconfirmar**). La aplicación no la lee. Una prueba agrega una cabecera `Cookie: _ga=…` en crudo a una petición a `/c/*` y afirma que el valor no aparece en ninguna línea de log (v1 la planteaba con una cookie del framework, que Laravel descarta antes de llegar al código: la prueba no probaría nada).

### 9.2 Privacidad: qué se puede enviar a terceros

| Dato | ¿Se envía a Google? | Regla |
| :-- | :-- | :-- |
| `page_location` | Sí, pero solo de la landing | Sin query personal: se pasa `page_location` ya normalizada (origen + path + solo los parámetros `utm_*` y los de clic de anuncios, ver sección 10). |
| `page_referrer` | Solo si es externo; si el `Referer` es del propio dominio se pasa únicamente el origen (`https://humetria.com.mx/`) | `gtag` lee `document.referrer` por su cuenta: si pasa la cadena vacía o la omite no está comprobado (E5). Se pasa el origen, nunca una cadena vacía. |
| Cookies `_ga`, `_ga_<ID>` | Sí, solo con consentimiento (9.3) | Declaradas en el aviso. |
| Parámetros UTM | Sí | Son de campañas propias. |
| IDs de clic (`gclid`, `gbraid`, `wbraid`) | Google los lee solo si el etiquetado automático de Ads está activo | Ver sección 10. |
| Nombres, correos, teléfonos, empresa, mensaje de una solicitud | **Nunca** | Una prueba revisa que `generate_lead` no lleva parámetros de usuario. |
| URLs de `/admin`, de encuestas, de portales, de reportes o con token | **Nunca** | Esas páginas no cargan Analytics. |
| Señales de Google (Signals), personalización de anuncios | No | `allow_google_signals` y `allow_ad_personalization_signals` en `false` (parámetros de `gtag('config')`). Si se cambian al conectar Google Ads, se decide con el dueño (Q5). |

**Un mismo dominio para marketing, encuestas y buzones: ¿riesgo de correlación?** Sí, pequeño pero real: la cookie `_ga` del dominio acompaña a las peticiones del buzón de quejas y de las páginas de trabajadores, y es un identificador estable que un tercero (Google) no ve en esas páginas pero que **sí** viaja a nuestro proxy. Alternativas: (a) **el mismo dominio con las salvaguardas actuales** (sin Analytics en esas páginas; la app no lee la cookie; logs del proxy sin la cabecera `Cookie`; aviso que lo declara); (b) restringir la cookie por path (`cookie_path=/`; no sirve, la landing está en `/`); (c) mover marketing a otro host (`www` o `humetria.com.mx` para marketing y `app.humetria.com.mx` para la plataforma), con costos de DNS, certificado, `APP_URL`, llaves de acceso (el identificador de WebAuthn depende del host) y enlaces ya enviados por correo; (d) analítica sin cookies (alternativa K de v1, rechazada porque no distingue visitantes que regresan). **Recomendación:** (a) para el lanzamiento, y (c) como mejora de P2 si el dueño decide que el riesgo de correlación importa más que el costo de migrar; no se impone una migración automática. Q8.

### 9.3 Consentimiento (decidido: `denied` por omisión)

*   **Hechos.** `services.google_analytics.consent_default` (`GOOGLE_ANALYTICS_CONSENT_DEFAULT`, `granted` o `denied`) vale **`denied`** por omisión y así se queda hasta que el abogado del dueño indique otra cosa (decisión Q5, sección 18). `App\Http\Consent::analyticsAllowed(Request)` devuelve verdadero solo con `granted`. `Analytics::measurementId()` y `RememberDemoAttribution` la consultan. Con `denied` **no se carga el script, no se abren orígenes en la CSP y no se escribe la cookie de atribución**; el dueño fue avisado de que no hay medición mientras tanto.
*   **Qué no se hace ahora.** No hay banner ni cookie de decisión del visitante: añadirlos es P2 (diseño, textos, accesibilidad y pruebas). `Consent` es el único sitio que habrá que ampliar.
*   **Consent Mode.** Con `granted`, el bloque de configuración emite `gtag('consent','default', …)` antes de `gtag('config', …)`. Su contrato exacto (`ad_user_data`, `ad_personalization`, `wait_for_update`) es **sin verificar** contra la documentación de Google y se confirma en E5 antes de poner `granted`.
*   **Verificación legal previa.** La interpretación de la ley mexicana aplicable (LFPDPPP y su reforma de 2025, y quién la hace cumplir) no se verificó. Es condición de despliegue: `GOOGLE_ANALYTICS_ID` vacío y `denied` hasta entonces.

### 9.4 Conversión `generate_lead`

*   **Cuándo se emite.** Solo cuando el servidor confirma que la solicitud quedó guardada. Hoy `DemoRequestController::store` responde igual con éxito y con el honeypot (`DemoRequestController.php:15-26`), así que la señal sale del servidor: `SubmitDemoRequest::handle()` devuelve el `DemoRequest` guardado y el controlador hace `Inertia::flash('lead', ['id' => $demoRequest->id])` **solo** si no es el honeypot (E-62). El `id` es un UUID v4 (`HasVersion4Uuids`, `DemoRequest.php:15`). El cliente lo lee en `onSuccess` y llama a `trackLead(window, sessionStorage, page.flash.lead?.id)` (E-63).
*   **Reintentos y respuestas tardías.** `trackLead` no hace nada sin `gtag`, sin `id` o si el `id` ya está en `sessionStorage`; así una recarga o un "atrás" no repite el evento. Si el servidor rechaza por validación o limitación no hay flash y no hay evento.
*   **Doble clic.** El botón se deshabilita mientras envía (`processing` del `<Form>`, se afirma en la prueba de navegador). Que dos peticiones HTTP distintas creen dos solicitudes es independiente de Analytics (`throttle:demo-requests`, `routes/demos.php:11`); una llave de idempotencia se decidió fuera de este trabajo (Q9, opción recomendada: no).
*   **No se asume deduplicación automática en GA4.** Enviar un identificador no la garantiza; el diseño no depende de ella. Lo que GA4 deduplica por `transaction_id` es, según la documentación general de Google, el evento `purchase`; para `generate_lead` **no se verificó** (E5).
*   **Pérdida de eventos.** Bloqueadores y fallos de red pierden eventos; el origen de verdad es la tabla `demo_requests`. La medición de servidor (Measurement Protocol) es P2.
*   **Navegación después del envío.** El éxito deja la persona en la misma página y el evento sale antes de cualquier salida.
*   **Validación.** DebugView con un ID real (E5); marcar `generate_lead` como evento clave y, solo cuando haya medición, importarlo a Google Ads (Q7: opción recomendada, esperar a medir la calidad del lead).
*   **Parámetros.** `generate_lead` sin datos personales. Hoy, con `consent_default=denied`, `gtag` no existe y `trackLead` no hace nada.

--------------------------------------------------------------------------------

## 10. Atribución para campañas y demos

### 10.1 Por qué cambia

El plan v1 guardaba el primer toque en la sesión de Laravel, que vence tras 120 minutos sin actividad (`config/session.php:37`). Quien llega por un anuncio el lunes y pide la demo el jueves queda sin procedencia, y el ciclo de compra B2B suele ser de semanas. La procedencia debe persistir más, sin convertir "no sé" en "directa".

### 10.2 Qué se distingue

| Concepto | Definición | Cómo se guarda |
| :-- | :-- | :-- |
| **Primer canal conocido** | Primera visita en el periodo de 90 días con alguna señal: `utm_*`, ID de clic de anuncios, o referente externo | `first` en la cookie; se escribe una vez |
| **Último canal conocido (no directo)** | La visita más reciente con señal | `last` en la cookie; se sobrescribe solo cuando hay señal |
| **Campaña asociada a la conversión** | Los `utm_*` de `last` si existen, si no los de `first` | Se calcula al guardar la solicitud y se escribe en la fila |
| **Visita directa** | Una visita sin `utm_*`, sin referente y con cookie existente | No se escribe: es la ausencia de señal |
| **Visita recurrente** | La cookie ya existía | Se infiere de la cookie; se guarda `first_seen_at` |
| **Conversión sin atribución** | Solicitud sin cookie y sin sesión con señal | La fila queda con `null` y la consola dice "Sin dato" |
| **Sin procedencia ≠ directa** | Muchos navegadores y apps de mensajería no envían `Referer`: "sin referente" no significa "directa" | Nunca se escribe `direct` por omisión; solo `null` |

### 10.3 Mecanismos de persistencia (comparación)

| Mecanismo | Duración | Privacidad y retención | Costo | Veredicto |
| :-- | :-- | :-- | :-- | :-- |
| Sesión de Laravel (v1) | 120 min | Ya existe (cookie de sesión) | Cero | Se conserva como respaldo (primer toque dentro de la sesión) |
| **Cookie propia de Laravel, cifrada, `HttpOnly`, `Secure`, `SameSite=Lax`, 90 días** | 90 días | Solo UTM, host de referencia y fecha; sin identificador de persona; se declara en el aviso; gobernada por el consentimiento (9.3) | Un middleware en `home`; el servidor la lee al guardar la solicitud | **Recomendada** |
| `localStorage` desde JavaScript | Sin vencimiento | Legible por cualquier script de la página; exige JS; no viaja al servidor | Cliente + campo oculto (v1 lo rechazó, alternativa N) | Descartada |
| Solo GA4 / Google Ads | 30–90 días en Google | Los datos viven en el tercero; no llegan a nuestra base ni al CRM | Cero | Insuficiente: no hay dato al revisar una solicitud en la consola |
| ID de visitante guardado en la base | Indefinido | Es un identificador persistente de persona | Tabla nueva | Descartada |

### 10.4 Identificadores de clic de anuncios (decidido: no se guardan)

`gclid`, `gbraid` y `wbraid` **no se guardan** (decisión Q6, sección 18). Su única utilidad real sería importar conversiones fuera de línea a Google Ads, y el dueño no la usará por ahora; el vínculo GA4–Ads atribuye `generate_lead` sin ellos. Son pseudónimos que ligan un clic con una persona (una solicitud con nombre y correo): guardarlos exigiría declararlos en el aviso y borrarlos a los 90 días. El esquema no los incluye; si el dueño cambia de opinión, es una migración y un trabajo aparte.

### 10.5 Diseño

*   **Dos niveles.** (1) **Sesión** de Laravel, siempre: la primera visita con señal guarda en `demo_attribution` los tres UTM y el host de referencia (regla de v1; es la cookie de sesión que el sitio ya usa). (2) **Cookie `humetria_acq`** (nueva), **solo si `Consent::analyticsAllowed()`**: cifrada, `HttpOnly`, `Secure`, `SameSite=Lax`, 90 días, con `{first, last, first_seen_at}`; `first` se escribe una vez; `last` se sobrescribe solo si la visita trae señal. Cifrada, no necesita excepción en `encryptCookies` (`bootstrap/app.php:40`).
*   **Con `denied` hoy (Q5): la cookie de 90 días no se escribe** y la atribución queda limitada a la sesión (120 minutos de inactividad, `config/session.php:37`) hasta que el abogado del dueño decida. El código, las columnas y la consola se entregan y se prueban con el consentimiento activo en las pruebas.
*   **Middleware.** `RememberDemoAttribution` (módulo Demos, `app/Modules/Demos/Http/Middleware/`, directorio nuevo: hoy el módulo solo tiene `Controllers` y `Requests`), solo GET y solo en `home`. Saneamiento de v1: cada UTM se recorta y se acepta si es texto de una línea de 1 a 100 caracteres (si no, se descarta sin error); del referente solo el host, en minúsculas, de 1 a 255 caracteres y solo si `App\Http\SiteHost::owns()` es falso.
*   **Guardado.** `StoreDemoRequestRequest::toData()` arma `DemoAttribution` con `first` y `last` (de la cookie si existe, si no de la sesión). Columnas anulables con `CHECK` de no vacío: `utm_source`, `utm_medium`, `utm_campaign`, `referrer_host` (la campaña asociada: `last`, o `first` si no hay `last`) y `first_utm_source`, `first_utm_medium`, `first_utm_campaign`, `first_referrer_host`. No hay `gclid`, `gbraid` ni `wbraid` (10.4).
*   **Consola.** "Llegó por" (campaña asociada) y, si difiere, "Primer contacto". La tabla de combinaciones de v1 se conserva para ambas filas (fuente y medio unidos por " / ", campaña tras " · ", si no hay UTM el host de referencia, si no `null` → "Sin dato"). **"Sin dato" no significa tráfico directo.** Los métodos `arrivedBy()` y `firstContact()` viven en `DemoRequest` para tener prueba de mutación.
*   **Pruebas existentes que cambian (autorizado, sección 18).** `tests/Feature/Demos/DemoRequestConsoleTest.php:38-74` compara la fila completa; la fila esperada gana `'arrived_by' => null` y `'first_contact' => null`. No hay otra.

--------------------------------------------------------------------------------

## 11. Riesgos

Probabilidad (P) e impacto (I): B baja, M media, A alta. La columna "Se comprueba" remite a la sección 13.

| # | Riesgo | P | I | Mitigación | Se comprueba |
| :-- | :-- | :-- | :-- | :-- | :-- |
| R1 | Node cae o se cuelga en producción | M | M | Degradación D1 (CSR 200 con etiquetas del slot), vigilante, healthcheck que renderiza, comprobación sintética con correo | Pruebas de infraestructura (E); F |
| R2 | El bundle de Node y el PHP son de commits distintos | B | M | Un `docker build`, dos objetivos, etiquetas emparejadas y metas de revisión | Prueba sintética compara `build-revision` y `app-revision` |
| R3 | Fuga de props a Node (propiedad compartida nueva con datos privados) | B | A | Lista deny-by-default, solo anónimos, contrato de llaves | `SsrPropsTest`, `SsrPagesTest` |
| R4 | Una ruta nueva activa SSR o indexación por omisión | B | A | Dos listas cerradas, prueba introspectiva de todas las rutas | Pruebas D |
| R5 | Violaciones de CSP por `style` escrito en el servidor (medidas: 53) | A hoy, B con C1 | M | C1 (CSS, decidido), sin excepción de CSP | Navegador SSR: `cspViolations() === []` |
| R6 | Diferencias de hidratación (tema, video, año, `useReducedMotion`) | M | M | Fila 3, 4, 5 de la matriz; `WhenMounted`; `useSyncExternalStore` | Navegador SSR; consola sin errores de React |
| R7 | Texto invisible sin JavaScript | M (con `motion`) | A | C1 y paneles visibles con `html:not(.js)` | Prueba sin JavaScript |
| R8 | Estado compartido entre visitantes en Node | B | A | Sin estado de módulo en la landing; prueba concurrente | Script de contaminación en CI |
| R9 | Memoria de Node crece | M | M | `mem_limit`, `max-old-space-size`, vigilante, medición | E4; revisión de `docker stats` tras el lanzamiento |
| R10 | Los nueve paneles rompen una aserción existente | A | M | Autorizado solo `:251-257` y `:309`; el diseño de 5.5 cambia únicamente `:309` y agrega aserciones | Revisión de la lista de pruebas modificadas |
| R11 | Redirigir `www` invalida las llaves de acceso | B | A | Confirmar `APP_URL` antes (Q12) | Revisión manual; `curl` |
| R12 | Cookie `_ga` o de atribución en logs del proxy | M | A | Revisar logs de nginx y Traefik antes de activar; prueba con cabecera `Cookie` cruda | Revisión manual + prueba automática en la app |
| R13 | Analytics se activa antes del aviso o de la verificación legal | M | A | ID vacío y `consent_default=denied` por omisión | Orden de despliegue; prueba de configuración |
| R14 | Vista previa guardada rota si Node cae justo cuando una red rastrea | B | M | Etiquetas del slot | Prueba con Node caído: `og:*` presentes |
| R15 | La imagen `ssr` o sus paquetes quedan sin parchear | M | M | Reconstrucción semanal programada `deploy.yml` (P2); `npm audit` ya corre en `checks` | Fuera de alcance de P0; anotado |
| R16 | Una dependencia de `npm audit` se vuelve vulnerable en el bundle SSR | B | M | Mismo proceso del repositorio (`tests.yml:54-57`) | CI |
| R17 | El rediseño de `docs/rediseno-landing/` choca con estos cambios | M | M | Decidido (Q11): primero el SSR; el rediseño se hace después sobre esta base | Revisión de PR |
| R18 | Hora de despliegue con versiones mezcladas de `web` y `ssr` | M | B | React se recupera; la comprobación sintética lo detecta | F3 |

--------------------------------------------------------------------------------

## 12. Plan detallado de implementación

**Estado:** las decisiones de la sección 18 están cerradas; lo que sigue es el plan de ejecución. No se implementa nada hasta que el dueño apruebe este documento. Es un solo PR ("landing con SSR, SEO y medición"), con un commit por comportamiento. **Regla de cada commit:** la prueba se escribe antes, se ve fallar localmente por la razón correcta (columna "Prueba que falla antes") y el commit trae la prueba y el código que la pasa; todos los commits dejan en verde lo que existía antes. Las pruebas existentes no se aflojan; las únicas que cambian son las que el dueño autorizó (sección 18) y están listadas en 5.5 y 10.5.

**Orden y dependencias.** Cada pieza usa solo lo que crean las anteriores:

```text
Pieza 1  noindex y lista de páginas indexables
Pieza 2  aviso de privacidad                       (usa la lista de la 1)
Pieza 3  política de SSR en Laravel y configuración (config, SsrPages, listeners; sin bundle: todo con Http::fake)
Pieza 4  etiquetas del <head> (seo, SeoHead, slot)  (usa la config de la 3 para simular SSR)
Pieza 5  bundle SSR, banco de pruebas y cliente compatible (job `ssr` de CI; usa 3 y 4)
Pieza 6  imagen, compose, despliegue y comprobación sintética (usa 5)
Pieza 7  consentimiento, GA4 y conversión           (usa 1; sin dependencia de 3 a 6)
Pieza 8  procedencia y atribución                  (usa el consentimiento de la 7)
Pieza 9  reglas de CLAUDE.md y manual de operación
```

**Prioridades** (handoff 16). **P0** (bloquea): piezas 3, 5 y 6, y de la 4 las entradas del respaldo de etiquetas. Justificación: sin ellas no hay SSR real, ni HTML legible, ni hidratación, ni CSP, ni exclusión de rutas, ni operación fiable. **P1** (requisito del lanzamiento comercial): piezas 1, 2, 4 (metadatos, canonical, previews), 7 y 8 (GA4 y atribución confiable). **P2:** 12.12. La pieza 9 es P0 (las reglas) y P1 (el manual). Nada que sea requisito de los doce objetivos del handoff se difirió para reducir el PR.

**Cómo leer cada pieza.** Se da: *Problema* (qué resuelve), *Por qué* (necesidad), *Riesgo*, *Reversión* y *Producción* (cómo se comprueba en producción). Las pruebas que validan cada entrada están en su fila.

**Archivos de configuración de pruebas que varias piezas tocan** (se listan aquí para que no falten):

| Archivo | Cambio | Pieza |
| :-- | :-- | :-- |
| `phpunit.xml` | `INERTIA_SSR_ENABLED=false` forzado (pieza 3); nueva suite `Ssr` con `tests/Ssr` (pieza 5) | 3, 5 |
| `tests/Pest.php:42` | `->in('Feature', 'Browser', 'Ssr')` (la suite `Ssr` usa `TestCase` y `RefreshDatabase`) | 5 |
| `composer.json` | `--exclude-testsuite=Ssr` en `test:coverage`, `test:fast`, `mutate` y `mutate:retry` (hoy excluyen `Browser` o corren todo: líneas 95-96, 114, 131-136); script nuevo `test:ssr` | 5 |
| `.github/workflows/tests.yml` | job `ssr` (en `needs` de `ci`) | 5 |

### 12.0 Verificaciones previas que quedan abiertas (E3 a E5)

E1 y E2 ya se midieron (sección 17). Quedan E3 (Dokploy), E4 (vigilante y memoria dentro del contenedor) y E5 (Google), definidos en la sección 15. Ninguna bloquea las piezas 1 a 5; E3 y E4 se corren al terminar la pieza 6 y E5 antes de poner un ID de medición. El experimento sobre el navegador sin JavaScript (antes E6) se resuelve: se usa `playwright` directo (entrada E-62).

### 12.1 Pieza 1 (P1). Lista de páginas indexables y `noindex` por defecto

*   **Problema:** la cabecera `X-Robots-Tag: noindex, nofollow` se pone a mano en cuatro respuestas y una meta (`resources/views/app.blade.php:7-9`); el login, las encuestas, los portales y el reloj checador no llevan nada. **Por qué:** una ruta nueva nace indexable. **Riesgo:** una página legítima queda con `noindex` (se corrige agregándola a la lista). **Reversión:** quitar la cabecera y la lista; sin datos. **Producción:** `curl -sI https://humetria.com.mx/admin/login` trae la cabecera; `curl -sI https://humetria.com.mx/` no.

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-1 | `app/Http/IndexablePages.php` | Create | `final`, kernel compartido; `ROUTES = ['home']` (`privacy-notice` entra en la pieza 2, cuando existe la ruta); `includes(?string): bool`. | `IndexablePagesTest` (clase inexistente) |
| E-2 | `app/Http/Middleware/SecurityHeaders.php` | Modify | Agrega `X-Robots-Tag: noindex, nofollow` al arreglo de `:52-58`, salvo ruta en la lista + respuesta 200 + `App::isProduction()`. Comentario de clase (`:17-21`) actualizado. | casos de E-4 |
| E-3 | `tests/Feature/IndexablePagesTest.php` | Create | `mutates(IndexablePages::class)`; cada nombre existe, es GET, sin parámetros, sin middleware `auth`; `includes()` con `home`, `null`, un nombre desconocido y `login`. | falla: la clase no existe |
| E-4 | `tests/Feature/SecurityHeadersTest.php` | Modify (casos nuevos) | Producción (`app()->detectEnvironment(fn () => 'production')`, como `:83` de v1, **por reconfirmar**): `/` sin la cabecera; login, una URL inexistente, `survey.show` con un token inventado (200), `route('well-known.passkeys')` (JSON) y un 500 en `/` (con `config(['app.debug' => false])` y la ruta `home` reemplazada por una que lanza) la llevan. Fuera de producción `/` la lleva. Una cabecera ya puesta con otro valor se conserva. | todos fallan: hoy no hay cabecera |

### 12.2 Pieza 2 (P1). Aviso de privacidad

*   **Problema:** el formulario recaba datos personales y no hay aviso (`demo-request-form.tsx:120` solo dice "Usamos tus datos solo para contactarte."); `MAIL_PRIVACY_URL` apunta a nada (`config/mail.php:140-143`). **Riesgo:** texto legal sin revisar: es un borrador, publicado solo tras la revisión del abogado del dueño. **Reversión:** quitar ruta y enlaces. **Producción:** `curl` de `/privacy-notice` con el texto en el HTML (ya con SSR, pieza 5).

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-5 | `routes/web.php` | Modify | `GET /privacy-notice`, nombre `privacy-notice`, closure como `home` (`routes/web.php:12`), con `legalName` y `legalAddress` desde `mail.legal.*` (texto no vacío o `null`; una variable vacía da `''`). | `PrivacyNoticeTest` (404) |
| E-6 | `app/Http/IndexablePages.php`, `tests/Feature/IndexablePagesTest.php` | Modify | Agrega `privacy-notice`. | el caso "cada nombre existe" ya cubre; la prueba de E-9 en producción |
| E-7 | `tests/Feature/RouteSecurityTest.php` | Modify | Alta en `publicRouteNames()` con la razón "guest: el aviso de privacidad del sitio; texto fijo, sin datos de nadie". | la propia suite falla sin el alta |
| E-8 | `config/mail.php`, `.env.example`, `.env.production.example` | Modify | `legal.name` ← `MAIL_LEGAL_NAME`; en `.env.production.example`, `MAIL_PRIVACY_URL=https://<dominio>/privacy-notice` (el marcador `<dominio>` es el mismo estilo de `APP_URL=https://<dominio>`, `:10`). | `tests/Unit/EnvExampleTest.php` |
| E-9 | `tests/Feature/PrivacyNoticeTest.php` | Create | 200 y componente para visitante y para sesión iniciada; props de configuración; `null` si es vacío; en producción sin `X-Robots-Tag`. | 404 |
| E-10 | `resources/js/pages/privacy-notice.tsx`; `components/landing/privacy-notice-header.tsx`, `privacy-notice-content.ts`, `privacy-notice-article.tsx` | Create | Página delgada (`<SeoHead>` llega en la pieza 4; aquí `<Head title>`). Secciones, cada una con `<h2>`: "Quién es el responsable", "Qué datos recabamos en el formulario de demo y para qué", "De dónde llegaste", "Cookies propias", "Cookies de analítica y de atribución", "Cómo borrar o rechazar las cookies", "Cuánto tiempo conservamos los datos", "Cómo ejercer tus derechos". Responsable, domicilio y correo salen de las dos props y de `support.email` (`HandleInertiaRequests.php:49`); el plazo de conservación es "pendiente de revisión legal". El texto literal es un borrador que se escribe en este commit; no puede contener palabras de `ENGLISH_STOPLIST` (`tests/Pest.php:269-277`: `email`, `link`, `password`, `account`, `address`, `name`, `profile`, `security`, `code`, `new`, `more`…). | `visitPage('privacy-notice', ...)` (E-13) |
| E-11 | `components/landing/landing-footer.tsx`, `demo-privacy-note.tsx`, `demo-request-form.tsx` | Modify/Create | `landing-footer.tsx` gana `sectionBase?: string` (por defecto `''`): los enlaces de sección pasan de `#como-funciona` a `` `${sectionBase}#como-funciona` ``; la página del aviso pasa `home().url`. Enlace "Aviso de privacidad" como `<a href={privacyNotice().url}>`. La frase de `demo-request-form.tsx:119-121` pasa a `DemoPrivacyNote` (archivo propio: el formulario ya usa 98 de 100 líneas por función) con el texto "Usamos tus datos para contactarte y para saber cómo llegaste a Humetría. Consulta el aviso de privacidad.". | caso de E-13 |
| E-12 | `app/Http/PageFrame.php:15-36`, `resources/js/lib/page-frame.ts`, `tests/js/lib/page-frame.test.ts` | Modify | `privacy-notice` en `STANDALONE_NAMES` en las dos listas. | `tests/Unit/PageFrameTest.php` |
| E-13 | `tests/Browser/PagesTest.php`, `tests/Browser/LandingTest.php` | Modify (casos nuevos) | `visitPage('privacy-notice', '/privacy-notice', …)` en claro, oscuro, escritorio y 375 px (lo exige `tests/Unit/BrowserCoverageTest.php`); el pie y la nota enlazan al aviso y cargan un documento nuevo (una marca en `window` no existe en el destino). | los nuevos |

### 12.3 Pieza 3 (P0). Política de SSR en Laravel y señales de fallo (sin bundle)

*   **Problema:** `config/inertia.php:21` activa el SSR para todas las páginas y enviaría el objeto de página completo a Node (H7); sin bundle el gateway devuelve `null` antes de cualquier HTTP; no hay señal de fallo. **Por qué:** objetivos 6, 11 y 12. **Riesgo:** un cierre mal escrito deja pasar una página privada; lo cubre la prueba introspectiva. **Reversión:** `INERTIA_SSR_ENABLED=false` y reiniciar `web`. **Producción:** `X-Rendered-By` (pieza 6 lo verifica de fuera).

Todas las pruebas de esta pieza usan `config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false])` y `Http::fake(['http://127.0.0.1:13714/render' => ...])`; no hay bundle ni Node.

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-15 | `config/inertia.php`, `.env.example`, `.env.production.example`, `phpunit.xml` | Modify | `ssr.enabled` ← `INERTIA_SSR_ENABLED` (defecto `false`), `ssr.url` ← `INERTIA_SSR_URL` (defecto el actual; `http://humetria-ssr:13714` en producción), `ssr.timeout` ← `INERTIA_SSR_TIMEOUT` (defecto 2), `ssr.ensure_bundle_exists` ← `INERTIA_SSR_ENSURE_BUNDLE` (defecto `true` en local y `false` en `.env.production.example`: el contenedor `web` no tiene el bundle, `HttpGateway.php:52`). `phpunit.xml` fuerza `INERTIA_SSR_ENABLED=false`. | `tests/Feature/SsrConfigTest.php`: `config('inertia.ssr.enabled')` es falso bajo pruebas y `timeout` es 2 (falla: hoy `true` y sin `timeout`); `EnvExampleTest` |
| E-16 | `app/Http/SsrPages.php` | Create | `final`; `ALLOWED = ['home' => 'landing', 'privacy-notice' => 'privacy-notice']`; `allows(Request, array $page): bool` verdadero solo si `config('inertia.ssr.enabled')` (**no** `env()`), el nombre de ruta existe en `ALLOWED`, el componente de `$page['component']` es el asociado, `$request->user() === null` y el cortacircuitos está cerrado (E-18). | `SsrPagesTest` |
| E-17 | `app/Providers/AppServiceProvider.php` | Modify | En `boot()` (`:51-57`): `Inertia::disableSsr(fn (): bool => ! SsrPages::allows(request(), app(SsrState::class)->page))`. `SsrState` es `scoped` (`vendor/inertiajs/inertia-laravel/src/ServiceProvider.php:32`). | `SsrPropsTest` y `SsrPagesTest` (no se envía ninguna petición hoy) |
| E-18 | `app/Listeners/LogSsrRenderFailure.php` | Create | Escucha `Inertia\Ssr\SsrRenderFailed` (descubrimiento automático, como `RecordLastLogin`): escribe `ssr.render_failed` con `type`, `component`, `path` (sin query) y `hint`, sin `page`, `stack` ni `error`; a lo más una línea por tipo y minuto (`Cache::add("ssr:failed:{type}", 1, 60)`), el resto suma a `Cache::increment("ssr:failed:count")`. Con `type = connection` abre el **cortacircuitos**: `Cache::put('ssr:circuit-open', true, 30)`; mientras exista, `SsrPages::allows()` devuelve falso. Evita que un Node colgado cueste el plazo de 2 s a cada visita. | `SsrDegradationTest` |
| E-19 | `app/Listeners/LogSsrRender.php` | Create | Escucha `Illuminate\Http\Client\Events\ResponseReceived`; si la URL es la de `inertia.ssr.url` + `/render` escribe `ssr.rendered` con `status` y `duration_ms` (de `transferStats`, `null` si no existe). Es la métrica de solicitudes y latencia del handoff 13. | `SsrDegradationTest` (la línea existe con `status`; la duración solo se afirma en `tests/Ssr`) |
| E-20 | `app/Http/Middleware/AnnounceRenderMode.php`, `bootstrap/app.php` | Create/Modify | Pone `X-Rendered-By: ssr` si `app(SsrState::class)->response !== null`, `csr` si la ruta es de `SsrPages` y no hubo respuesta; en cualquier otra, nada. Se registra en el grupo `web` después de `HandleInertiaRequests` (`bootstrap/app.php:48-57`). | casos de E-21 |
| E-21 | `tests/Feature/SsrPagesTest.php`, `SsrPropsTest.php`, `SsrDegradationTest.php`, `SsrConfigTest.php` | Create | Ver sección 13 (D y E). También `tests/Feature/SsrDocumentTest.php` (A2: el documento con una respuesta SSR simulada). `mutates(SsrPages::class)`, `mutates(LogSsrRenderFailure::class)`. `SsrPagesTest` **tabla**: para cada ruta GET de `Route::getRoutes()`, cada parámetro (`parameterNames()`) se llena con el valor fijo `'x'` (los de token siguen dando 200 de "enlace no disponible" o 404); se cuenta con `Http::recorded()` cuántas peticiones fueron a `/render` y se repite con un usuario con sesión. | los cuatro archivos son nuevos |
| E-22 | `tests/Feature/SecurityHeadersTest.php` | Modify (caso) | Con SSR encendido y respondiendo, la CSP de `/` es la de hoy (`cspFor`). | pasa desde el inicio: es una guarda de regresión, no una prueba roja |
| E-23 | `tests/Ssr/fixtures/page-keys.json` | Create | Golden: las llaves de primer nivel de `props` que recibe Node para un anónimo (`errors`, `name`, `support`, `auth`, `impersonator`, `tenant`, `tenants`, `inCourse`, `firstSteps`, `sidebarOpen`, y desde la pieza 4 `seo`) y las de la página (`component`, `props`, `url`, `version`, `flash` si existe). `SsrPropsTest` la compara; la usa también el Node del banco (E-55). | `SsrPropsTest` |

### 12.4 Pieza 4 (P1; las entradas E-24 a E-30 son P0 por el respaldo de etiquetas)

*   **Problema:** `<title>` único y sin descripción ni Open Graph (`resources/views/app.blade.php:47-49`; `landing.tsx:23-28` pone título y descripción solo en el cliente). **Por qué:** previews, rastreadores y la política de degradación (7.6). **Riesgo:** dos renderizadores de una fuente que se desfasan: lo impide el golden de E-33. **Reversión:** quitar el parcial y `SeoHead`; sin datos. **Producción:** `curl` con `og:title`, `og:image`, canonical y JSON-LD; depuradores de LinkedIn y Meta.

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-24 | `lang/es/seo.php` | Create | `home` y `privacy-notice` con `title` y `description` (la de `home` ≤ 160 caracteres y con los nueve nombres: lo exige `tests/Browser/LandingTest.php:165`), `image_alt`. | `PageMetaTest` |
| E-25 | `config/app.php`, `.env.example`, `.env.production.example` | Modify | `'revision' => env('APP_REVISION', 'unknown')` y `APP_REVISION=` (vacío). **Cierra el hueco de la revisión:** la imagen `app` fija `APP_REVISION` desde `GIT_SHA` (E-47). | `EnvExampleTest`; `PageMetaTest` (meta `app-revision`) |
| E-26 | `app/Http/PageSeo.php` | Create | `final`; `for(string $routeName): array` con `title`, `description`, `canonical` (`config('app.url')` sin barra final + path, sin query), `image` (`url`, `width: 1200`, `height: 630`, `alt`), `locale` (`es_MX`), `siteName`, `jsonLd`. **`jsonLd` es una cadena** de `json_encode(['@context' => 'https://schema.org', '@graph' => [...Organization, ...WebSite]], JSON_HEX_TAG \| JSON_UNESCAPED_SLASHES \| JSON_UNESCAPED_UNICODE \| JSON_THROW_ON_ERROR)`: `JSON_HEX_TAG` convierte `<` y `>` en `<` y `>`, de modo que un texto con `</script>` no cierra el bloque. Se imprime con `{!! !!}` en Blade y con `dangerouslySetInnerHTML` en React (que `Head` inserta sin escapar: `@inertiajs/react/dist/index.js:1195-1205`). | `PageSeoTest` (`mutates(PageSeo::class)`): una descripción con `</script><script>alert(1)` produce un JSON sin `<`; el canonical no lleva query ni doble barra |
| E-27 | `routes/web.php` | Modify | `home` y `privacy-notice` pasan `seo` como prop. `tests/Ssr/fixtures/page-keys.json` (E-23) gana `seo`. | `SsrPropsTest` |
| E-28 | `resources/js/components/landing/seo-head.tsx` | Create | `<Head>` desde `seo`: `<title>`, `description`, `canonical`, `og:*`, `twitter:*`, `build-revision` (`import.meta.env['VITE_BUILD_REVISION']`, vacío en local) y el JSON-LD, cada uno con `head-key`. Sin textos propios. | el caso de navegador "un solo ..." de E-31: con el slot (E-30) y sin `SeoHead` hay dos descripciones (la del slot y la de `landing.tsx`); además `seo-parity.test.mjs` (pieza 5) |
| E-29 | `resources/js/pages/landing.tsx`, `pages/privacy-notice.tsx` | Modify | Usan `SeoHead`; se quita el `<Head title>` y la `<meta description>` de `landing.tsx:23-28`. | `LandingTest.php:161-168` (título y descripción en el DOM: debe seguir pasando) |
| E-30 | `resources/views/partials/seo-fallback.blade.php`; `resources/views/app.blade.php` | Create/Modify | El parcial escribe las mismas etiquetas con `data-inertia="<llave>"` (las mismas `head-key` de `SeoHead`) desde `$page['props']['seo']`; `app.blade.php` lo incluye **dentro del slot** de `<x-inertia::head>` si existe `seo`, y conserva `<title>{{ config('app.name') }}</title>` si no. Fuera del slot, siempre: `<meta name="app-revision" content="{{ config('app.revision') }}">`. | `PageMetaTest` |
| E-31 | `tests/Feature/PageMetaTest.php`, `tests/Feature/IndexablePagesTest.php`, `tests/Browser/LandingTest.php` | Create/Modify | Con SSR apagado: título propio, descripción ≤ 160, canonical sin query y con `app.url` aunque llegue por otro host, `og:*`, `twitter:*`, JSON-LD, `app-revision`. Con SSR simulado que responde: el slot **no** aparece. Con SSR simulado que falla (500) o sin respuesta: el slot aparece. El login y una página de error servida en `/` no traen etiquetas. `public/og-v1.png` mide 1200×630 y `public/app-icon-512.png` existe. Navegador: tras cargar JavaScript, un solo `<title>`, una sola descripción, un solo canonical y un solo JSON-LD (caso nuevo en `LandingTest.php`). Sitemap = lista. `tests/Unit/NoDuplicatedLandingTextTest.php` (A5: sin `<noscript>` en `resources/views`; guarda de regresión, pasa desde el inicio). | los nuevos |
| E-32 | `public/og-v1.png`, `docs/design-system/og/og-v1.html` | Create | Imagen de 1200×630 (logo, `<h1>` de la landing, `humetria.com.mx`), con el comando de captura de `npx playwright screenshot --viewport-size=1200,630` en el HTML fuente. | `PageMetaTest` (el archivo existe y mide 1200×630) |
| E-33 | `tests/Ssr/fixtures/seo-tags.json`; `tests/Feature/SeoFallbackSnapshotTest.php` | Create | **Paridad entre renderizadores sin leer React desde PHP:** un golden con la lista normalizada de etiquetas (`[etiqueta, atributos]`) para un `seo` fijo. El Feature renderiza el parcial de Blade con ese `seo` y lo compara con el golden; el banco de Node (`seo-parity.test.mjs`, pieza 5) renderiza el bundle con el mismo `seo` y compara su `head` con el mismo golden. Si alguien cambia un renderizador sin el otro, falla uno de los dos. | `SeoFallbackSnapshotTest` |
| E-34 | `public/sitemap.xml`, `public/robots.txt` | Create/Modify | Las dos URLs; línea `Sitemap:`; sigue sin `Disallow` con ruta. | el caso de E-31 |

### 12.5 Pieza 5 (P0). Bundle SSR, banco de pruebas y cliente compatible

*   **Problema:** el bundle no carga en Node (`app.tsx:15-19`), y con él el HTML trae 53 atributos `style` (42 con `opacity:0`), tres `<video>` con el póster del tema claro, una sola herramienta, y la hidratación en oscuro falla con el error 418 (sección 17). **Por qué:** objetivos 2 a 4, 6.2 y 6.3 del handoff. **Riesgo:** regresión visual de las animaciones y diferencias de hidratación; las pruebas existentes de navegador (con SSR apagado) siguen pasando en cada commit. **Reversión:** cada commit se revierte por separado. **Producción:** verificación visual en claro, oscuro y 375 px contra `docs/design-system/pantallas/`, y la comprobación sintética de la pieza 6.

**Banco de pruebas (el sitio de las pruebas que necesitan Node).** `scripts/ssr/test.sh` (`composer test:ssr`): `npm run build:ssr`; arranca `node bootstrap/ssr/app.js` en segundo plano en el puerto 13714 y espera `GET /health`; arranca `php artisan serve --port=8000` con `INERTIA_SSR_ENABLED=true INERTIA_SSR_URL=http://127.0.0.1:13714 INERTIA_SSR_ENSURE_BUNDLE=true`; corre `php artisan test --testsuite=Ssr` (Pest Browser) y `BASE_URL=http://127.0.0.1:8000 node --test tests/Ssr/node/` (el ejecutor de pruebas incluido en Node, sin dependencias nuevas); termina los procesos. En CI es el job `ssr` (E-35): Node 24 (la versión de `NODE_IMAGE`, `Dockerfile:8`; el action `setup` fija 22, `.github/actions/setup/action.yml`, que se vuelve a fijar en 24 en ese job), Postgres como los demás, `npx playwright install --with-deps chromium`. La suite `Ssr` queda fuera de `test`, `test:coverage`, `test:fast` y `mutate` (E-36).

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-35 | `.github/workflows/tests.yml`; `scripts/ssr/test.sh`; `composer.json` | Modify/Create | Job `ssr` (`needs` de `ci`); script y `test:ssr`. | el propio job (sin el bundle falla) |
| E-36 | `phpunit.xml`; `tests/Pest.php:42`; `composer.json` | Modify | Suite `Ssr`; `uses(...)->in('Feature', 'Browser', 'Ssr')`; `--exclude-testsuite=Ssr` en los scripts de `composer.json` que hoy excluyen `Browser` (`test:coverage`, `mutate`, `mutate:retry`) y en `test:fast`. | `composer test:coverage` no corre `tests/Ssr` |
| E-37 | `vite.config.ts` | Modify | `laravel({ input: [...], ssr: 'resources/js/app.tsx', ... })` y `ssr: { noExternal: true }` (medido en la sección 17: `vite-plus` 1.4.0 respeta la opción; el bundle queda en `bootstrap/ssr/app.js` y `BundleDetector` lo encuentra sin `inertia.ssr.bundle`). | `tests/Ssr/node/render.test.mjs` (sin bundle) |
| E-38 | `resources/js/app.tsx` | Modify | La precarga del marco (`:15-19`) corre solo si existe `document`; el archivo sigue sin importar `@/layouts/*` (`tests/Unit/FrontendArchitectureTest.php:229-233`). `.gitignore:2` ya ignora `bootstrap/ssr`; `.dockerignore` lo gana en E-46. | `render.test.mjs`: `ReferenceError: document is not defined` (medido) |
| E-39 | `scripts/ssr/fixtures/landing-page.json`, `tests/Ssr/node/render.test.mjs` | Create | Un objeto de página de anónimo (llaves de `page-keys.json`; `seo` es el de `tests/Ssr/fixtures/seo-tags.json`). Vive en `scripts/ssr/` porque la imagen lo copia (`.dockerignore` excluye `tests/`); `render.test.mjs` hace `POST /render` y afirma 200 con `head` y `body`. | falla hasta E-38 |
| E-40 | `resources/js/hooks/use-appearance.tsx` | Modify | `resolvedAppearance` por `useSyncExternalStore(subscribe, () => (isDarkMode(currentAppearance) ? 'dark' : 'light'), () => 'light')`; fuera, el cálculo de `:110-112`. | `tests/Ssr/LandingSsrTest.php` caso "hidrata en oscuro" (hoy: error 418, 3 de 3) |
| E-41 | `components/landing/loop-video.tsx`, `tool-film-player.tsx`, `privacy-demo.tsx` | Modify | `<video>` y `AnimatePresence` envueltos en `WhenMounted` de `@inertiajs/react` (`react/dist/index.js:2050-2063`) con un `fallback` del mismo tamaño (`aspect-video`; para `privacy-demo` el resultado sin animar). | `tests/Ssr/node/no-video-in-ssr.test.mjs` (hoy: tres `<video autoplay>`, medido) |
| E-42 | `components/landing/landing-footer.tsx` | Modify | El año se calcula dentro del componente (`:8`). | `tests/Unit/LandingServerSafetyTest.php` (E-43) |
| E-43 | `tests/Unit/LandingServerSafetyTest.php`; `tests/Unit/LandingModuleStateTest.php` | Create | Estáticas (PHP leyendo los `.tsx`): sin `initial=`, `whileInView`, `animate=` ni `style={{` en `components/landing/` salvo la lista de excepciones con su razón (`film-chapters.tsx`, que solo se monta en el navegador); `app.tsx` no llama a `document` sin `typeof document`; sin `new Date()` a nivel de módulo ni `let` de módulo en `components/landing/`. | fallan hoy (53 usos, `landing-footer.tsx:8`, `app.tsx:15`) |
| E-44 | `resources/css/app.css`; componentes de la tabla 5.3 | Modify | Animaciones en CSS con los valores exactos de 5.3; `landing.tsx` instala `useScrollReveal()` (hook nuevo en `hooks/`). Lista de componentes: `hero-headline.tsx`, `landing-hero.tsx`, `reveal.tsx`, `demo-next-steps.tsx`, `inspection-line.tsx`, `how-it-works-step.tsx`, `how-it-works-section.tsx`, `step-bar.tsx`, `loop-video.tsx`, `tool-stage.tsx`. | `tests/Ssr/node/no-style-attributes.test.mjs` (hoy 53) y `tests/Ssr/LandingSsrTest.php` (CSP `[]`; hoy 53 violaciones); `tests/Browser/LandingTest.php` caso nuevo "los bloques bajo el pliegue se revelan al desplazarse" (`data-reveal` pasa de `pending` a `done`) |
| E-45 | `components/landing/tools-section.tsx`, `tool-tabs.tsx`, `tool-stage.tsx` (→ `tool-panels.tsx`); `resources/views/app.blade.php:12-24` | Modify/Create | Nueve paneles según 5.5; el script inline añade `js` a `<html>`. | `LandingTest.php`: los casos nuevos de 5.5 y el cambio de `:309`; `tests/Feature/LandingShellTest.php` (el HTML trae `classList.add('js')`); `tests/Ssr/node/nine-tools.test.mjs` (hoy un solo `role="tabpanel"`, medido) |

**Pruebas del banco de esta pieza** (todas nuevas, fallan antes de su código):

| Archivo | Qué afirma |
| :-- | :-- |
| `tests/Ssr/node/nine-tools.test.mjs` | En el `body` renderizado, el nombre y el `hint` de las nueve herramientas están en nodos de contenido (se analiza con el parser HTML de la prueba: se descartan coincidencias dentro de `script`, `data-page`, `meta` y `noscript`); un `<h1>`; `data-server-rendered`; la fuente de las nueve es `tests/Ssr/fixtures/landing-expected.json`, que se compara con el render real en cada corrida (si un componente cambia, falla el banco y se actualiza el golden). |
| `tests/Ssr/node/no-style-attributes.test.mjs` | Ningún elemento del `body` lleva `style`. |
| `tests/Ssr/node/no-video-in-ssr.test.mjs` | Ningún `<video>`. |
| `tests/Ssr/node/seo-parity.test.mjs` | El `head` coincide con `seo-tags.json` (E-33). |
| `tests/Ssr/node/concurrency.test.mjs` | 50 `POST /render` en paralelo con marcas distintas en `props.errors` y `url`; cada respuesta contiene solo la suya. |
| `tests/Ssr/node/budget.test.mjs` | 200 renders seguidos: p95 ≤ 150 ms (medido: 4 a 5 ms); HTML ≤ 250 KB (medido: 123 KB). Es el presupuesto del handoff 12 G. |
| `tests/Ssr/node/nojs.test.mjs` | `playwright` con `javaScriptEnabled: false` contra `BASE_URL`: el `<h1>` es visible (`opacity` calculada 1), los nueve nombres son legibles y los enlaces de navegación existen. |
| `tests/Ssr/node/form-before-hydration.test.mjs` | `playwright`: se bloquea el trozo de JavaScript con `route.abort`, se escribe en el formulario del HTML SSR, se libera y se afirma que el valor se conserva. |
| `tests/Ssr/LandingSsrTest.php` | Pest Browser, con `Http::allowStrayRequests(['127.0.0.1:13714/*'])` (`vendor/laravel/framework/src/Illuminate/Http/Client/Factory.php:451`; la prueba fija `inertia.ssr.enabled` y `ensure_bundle_exists` en `true`): `visitPage('landing', '/')` en claro y oscuro, escritorio y 375 px; `assertNoJavaScriptErrors()`; `assertNoConsoleLogs()` (`vendor/pestphp/pest-plugin-browser/src/.../MakesConsoleAssertions.php:61`, que el reporte de revisión confirma) para los errores de hidratación; `cspViolations()` vacío; un solo `<title>`, descripción, canonical y JSON-LD. |

### 12.6 Pieza 6 (P0). Imagen, compose, despliegue y comprobación sintética

*   **Problema:** no existe imagen con Node, ni servicio, ni comprobación fuera del contenedor (H1, H17). **Por qué:** objetivos 11 y 12. **Riesgo:** una imagen y un servicio más; un Dokploy que no respete la red interna (E3). **Reversión:** quitar el servicio y poner `INERTIA_SSR_ENABLED=false`; el sitio sigue en CSR con las etiquetas del slot. **Producción:** sección 14.

**CI y la regla del harness.** Esta pieza toca `deploy.yml` y `tests.yml`. Las necesita la feature: sin construir la imagen `ssr` no hay SSR en producción; sin el job `ssr` ninguna prueba ejercita Node (N-1) y un bundle roto llegaría a `main` oculto por la degradación; sin la comprobación sintética el fallo es silencioso por diseño de Inertia (handoff 8.5). Quedan **fuera** (PR aparte): construir la imagen completa en cada PR, escaneo de imágenes, reconstrucción semanal, Lighthouse y paneles de alertas. **Decisión para el job `ssr`:** corre `npm run build:ssr` y arranca el bundle desde el espacio de trabajo (pieza 5); además `docker build --target ssr` y arranca esa imagen para las pruebas de contenedor (healthcheck, usuario `node`, vigilante). El `target: app` no se construye por PR.

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-46 | `.dockerignore` | Modify | Agrega `bootstrap/ssr` (un bundle local no entra a la imagen `app`). El resto ya excluye `tests` y `docs`: **por eso** el fixture y los scripts de la imagen viven en `scripts/ssr/`, no en `tests/` (`scripts/` no está excluido). | el job `docker build` |
| E-47 | `Dockerfile` | Modify | (1) `assets` (`:33-39`): `ARG GIT_SHA=unknown`, `ENV VITE_BUILD_REVISION=$GIT_SHA` y `npm run build:ssr` en lugar de `npm run build`. (2) Etapa nueva `ssr`, **antes** de `app` (la etapa final sigue siendo `app`, `:42`, para que `docker build .` sin `target` produzca la imagen web): `FROM ${NODE_IMAGE} AS ssr`; `WORKDIR /srv/ssr`; `COPY --from=assets --chown=node:node /var/www/html/bootstrap/ssr ./bootstrap/ssr`; `COPY --chown=node:node scripts/ssr ./scripts/ssr`; `RUN printf '{"type":"module"}' > package.json` (el bundle es ESM y la imagen no trae `package.json`); `USER node`; `CMD ["node","scripts/ssr/supervisor.mjs"]`. (3) `app`: `ENV APP_REVISION=${GIT_SHA}` junto al `ARG GIT_SHA` de `:54`. | job `ssr`: `docker build --target ssr` falla; `PageMetaTest` ve `unknown` |
| E-48 | `scripts/ssr/supervisor.mjs`, `scripts/ssr/healthcheck.mjs`, `scripts/ssr/fixtures/landing-page.json` | Create | Ver 7.4 y 7.5. El fixture es el de E-39 (un solo archivo). | `tests/Ssr/node/supervisor.test.mjs` y `healthcheck.test.mjs` (abajo) |
| E-49 | `tests/Ssr/node/supervisor.test.mjs`, `healthcheck.test.mjs`, `tests/Ssr/node/fixtures/{good,throws,hangs}.mjs` | Create | El supervisor con un bundle bueno (sigue vivo), uno que lanza al importar (sale con código ≠ 0) y uno con un bucle infinito en `/render` (sale por plazo, con los parámetros de 7.5 reducidos por variables de entorno para la prueba). El healthcheck contra el bundle real: 0 con render correcto, 1 si la respuesta no trae el `<h1>`. | falla: los scripts no existen |
| E-50 | `compose.production.yaml` | Modify | (1) En el ancla `x-app` (`:20-31`) la imagen pasa a `'${HUMETRIA_IMAGE:-ghcr.io/vicbravodev/humetria}:${HUMETRIA_TAG:-latest}'` (así `web`, `horizon` y `scheduler` siguen **juntos** al mismo `HUMETRIA_TAG`; se actualiza el comentario de `.env.production.example:65`). (2) `web.networks` se repite completa (`default`, `dokploy-network`, `ssr-internal`): la clave `networks` de un servicio reemplaza la del ancla, no se suma. (3) Servicio `ssr`: `image: '${HUMETRIA_SSR_IMAGE:-ghcr.io/vicbravodev/humetria-ssr}:${HUMETRIA_TAG:-latest}'`, `networks: { ssr-internal: { aliases: [humetria-ssr] } }`, `restart: unless-stopped`, `init: true`, `read_only: true`, `tmpfs: [/tmp]`, `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]`, `mem_limit: 384m`, `cpus: '1.0'`, `pids_limit: 100`, healthcheck `['CMD','node','scripts/ssr/healthcheck.mjs']` (intervalo 30 s, `timeout` 5 s, 3 reintentos, `start_period` 20 s), sin `env_file`, sin `ports`, sin `depends_on`. (4) Red `ssr-internal` con `internal: true`. (5) El comentario de cabecera (`:1-12`) lo describe. | revisión + verificación manual de E3 |
| E-51 | `.github/workflows/deploy.yml` | Modify | Dos pasos `docker/build-push-action` con `target: app` y `target: ssr`, `build-args: GIT_SHA=${{ github.event.workflow_run.head_sha \|\| github.sha }}` en ambos (hoy `deploy.yml:37-45` no pasa `build-args`, así que `GIT_SHA` valdría `unknown`), etiquetas `humetria:sha-…`/`latest` y `humetria-ssr:sha-…`/`latest`, y caché con `scope` distinto (`cache-from: type=gha,scope=app` / `scope=ssr`; `cache-to` igual). Después del webhook: paso `node scripts/synthetic/check-landing.mjs --expect-revision <sha> --wait 600` (reintenta cada 15 s hasta 600 s). | el workflow en `workflow_dispatch` de prueba |
| E-52 | `.github/workflows/synthetic.yml` | Create | `schedule: cron '*/15 * * * *'` y `workflow_dispatch`; corre `check-landing.mjs` contra `https://humetria.com.mx/`. Si falla, GitHub avisa por correo (el patrón de `mutation.yml`). | `workflow_dispatch` |
| E-53 | `scripts/synthetic/check-landing.mjs`, `tests/Ssr/fixtures/landing-expected.json` | Create | `fetch` de `/`, parser HTML sin JavaScript; afirma: 200; `X-Rendered-By: ssr`; un `<h1>` con el texto esperado; los nueve nombres en nodos de contenido; `canonical`, `og:*` y JSON-LD; `build-revision` = `app-revision` (= `--expect-revision` si se da); `X-Robots-Tag` ausente; sin `X-Powered-By`. La lista esperada (h1 y nueve nombres) es `landing-expected.json`, la misma que valida `nine-tools.test.mjs` contra el render real. Imprime el TTFB y el tamaño. | `tests/Ssr/node/synthetic.test.mjs` (el script contra `BASE_URL`: pasa; con `X-Rendered-By: csr` simulado: falla) |
| E-54 | `.github/workflows/tests.yml`; `scripts/ssr/test.sh` | Modify | El job `ssr` ahora también corre `docker build --target ssr`, arranca el contenedor con las mismas restricciones de compose, espera `healthy` y repite `render.test.mjs` y `synthetic.test.mjs` contra él. | el job |
| E-55 | `tests/Ssr/node/page-keys.test.mjs` | Create | `scripts/ssr/fixtures/landing-page.json` tiene las llaves de `tests/Ssr/fixtures/page-keys.json`. | falla si el fixture envejece |

### 12.7 Pieza 7 (P1). Consentimiento, GA4 y conversión

*   **Problema:** no hay medición; `generate_lead` no tiene señal del servidor. **Riesgo:** datos a terceros, cookies, CSP. **Reversión:** `GOOGLE_ANALYTICS_ID` vacío. **Producción:** DebugView con un ID real antes de activar (E5).

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-57 | `config/services.php`, `.env.example`, `.env.production.example` | Modify | `google_analytics.measurement_id` ← `GOOGLE_ANALYTICS_ID` (vacío) y `google_analytics.consent_default` ← `GOOGLE_ANALYTICS_CONSENT_DEFAULT` (`denied`); comentario: "No poner el ID hasta publicar el aviso y la verificación legal." | `EnvExampleTest` |
| E-58 | `app/Http/Consent.php`, `tests/Feature/ConsentTest.php` | Create | `final`; `analyticsAllowed(Request): bool`: verdadero si `consent_default` es `granted`. Un hueco para una decisión del visitante (cookie `humetria_consent`) **no** se implementa: la clase solo lee la configuración. `mutates(Consent::class)`. | `ConsentTest` |
| E-59 | `app/Http/Analytics.php`, `tests/Feature/AnalyticsTest.php` | Create | `measurementId(Request): ?string` (ruta `home`, ID con la forma `G-` + mayúsculas o dígitos, sin sesión y `Consent::analyticsAllowed()`); `referrer(Request): ?string`. `mutates(Analytics::class)`. | `AnalyticsTest` |
| E-60 | `resources/views/partials/analytics.blade.php`; `resources/views/app.blade.php` | Create/Modify | El HTML de v1 (cargador `async` con nonce; bloque de configuración con `Js::from`) con `gtag('consent','default', …)` antes de `config`; parámetros `allow_google_signals` y `allow_ad_personalization_signals` en `false`; `page_referrer` solo si hay. | `AnalyticsTest` |
| E-61 | `app/Http/Middleware/SecurityHeaders.php`; `tests/Feature/SecurityHeadersTest.php` | Modify | `policy(string $nonce, string $devServer, bool $maps, bool $horizon, bool $analytics)` (privada, `:105`). Con `$analytics` verdadero agrega **al final** de cada directiva: `script-src` → `https://*.googletagmanager.com`; `img-src` → `https://*.google-analytics.com https://*.googletagmanager.com`; `connect-src` → `https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com`. Es la lista inicial de la guía de CSP de Google; **sin verificar** hasta E5. `cspFor()` (`tests/Feature/SecurityHeadersTest.php:13`) gana `bool $analytics = false` y agrega los mismos orígenes en el mismo orden. | caso nuevo con `analytics: true` |
| E-62 | `app/Modules/Demos/Actions/SubmitDemoRequest.php`, `Http/Controllers/DemoRequestController.php`, `tests/Feature/Demos/DemoRequestTest.php`, `resources/js/types/global.d.ts` | Modify | `handle()` devuelve el `DemoRequest` guardado; el controlador (`:15-26`) hace `Inertia::flash('lead', ['id' => $demoRequest->id])` **solo** si no es el honeypot (`isFromBot()`); `flashDataType` gana `lead?: { id: string }` (`global.d.ts:41`). El `id` es un UUID v4 (`HasVersion4Uuids`, `DemoRequest.php:15`). | casos nuevos: con éxito, el flash `lead` existe; con el honeypot y con errores de validación, no |
| E-63 | `resources/js/lib/analytics.ts`, `tests/js/lib/analytics.test.ts`, `types/global.d.ts`, `components/landing/demo-request-form.tsx` | Create/Modify | `trackLead(target: Pick<Window, 'gtag'>, storage: Pick<Storage, 'getItem' \| 'setItem'>, id: string \| undefined)`: no hace nada sin `gtag` ni sin `id`; si `storage` ya tiene ese `id` no repite; si no, llama `gtag('event','generate_lead')` y guarda el `id`. `onSuccess={(page) => trackLead(window, sessionStorage, page.flash.lead?.id)}` en una línea (el formulario usa 98 de 100 líneas). `Window` gana `gtag?` y `dataLayer?`. | `analytics.test.ts` (con `gtag`, sin `gtag`, sin `id`, `id` repetido) |
| E-64 | `components/landing/landing-header.tsx:57`, `landing-footer.tsx:45`, `components/tenant-switcher.tsx:66`, `components/error-page-action.tsx:23`, `layouts/auth/auth-split-layout.tsx:15`, `pages/error.tsx:47`; `tests/Unit/PlainLinksTest.php` | Modify | "Iniciar sesión" y todo enlace cuyo destino es `home` pasan de `<Link>` a `<a href={...().url}>` (el logo de `landing-header.tsx:34-35` se queda: mismo documento). Se añade a `PlainLinksTest` que `landing-header.tsx` y `landing-footer.tsx` no usan `<Link href={dashboard`. | `PlainLinksTest` (hoy 6 infractores) |
| E-65 | `tests/Feature/AnalyticsTest.php`, `tests/Browser/LandingTest.php`, `tests/Feature/Complaints/PublicPageTest.php` | Modify | `AnalyticsTest`: tabla de cuatro casos (sin ID, con ID, otra ruta, sesión) con script y CSP coincidentes; sin consentimiento no hay script ni orígenes; el `Referer` del propio sitio (con token, o variante `www`) llega solo como origen; una petición a `/c/*` con la cabecera `Cookie: _ga=sentinel` enviada en crudo no deja `sentinel` en ninguna línea de log (la prueba de v1 con una cookie de Laravel pasaba sin probar nada: `bootstrap/app.php:40` cifra las cookies). Navegador: "Iniciar sesión" carga un documento sin `window.dataLayer`; con `window.gtag` simulado, enviar el formulario con datos válidos registra una llamada con `generate_lead`, y enviarlo de nuevo con el mismo `id` no la repite. | los nuevos |

### 12.8 Pieza 8 (P1). Procedencia y atribución

*   **Problema:** la solicitud de demo no guarda de dónde llegó (`app/Modules/Demos/Data/DemoRequestData.php`) y la sesión de 120 minutos (`config/session.php:37`) no cubre un ciclo B2B. **Riesgo:** datos personales nuevos; se declaran en el aviso. **Reversión:** la migración tiene `down()` y solo agrega columnas anulables. **Producción:** una solicitud de prueba con `?utm_source=…` y su lectura en la consola. **Alcance real hoy:** con `consent_default=denied` (decisión Q5) la cookie de 90 días **no se escribe**; solo funciona la procedencia de sesión (sección 14).

| Entrada | Archivo | Acción | Cambio | Prueba que falla antes |
| :-- | :-- | :-- | :-- | :-- |
| E-66 | `app/Http/SiteHost.php`, `tests/Feature/SiteHostTest.php` | Create | `owns(?string): bool` y `origin(): string`, con los casos de v1 (propio, `www`, mayúsculas, puerto; ajeno, subdominio distinto, `otrohumetria.com.mx`, `null`, vacío, no URL). `mutates(SiteHost::class)`. | `SiteHostTest` |
| E-67 | `database/migrations/<la última + 1>_add_attribution_to_demo_requests_table.php` | Create | Ocho columnas anulables (`utm_source`, `utm_medium`, `utm_campaign`, `referrer_host`, y las cuatro `first_*`), con `CHECK` de no vacío `demo_requests_<columna>_filled` (`btrim(<columna>) <> ''`); `string(100)` y `string(255)`. La última migración existente se confirma al implementar (v1 dijo `2027_01_16_100000`; **por reconfirmar**). | `DemoRequestStorageTest` |
| E-68 | `app/Modules/Demos/Data/DemoAttribution.php`, `app/Modules/Demos/Data/DemoRequestData.php` | Create/Modify | DTO `final readonly` con `first` y `last` (cada uno con cuatro campos anulables); `DemoRequestData` gana `?DemoAttribution $attribution = null` al final. | `DemoAttributionTest` |
| E-69 | `app/Modules/Demos/Http/Middleware/RememberDemoAttribution.php` (el directorio `Http/Middleware` se crea; hoy solo hay `Controllers` y `Requests`), `routes/web.php` | Create/Modify | Solo GET en `home`. **Sesión (siempre):** guarda en `demo_attribution` la procedencia de la primera visita con señal. **Cookie `humetria_acq` (90 días, cifrada, `HttpOnly`, `Secure`, `SameSite=Lax`) solo si `Consent::analyticsAllowed()`:** `{first, last, first_seen_at}`; `first` se escribe una vez; `last` se sobrescribe solo si la visita trae señal. Reglas de saneamiento de v1 (UTM de una línea de 1 a 100 caracteres, host de 1 a 255 que no sea del sitio). No hay excepción en `encryptCookies` (`bootstrap/app.php:40`). | `DemoAttributionTest` |
| E-70 | `app/Modules/Demos/Http/Requests/StoreDemoRequestRequest.php`, `app/Modules/Demos/Actions/SubmitDemoRequest.php`, `app/Modules/Demos/Models/DemoRequest.php`, `app/Modules/Demos/Http/Controllers/DemoRequestConsoleController.php`, `database/factories/Demos/DemoRequestFactory.php` | Modify | `toData()` arma el DTO desde la cookie (si existe) o la sesión; `handle()` guarda las columnas; el modelo gana las columnas en `#[Fillable]` (`DemoRequest.php:39`) y `arrivedBy()` y `firstContact()` con la tabla de v1; la fila de la consola gana `arrived_by` y `first_contact`; estados de la factory `fromCampaign()` y `referredBy()`. El contexto del log `demo_request.submitted` no cambia. | `DemoAttributionTest`, `DemoRequestConsoleTest` |
| E-71 | `resources/js/types/demos.ts`, `components/demos/demo-request-detail.tsx` | Modify | Filas "Llegó por" y "Primer contacto" (si difiere); "Sin dato" no significa tráfico directo. | `tests/Browser/DemoConsoleTest.php` |
| E-72 | `tests/Feature/Demos/DemoAttributionTest.php` (nuevo, `mutates(RememberDemoAttribution::class)`), `DemoRequestStorageTest.php` (existe: Modify), `DemoRequestConsoleTest.php:38-74` (existe: Modify, la fila gana `'arrived_by' => null` y `'first_contact' => null`; **autorizado**, sección 18; agrega `mutates(DemoRequest::class)`), `tests/Browser/DemoConsoleTest.php` (Modify, aserciones nuevas) | Create/Modify | Bordes de v1 (100/101 caracteres, control, vacío, espacios, arreglo, host 255/256, referente del sitio y `www`, HEAD, valor de sesión mal formado) más: sin consentimiento no hay cookie y funciona la sesión; con consentimiento, la cookie vive 90 días, `first` no cambia y `last` sí con señal; ninguna línea de log contiene un valor de procedencia. | los nuevos |

### 12.9 Pieza 9 (P0 reglas, P1 manual). Reglas y manual de operación

| Entrada | Archivo | Acción | Cambio | Prueba |
| :-- | :-- | :-- | :-- | :-- |
| E-73 | `CLAUDE.md` | Modify | Cuatro viñetas al final de "Seguridad", en este PR (decisión Q-reglas): "Toda respuesta lleva `X-Robots-Tag: noindex, nofollow`, salvo las rutas de `App\Http\IndexablePages` en producción; para ser indexable se agrega ahí." / "Solo `App\Http\SsrPages` pasa por Node, solo para visitantes sin sesión; una ruta nueva no activa SSR por omisión." / "Google Analytics carga solo en el documento de la landing (`App\Http\Analytics`) y solo con consentimiento (`App\Http\Consent`)." / "Todo enlace que sale de la landing o llega a ella es un `<a href>` normal, no un `<Link>`." | `PlainLinksTest`, `SsrPagesTest`, `IndexablePagesTest` ya las hacen cumplir |
| E-74 | `docs/operations/ssr.md` | Create | Manual: variables, despliegue, `HUMETRIA_TAG`, rollback, interruptor `INERTIA_SSR_ENABLED`, qué hacer ante `ssr.render_failed`, cómo leer `X-Rendered-By`, **lista de verificaciones manuales** de infraestructura (13 E). Es la documentación que pide el handoff 13. | revisión |

### 12.10 Pruebas de infraestructura que no corren en CI (verificación manual)

Docker Compose no está en el CI del repositorio y no se agrega. Estos casos se ejecutan **a mano en Dokploy**, una vez antes del primer lanzamiento y después de cada cambio de `compose.production.yaml`, con la lista de `docs/operations/ssr.md`: reinicio del contenedor `ssr` (unos segundos de CSR y vuelta a SSR, `X-Rendered-By` lo muestra), despliegue de una versión nueva, rollback con `HUMETRIA_TAG`, bundle ausente (arrancar el contenedor con la imagen `app` por error: `ssr` no arranca y `web` sigue en CSR), red interna (`web` alcanza `ssr`; `ssr` no sale a internet; Traefik no enruta a `ssr`: E3). El resto se prueba en CI (`SsrDegradationTest` con Node falso, y el job `ssr` con Node real).

### 12.11 Archivos que no cambian aunque lo parezca

`resources/js/routes/**` (lo genera Wayfinder); `tests/Feature/LandingTest.php` y `tests/Feature/Demos/DemoRequestTest.php` (siguen pasando; E-62 solo agrega casos); las cuatro respuestas que ya ponen `X-Robots-Tag` (se pueden retirar en un PR de limpieza sin tocar sus pruebas).

### 12.12 P2 (no bloquea el lanzamiento comercial ni la seguridad)

Páginas por herramienta o norma (`IndexablePages` y `SsrPages` ya las admiten); caché de la última respuesta SSR (D4); medición de servidor con Measurement Protocol; banner y Consent Mode con actualización; separar dominios (9.2); métricas de experiencia real (LCP, INP, CLS) con Search Console o CrUX; reconstrucción semanal y escaneo de imágenes; `llms.txt`. Ninguno es requisito de los doce objetivos.

--------------------------------------------------------------------------------

## 13. Matriz de pruebas

Nivel: **U** unitaria (Vitest, o `tests/Unit` en PHP), **F** Feature (Pest), **N** navegador con la app normal (suite `Browser`, SSR apagado), **S** navegador o Node con el servidor SSR real (suite `Ssr` y `tests/Ssr/node`, job `ssr`), **M** manual, **P** producción. "Job" es el job de `tests.yml` donde corre; los de la suite `Browser` siguen en `browser`, los de `tests/Feature` y `tests/Unit` en `tests`. Toda clase de PHP con reglas declara `mutates()` (`IndexablePages`, `SsrPages`, `PageSeo`, `Consent`, `Analytics`, `SiteHost`, `RememberDemoAttribution`, `DemoRequest`, `LogSsrRenderFailure`).

### A. HTML inicial

| ID | Prueba | Nivel | Archivo / job | Resultado |
| :-- | :-- | :-- | :-- | :-- |
| A1 | El render contiene `<h1>`, `<h2>` y los nombres y descripciones de las nueve herramientas **en nodos del cuerpo** (se descartan coincidencias dentro de `script`, `data-page`, `meta` y `noscript`) | S | `tests/Ssr/node/nine-tools.test.mjs`, job `ssr` | Pasa |
| A2 | Lo mismo, con el HTML que PHP imprime a partir de una respuesta SSR simulada (la forma del documento: `body` sin duplicar el `<script data-page>`) | F | `tests/Feature/SsrDocumentTest.php`, job `tests`; se analiza con `DOMDocument` y XPath (`Dom\HTMLDocument` exige PHP 8.4 y `composer.json:8` pide `^8.3`) | Pasa |
| A3 | Enlaces rastreables presentes (`href` a `/privacy-notice`, inicio de sesión, anclas de sección) | S | `nine-tools.test.mjs` | Pasa |
| A4 | Metadatos completos y JSON-LD válido (`json_decode`, tipos `Organization` y `WebSite`, sin `<`) | F | `PageMetaTest`, `PageSeoTest` | Pasa |
| A5 | No existe contenido comercial duplicado: sin `<noscript>` en `resources/views` y sin el texto del `<h1>` fuera de `resources/js` | U | `tests/Unit/NoDuplicatedLandingTextTest.php`, job `tests` | Pasa (evidencia del objetivo 2, automática) |

### B. Sin JavaScript

| ID | Prueba | Nivel | Archivo / job | Resultado |
| :-- | :-- | :-- | :-- | :-- |
| B1 | `playwright` con `javaScriptEnabled: false` contra `BASE_URL`: `<h1>` visible (`opacity` calculada 1), nueve nombres legibles, enlaces presentes | S | `tests/Ssr/node/nojs.test.mjs` (Pest Browser 5.1.2 no tiene la opción: no se encontró en `vendor/pestphp/pest-plugin-browser/src`; `playwright` ya está en `package.json` y se usa directo) | Pasa |
| B2 | Un solo `<h1>`, `<title>` y `<main>` | S | `nojs.test.mjs` | Pasa |
| B3 | Revisión visual de una captura sin JavaScript, una vez por cambio de diseño | M | `docs/operations/ssr.md` | Revisada |

### C. Hidratación

| ID | Prueba | Nivel | Archivo / job | Resultado |
| :-- | :-- | :-- | :-- | :-- |
| C1 | `visitPage('landing', '/')` con SSR real: sin errores de JS, `cspViolations()` vacío, axe | S | `tests/Ssr/LandingSsrTest.php` | `[]` |
| C2 | Sin errores de hidratación en la consola | S | `LandingSsrTest.php` con `assertNoConsoleLogs()` (`vendor/pestphp/pest-plugin-browser/src`, `MakesConsoleAssertions.php:61`; la sección 17 decía que la consola no se podía leer: era incorrecto) | Ninguno |
| C3 | El formulario conserva lo escrito aunque la hidratación llegue tarde | S | `tests/Ssr/node/form-before-hydration.test.mjs` (Pest no intercepta la red) | Se conserva |
| C4 | Claro y oscuro, escritorio y 375 px | S | `LandingSsrTest.php` (4 casos) | Mismos resultados; oscuro hoy falla con el error 418 |
| C5 | Un solo `<title>`, descripción, canonical y JSON-LD tras hidratar | S y N | `LandingSsrTest.php`; `LandingTest.php` (SSR apagado) | 1 de cada uno |

### D. Seguridad

| ID | Prueba | Nivel | Archivo / job | Resultado |
| :-- | :-- | :-- | :-- | :-- |
| D1 | Conjunto exacto de llaves de la página que recibe Node | F | `SsrPropsTest` + `tests/Ssr/fixtures/page-keys.json` | Igual al golden |
| D2 | Tabla sobre todas las rutas GET: cada parámetro se llena con `'x'`; `Http::recorded()` cuenta peticiones a `/render`; solo `home` y `privacy-notice` generan una | F | `SsrPagesTest` | 2 rutas con 1 petición, el resto 0 |
| D3 | Con sesión (usuario y superadmin suplantando), `GET /` no hace petición; igual en la tabla de D2 repetida con sesión | F | `SsrPagesTest` | 0 |
| D4 | Una página de error servida en `/` (500) no hace petición | F | `SsrPagesTest` | 0 |
| D5 | Contaminación: 50 renders concurrentes con marcas distintas | S | `concurrency.test.mjs` | Sin cruces |
| D6 | CSP de `/` idéntica a la de hoy con SSR encendido; sin `unsafe-inline` ni `unsafe-eval` ni `style-src-attr` | F | `SecurityHeadersTest` | Idéntica |
| D7 | `X-Robots-Tag` según la lista (casos de la pieza 1) | F | `SecurityHeadersTest` | Como en E-4 |
| D8 | Ruta nueva segura por omisión: una ruta pública agregada en la prueba no está en `IndexablePages` ni `SsrPages`, lleva `noindex` y no hace petición | F | `SsrPagesTest`, `IndexablePagesTest` | Segura |
| D9 | Las URLs con token no llegan a Analytics: el HTML de esas páginas no tiene el script ni `window.dataLayer` | F | `AnalyticsTest` | Sin script |
| D10 | Ningún `<Link>` a `home` ni a `dashboard` fuera del logo de la landing | U | `PlainLinksTest` | Cumple |

### E. Infraestructura

Las pruebas que se pueden automatizar corren en CI; el resto es verificación manual (12.10).

| Escenario | Resultado esperado | Verificación observable | Dónde |
| :-- | :-- | :-- | :-- |
| SSR activo | 200, `X-Rendered-By: ssr`, `<h1>` en el HTML | cabecera y cuerpo | F (simulado) y S |
| SSR detenido (conexión rechazada) | 200, `csr`, etiquetas del slot | cabecera; `og:*` presentes; una línea `ssr.render_failed` tipo `connection`; el cortacircuitos se abre y la siguiente petición no intenta Node | F (`SsrDegradationTest`) |
| SSR lento (responde tras `timeout`) | Igual, en ≈ 2 s | duración de la petición | F (`Http::fake` con retraso) |
| Cortacircuitos: tras 30 s vuelve a intentar | Una petición a Node | `Http::recorded()` | F con `Carbon::setTestNow` |
| Bundle roto (lanza al importar) | El supervisor sale con código ≠ 0 | código de salida | S (`supervisor.test.mjs`) |
| Render colgado (bucle infinito) | El supervisor sale tras tres sondeos fallidos | código de salida y tiempo | S (`supervisor.test.mjs`) |
| Imagen `ssr` real: sana, usuario `node`, sin red de salida | `healthy`; `id -u` ≠ 0 | `docker inspect` | S (job `ssr`, E-54) |
| Una sola línea de log por tipo y minuto con 100 visitas fallidas | Sin inundación | conteo de líneas | F |
| Reinicio del contenedor `ssr` | Unos segundos de CSR y vuelta a SSR sin intervención | serie de peticiones con `X-Rendered-By` | M |
| Despliegue de versión nueva | `build-revision` = `app-revision` = commit | `check-landing.mjs` | P (deploy.yml) |
| Rollback con `HUMETRIA_TAG` | Las dos imágenes vuelven juntas | meta de revisión del commit anterior | M |
| Bundle ausente en producción (imagen equivocada) | `ssr` no arranca; `web` sigue en CSR | estado de `ssr`; sin 5xx en `web` | M |
| Red interna (`web` alcanza `ssr`; `ssr` no sale; Traefik no enruta a `ssr`) | Como se dice | `docker exec` y `curl` | M (E3) |
| Fallo de comunicación Laravel–Node | CSR + señal | `ssr.render_failed` | F |

### F. SEO, previews y producción

`scripts/synthetic/check-landing.mjs` corre después de cada despliegue (`deploy.yml`) y cada 15 minutos (`synthetic.yml`); los pasos manuales, una vez por lanzamiento.

| ID | Comprobación | Quién |
| :-- | :-- | :-- |
| F1 | `curl -s https://humetria.com.mx/`: `<h1>`, nueve nombres en nodos del cuerpo, canonical, `og:*`, JSON-LD (la lista de nombres sale de `landing-expected.json`, que el banco compara con el render real) | script sintético |
| F2 | `X-Rendered-By: ssr`; `X-Robots-Tag` ausente en `/` y presente en `/admin/login` | script |
| F3 | `build-revision` = `app-revision` = commit desplegado | script |
| F4 | `/sitemap.xml`, `robots.txt`, redirección de `www` (`curl -sI` con 301 que conserva ruta y query) y certificados | script y `curl` |
| F5 | Vista previa en el depurador de LinkedIn, el de Meta y WhatsApp | manual |
| F6 | Un extractor que no ejecuta JavaScript (por ejemplo `lynx -dump`) lee título y descripción | manual |
| F7 | Search Console: URL inspeccionada, página indexable y sitemap enviado. Es la única comprobación de que Google indexa; no se infiere de lo anterior | manual |

### G. Rendimiento (presupuesto inicial, separando servidor y experiencia real)

| Métrica | Presupuesto | Medido el 2026-11-14 (sección 17) | Dónde se mide |
| :-- | :-- | :-- | :-- |
| Render SSR | p95 ≤ 150 ms | 4 a 5 ms en caliente | `tests/Ssr/node/budget.test.mjs` (CI) y logs `ssr.rendered` (`duration_ms`) |
| TTFB de `/` | p95 ≤ 600 ms | sin medir | `check-landing.mjs` lo imprime (`curl -w`) |
| Memoria de Node | ≤ 200 MB en reposo (límite 384 MB) | 98 → 115 MB tras 20 renders | `docker stats` (M) |
| Tamaño del HTML | ≤ 250 KB sin comprimir | 123 KB | `budget.test.mjs` |
| Bundles de cliente | sin aumento | sin medir | el build |
| LCP / INP / CLS | umbrales "buenos" públicos de Google (2,5 s / 200 ms / 0,1) | sin medir | datos reales (Search Console o CrUX) tras el lanzamiento: P2 |

### H. Observabilidad (handoff 13)

| Qué | Cómo | Dónde |
| :-- | :-- | :-- |
| Solicitudes y renders exitosos, latencia | línea `ssr.rendered` con `status` y `duration_ms` | E-19 |
| Errores de renderizado, timeouts | `ssr.render_failed` por tipo, una por minuto, más contador | E-18 |
| Estado del servicio y reinicios | healthcheck del contenedor; `docker ps` y la pestaña de logs de Dokploy | M |
| Uso de memoria | `docker stats` | M |
| Versión del bundle desplegado | meta `build-revision` | E-28, F3 |
| Disponibilidad del contenido SEO | la comprobación sintética cada 15 minutos | E-52 |
| Fallos de hidratación detectables | solo en pruebas (`assertNoConsoleLogs`); en producción **no** se miden: medirlos exigiría enviar errores del navegador a un servicio | P2 |
| Alertas | correo de GitHub cuando falla el flujo sintético; no hay otra alerta en el repositorio (H19) | E-52 |

No se registran props completas, cookies ni tokens: `ssr.render_failed` y `ssr.rendered` llevan solo tipo, componente, path, estado y duración.

--------------------------------------------------------------------------------

## 14. Criterios de aceptación (resultados observables)

Las diez preguntas del handoff 17.

| # | Pregunta | Respuesta | Evidencia |
| :-- | :-- | :-- | :-- |
| 1 | **Renderizado.** ¿Un lector automatizado entiende qué hace Humetría sin ejecutar JavaScript? | Sí: el HTML trae el `<h1>`, los `<h2>`, la propuesta de valor y las nueve herramientas en nodos de contenido. | A1, A2, B1, F1, F6 |
| 2 | **Duplicación.** ¿El contenido tiene una sola fuente? | Sí: el texto vive en los componentes React; las etiquetas, en la propiedad `seo` (PHP). No existe `<noscript>` ni parcial de texto. Hay dos renderizadores de `seo` con un golden común. | A5, E-33 |
| 3 | **Experiencia.** ¿Se mantienen las funciones y se hidrata sin errores? | Sí: el estado medido hoy (53 violaciones de CSP, error 418 en oscuro) se corrige con C1, `WhenMounted` y `useSyncExternalStore`. Los casos de movimiento reducido existentes (`LandingTest.php:220`, `:317`, `:522`) no cambian. | C1 a C5 y la suite `Browser` existente |
| 4 | **Seguridad.** ¿Los componentes privados y los datos sensibles quedan fuera de SSR? | Solo las dos páginas autorizadas, solo anónimos, con conjunto de props fijo; el bundle contiene páginas privadas pero corre sin `.env`, sin base y sin salida a internet. | D1 a D4, D8 |
| 5 | **CSP.** ¿Se ejecuta sin debilitarla? | Sí: C1 deja cero `style` en el HTML y la política es la de hoy. | D6, C1 |
| 6 | **Operación.** ¿Qué pasa si Node se detiene durante una visita o un despliegue? | La visita recibe 200 con las etiquetas del slot y React la pinta; el cortacircuitos evita pagar 2 s en cada visita; el supervisor reinicia Node; el correo de GitHub avisa en ≤ 15 minutos. | E (F y S), `check-landing.mjs` |
| 7 | **Despliegue.** ¿Cómo se garantiza que Laravel y Node usan el mismo bundle? | Un `docker build` por commit con `GIT_SHA` en ambos objetivos, etiquetas emparejadas por `HUMETRIA_TAG`, y `build-revision` = `app-revision` verificada después de cada despliegue. Durante la sustitución hay una ventana de segundos con versiones mezcladas: React se recupera y la comprobación sintética la ve. | F3 |
| 8 | **Indexabilidad.** ¿Qué impide que una ruta nueva o una respuesta errónea sea indexable? | `noindex` por defecto, lista cerrada, solo con 200 y en producción. | D7, D8 |
| 9 | **Marketing.** ¿GA y Ads miden sin exponer información privada? | **Con la decisión actual (`consent_default=denied`) no hay medición**: `Analytics::measurementId()` devuelve `null`, no se carga el script ni se abren orígenes. Cuando el abogado del dueño autorice, se pone `granted` y el ID, y se verifica E5. | D9, `AnalyticsTest`, `ConsentTest` |
| 10 | **Verificación.** ¿Cómo sabemos desde fuera del contenedor que el HTML de producción es el esperado? | `check-landing.mjs` en GitHub Actions, después de cada despliegue y cada 15 minutos. | `synthetic.yml` verde |

**Atribución (objetivo 10 del handoff) con la decisión actual.** Con `consent_default=denied` **la cookie `humetria_acq` de 90 días no se escribe**. La atribución queda limitada a la sesión de Laravel (120 minutos de inactividad, `config/session.php:37`) hasta que el abogado del dueño decida el valor de `consent_default`. Las columnas, la lógica de primer y último toque y la consola se entregan y se prueban con el consentimiento activado en las pruebas, pero en producción solo hay datos de sesión. Esto es un límite aceptado y declarado, no un defecto: quien llega por un anuncio el lunes y pide la demo el jueves aparecerá como "Sin dato" hasta entonces; y "Sin dato" no significa tráfico directo.

Criterios generales: `composer ci:check` en verde y el job `ssr` en verde (en `ci`); ninguna prueba aflojada; solo cambian las autorizadas por el dueño (sección 18).

--------------------------------------------------------------------------------

## 15. Preguntas abiertas (requieren al dueño)

Las decisiones de la sección 18 (Q1 a Q11 y Q-reglas) están aplicadas en este documento como hechos. **Sigue abierta una sola pregunta:**

| # | Pregunta | Opciones y costo |
| :-- | :-- | :-- |
| **Q12** | **`APP_URL` en Dokploy y mecanismo de la redirección de `www`.** | El dueño confirma que `APP_URL` es `https://humetria.com.mx` (el identificador de las llaves de acceso sale de su host: `config/fortify.php:146-148`; con `www` la redirección invalidaría las llaves). El autor decide entre la pestaña Domains y etiquetas de Traefik tras E3. Bloquea solo la redirección de `www`, tarea de despliegue fuera del código. |

**Aserciones existentes que el diseño de los nueve paneles cambia fuera de la autorización:** ninguna. La autorización del dueño cubre `tests/Browser/LandingTest.php:251-257` y `:309`; el diseño de 5.5 deja el resto igual y la lista exacta está allí. Si al implementar apareciera otra aserción afectada, se detiene el trabajo y se pregunta con su línea.

**Decisiones cerradas por referencia (Q7 a Q10, sección 18).** Se aplican así: Q7, la conversión `generate_lead` se marca como evento clave y su importación a Ads espera a tener medición y calidad de lead (9.4); Q8, marketing, encuestas y buzones siguen en el mismo dominio con las salvaguardas de 9.2 (separar dominios es P2); Q9, no se agrega llave de idempotencia al formulario (9.4); Q10, las pruebas existentes que cambian son `LandingTest.php:309` (más aserciones nuevas, 5.5) y `DemoRequestConsoleTest.php:38-74` (`arrived_by` y `first_contact`, 10.5).

### Verificaciones que quedan por hacer (no son decisiones)

| # | Qué decide | Pasos |
| :-- | :-- | :-- |
| **E3** | Red interna de Dokploy; redirección de `www` | En un compose de prueba en Dokploy: dos servicios y una red `internal: true`; comprobar que `web` alcanza `ssr`, que `ssr` no sale a internet y que Traefik no lo enruta; buscar la redirección en Domains y, si no está, probar las etiquetas. |
| **E4** | Vigilante dentro del contenedor | Con la imagen real: inyectar un bucle infinito en `/render` y comprobar que el supervisor reinicia el contenedor en ≤ 100 s; medir memoria con 1 000 renders. La memoria y el tiempo de render ya están medidos en la sección 17 (115 MB; 4 a 5 ms). |
| **E5** | Orígenes de la CSP de Google; `page_referrer`; deduplicación de `generate_lead` | Con un ID real en local y `consent_default=granted`: abrir la landing y recopilar violaciones de CSP; llegar desde una URL con token del sitio y mirar el parámetro `dr` de `collect`; enviar la solicitud dos veces y ver en DebugView si se cuenta una o dos; solo entonces cambiar `consent_default` en producción. |

### Lo que no se pudo verificar

*   El comportamiento de React ante atributos distintos en hidratación **en producción** (la sección 17 midió el error 418 en oscuro; la causa exacta no se aisló).
*   Que `Http::allowStrayRequests(['127.0.0.1:13714/*'])` baste para la suite `Ssr` (la firma existe: `Factory.php:451`); que Pest Browser use la misma instancia de la aplicación, de modo que `config([...])` de la prueba aplique al servidor (**sin verificar**; si no, las variables de entorno de `scripts/ssr/test.sh` lo hacen).
*   Que el `ReportingObserver` de `cspViolations()` vea las violaciones del HTML SSR igual que las del cliente (la sección 17 las midió en 53).
*   Si Dokploy respeta `internal: true`, no renombra la red y ofrece la redirección de `www` (E3).
*   Los orígenes de Google, `page_referrer` y la deduplicación de `generate_lead` (E5).
*   La ley mexicana aplicable a cookies de analítica y atribución (a cargo del abogado del dueño).
*   Las aproximaciones en CSS de los resortes de `how-it-works-step.tsx` (`bounce: 0.3`): se confirman al ojo contra el rediseño aprobado.
*   Referencias heredadas de v1 que no se reabrieron: `tests/Feature/LocalizationTest.php:87`, `tests/Feature/Tenancy/AcceptInvitationTest.php:293`, las cuatro respuestas con `X-Robots-Tag` a mano, `resources/js/pages/complaints-public/form.tsx:6`, `resources/js/components/disclosure/public-complaint-card.tsx:5` y la última migración (`2027_01_16_100000`); cada una se reconfirma antes de tocarla.

--------------------------------------------------------------------------------

## 16. Diferencias contra el plan anterior

### 16.1 Qué cambia

| Tema | v1 | v2 | Por qué |
| :-- | :-- | :-- | :-- |
| Contenido para quien no ejecuta JavaScript | `<noscript>` escrito en Blade, con prueba que compara cadenas | SSR real de Inertia con los mismos componentes | Decisión del dueño; una sola fuente |
| Etiquetas del `<head>` | Blade, fuera de `<x-inertia::head>` | `<Head>` con SSR, fuente única `seo`; el slot de Blade como respaldo con las mismas llaves | Preferencia del handoff; el slot es el mecanismo oficial y cubre la caída de Node |
| SSR | "Mejora futura"; `ssr.enabled => false` | Alcance central; política por listas | Objetivo 1 |
| Datos a Node | No se evaluaba | Solo anónimos, conjunto exacto de props, sin sesión | Handoff 4.3 |
| Animaciones | `motion` sin cambio | CSS (C1, decidido) para el estado inicial; sin `style` en el servidor | CSP y legibilidad; 53 violaciones medidas |
| Herramientas | Un panel (límite aceptado) | Nueve paneles ARIA (autorizado, sección 18) | Handoff 5 |
| Fallos de SSR | Una línea de log | Señales, cabecera, vigilante, comprobación externa | Handoff 8.5 |
| Atribución | Primer toque en sesión de 120 min | Primer y último toque en cookie de 90 días solo con consentimiento (hoy `denied`: solo sesión); sin IDs de clic | Handoff 11 |
| Consentimiento | Fuera de alcance | `Consent` y `consent_default=denied` por omisión | Handoff 10.3 |
| CI | Fuera de alcance | Job `ssr`, despliegue de dos imágenes y comprobación sintética, porque la feature los necesita | Handoff 12 E y F |
| Aviso de privacidad | Sin SSR (texto con JavaScript) | Con SSR | Autorizado por el handoff |

### 16.2 Qué se mantiene

La lista `IndexablePages` y `noindex` por defecto (solo con 200 y en producción); sitemap estático y `robots.txt` sin `Disallow` con ruta; aviso de privacidad en `/privacy-notice` (ruta en inglés); `www` → sin `www` en Traefik; GA4 con `gtag.js` directo, solo en la landing y para visitantes sin sesión; la procedencia de la solicitud de demo con las mismas reglas de saneamiento de v1 (se amplía, no se reescribe); no usar Tag Manager; el límite de 100 líneas por función y el resto de `CLAUDE.md`.

### 16.3 Non-Goals (revisados)

*   Páginas por herramienta o norma, blog y guías (P2).
*   Rediseñar la landing o cambiar su texto; sí cambia **cómo** se anima y cómo se estructuran las herramientas (Q1, Q2).
*   Píxeles de Meta y LinkedIn, Tag Manager, Google Signals.
*   Indexar el login, `/media/films/{film}` o cualquier página con token.
*   Banner de consentimiento (P2).
*   Un framework distinto de Inertia, o un renderizador propio.

(El SSR **sale** de los Non-Goals y de Future Improvements.)

### 16.4 Apéndice B. Alternativas A a P del plan v1

| Letra | Alternativa de v1 | Decisión de v1 | Clasificación v2 | Motivo |
| :-- | :-- | :-- | :-- | :-- |
| A | Etiquetas desde el `<Head>` de React con SSR | Rechazada | **Modificar** | Ahora es la vía principal; se agrega el respaldo del slot para el caso de Node caído |
| B | Renderizar el cuerpo con Node (SSR) | Rechazada | **Reemplazar** | Es la decisión principal aprobada (secciones 3 a 7); se rehacen riesgos, costos y criterios |
| C | Prerender al construir | Rechazada | **Conservar (rechazo)** | El nonce de la CSP cambia por petición y el build no tiene `.env`; sigue sin servir |
| D | Texto dentro del contenedor de la app | Rechazada | **Descartar** | Duplicaría contenido; el SSR lo resuelve |
| E | `noindex` en Blade o ruta por ruta | Rechazada | **Conservar (rechazo)** | Negar por defecto en un solo sitio |
| F | `Disallow` en `robots.txt` | Rechazada | **Conservar (rechazo)** | Impide leer el `noindex` y publica prefijos de URLs privadas |
| G | Sitemap generado | Rechazada | **Conservar (rechazo)** | Archivo estático + prueba |
| H | Aviso como Blade suelto | Rechazada | **Conservar (rechazo)** | Duplicaría marco y tokens; con SSR deja de haber motivo para usar Blade |
| I | Otro proveedor de analítica | Rechazada | **Conservar (rechazo)** | El dueño eligió GA y Ads |
| J | Google Tag Manager | Rechazada | **Conservar (rechazo)** | Contradice la CSP estricta |
| K | Analytics sin cookies | Rechazada | **Modificar** | Se mantiene la cookie, pero gobernada por el consentimiento (9.3) y con el riesgo de correlación analizado (9.2) |
| L | Guardar el referente completo | Rechazada | **Conservar (rechazo)** | Podría ser una URL con token |
| M | Capturar la procedencia en un middleware global | Rechazada | **Conservar (rechazo)** | Solo la ruta `home` |
| N | Campos ocultos en el formulario (último toque) | Rechazada | **Modificar** | Sigue rechazada como único mecanismo; el servidor lee la cookie, no el formulario |
| O | Redirección de `www` en la app | Rechazada | **Conservar (rechazo)** | En Traefik cubre archivos estáticos |
| P | Varios PR | Rechazada | **Conservar (rechazo)** | Un PR por trabajo; los commits llevan la granularidad (la CI necesaria entra aquí y la prescindible va aparte, 12.6) |
| Q (nueva) | `<noscript>` desde Blade | Aprobada en v1 | **Descartar** | Rechazada por el dueño |
| S-A / S-B (nuevas) | Node en `web` / servicio aparte | — | S-B recomendada | 7.1 |
| C1, C2, C3 (nuevas) | Cómo animar | — | C1 recomendada | 5.3 |
| D1 a D4 (nuevas) | Degradación | — | D1 recomendada | 7.6 |

*Drafted for review by the owner. Pending approval; nothing is implemented.*

--------------------------------------------------------------------------------

## 17. Resultados de los experimentos E1 y E2 (medidos el 2026-11-14)

Se corrieron en un worktree aparte, sobre el estado actual de la landing, con solo dos cambios: la precarga de `app.tsx` protegida con `typeof document` y `ssr: 'resources/js/app.tsx'` en `laravel()` de `vite.config.ts`. Ningún componente de la landing se tocó. Lo que sigue sustituye a las marcas "sin verificar" de esos puntos en el resto del documento.

### Lo que se midió

| Punto | Resultado |
| :-- | :-- |
| Build | `npm run build:ssr` termina sin errores. La entrada queda en `bootstrap/ssr/app.js`, y `BundleDetector` la encuentra sin configurar `inertia.ssr.bundle`. |
| Dependencias dentro del bundle | Con `ssr: { noExternal: true }` el build termina y el bundle solo importa módulos propios de Node (`http`, `process`, `node:cluster`, `node:fs`, `node:os`, `node:path`). El directorio pasa de 15 MB a 32 MB. `vite-plus` 1.4.0 respeta la opción. |
| Arranque | `php artisan inertia:start-ssr` arranca y `/health` responde. Ningún módulo falla al importarse en Node. |
| HTML del servidor | 123 KB. `#app` trae `data-server-rendered`. Un `<h1>`, siete `<h2>`, un solo `<title>` y una sola descripción, los dos con `data-inertia`. |
| Estilos en atributo | 53 atributos `style` dentro de `#app`; 42 llevan `opacity:0`, incluidos los diez `<span>` del `<h1>`. |
| CSP | 53 violaciones de `style-src-attr`, una por atributo, en claro y en oscuro. El navegador bloquea esos estilos. |
| Hidratación en claro | Sin errores de JavaScript, en escritorio y a 375 px. Tras hidratar, el `<h1>` tiene opacidad 1 y las pestañas cambian de panel. |
| Hidratación en oscuro | Falla siempre (tres de tres corridas) con el error 418 de React, que es un desajuste de hidratación. |
| Videos | El servidor emite tres `<video>` con `autoplay` y el póster de la variante clara. |
| Herramientas | Los nueve nombres aparecen como texto. Solo hay un `role="tabpanel"`: la descripción y los puntos de una sola herramienta. |
| Memoria y tiempo | El proceso Node pasa de 98 MB a 115 MB de memoria residente tras 20 renders. Un render tarda de 4 a 5 ms en caliente. |

### Lo que no se midió

*   La consola del navegador: no se leyó. (Corrección de la segunda ronda de revisión: Pest Browser sí la expone, con `assertNoConsoleLogs()` y `consoleLogs()`; `vendor/pestphp/pest-plugin-browser/src/.../MakesConsoleAssertions.php:61`. Los errores del experimento se detectaron con `assertNoJavaScriptErrors`; las pruebas de la pieza 5 sí leen la consola.)
*   La página con JavaScript desactivado. Es una inferencia: un lector que aplica los estilos y no aplica la CSP vería el `<h1>` y otros 41 elementos con opacidad cero.
*   El modo oscuro a 375 px.
*   La causa exacta del error 418. La causa probable es el póster de los videos, que el servidor emite claro y el cliente elige oscuro.

### Qué decide

*   **Q2 (animaciones).** El estado actual no pasa: genera 53 violaciones de CSP. Las dos salidas siguen siendo C1 (pasar las entradas a CSS) y C2 (abrir `style-src-attr` en la landing). Con C2 el texto llega con opacidad cero hasta hidratar. La medición confirma que hace falta elegir una; no cambia la recomendación de C1.
*   **Videos y tema (filas 3 y 4 de la matriz).** Son obligatorias: sin ellas la hidratación falla en modo oscuro.
*   **Q1 (nueve paneles).** Confirmada como necesaria para cumplir el handoff: hoy el HTML del servidor trae la descripción de una sola herramienta.
*   **Recursos del servicio `ssr`.** El límite de 384 MB de la sección 7.2 queda holgado frente a los 115 MB medidos. El plazo de 2 segundos queda muy por encima de los 5 ms de un render.
*   **Nombre del bundle y `noExternal`.** Resueltos: no hace falta `inertia.ssr.bundle`, y la imagen de Node no necesita `node_modules`.

Los archivos del experimento quedaron en `.claude/worktrees/agent-aa6aedddc63beb902/` (el HTML medido está en `storage/ssr-exp/landing.html`). Es una copia desechable; nada de ahí entra al PR.

--------------------------------------------------------------------------------

## 18. Decisiones del dueño

| Fecha | Pregunta | Decisión |
| :-- | :-- | :-- |
| 2026-11-14 | **Q1.** Nueve paneles de herramientas en el DOM, uno visible | **Autorizada, opción (a).** Es un cambio de comportamiento aprobado por el dueño (regla 2 de `CLAUDE.md`): las pruebas de `tests/Browser/LandingTest.php:251-257` y `:309` pasan a afirmar nueve paneles en el DOM y uno visible, y los ids pasan de `tool-panel` a `tool-panel-<key>`. |
| 2026-11-14 | **Q2.** Animaciones de entrada | **Autorizada, opción C1.** Las entradas se reescriben en CSS. No se abre `style-src-attr`. Decidida después de medir 53 violaciones de CSP con el estado actual (sección 17). |

| 2026-11-14 | **Q3.** Si Node falla | **Opción recomendada (D1).** La landing responde 200 sin cuerpo renderizado, con las etiquetas del slot, un registro y la comprobación externa. No se responde 503. |
| 2026-11-14 | **Q4.** Imagen y servicio de Node en producción | **Autorizada (S-B).** Servicio `ssr` aparte, con su propia imagen, sin `.env` y en una red interna. |
| 2026-11-14 | **Q5.** Consentimiento | **Opción recomendada.** `consent_default` en `denied` hasta que el abogado del dueño indique otra cosa. El dueño fue avisado de que no hay medición mientras tanto. |
| 2026-11-14 | **Q6.** Identificadores de clic de Google Ads | **Opción recomendada.** No se guardan `gclid`, `gbraid` ni `wbraid`. |
| 2026-11-14 | **Q7, Q8, Q9 y Q10** | **Opción recomendada de cada una**: `generate_lead` como conversión principal en Ads (Q7), mismo dominio con salvaguardas (Q8), sin llave de idempotencia en este trabajo (Q9) y las pruebas existentes que cambian (Q10). La llave nueva `first_contact` de la fila de la consola de demos queda autorizada como cambio de comportamiento. |
| 2026-11-14 | **Q11.** Orden frente al rediseño de la landing | **Opción recomendada.** Primero el SSR; el rediseño se hace después sobre esa base. |
| 2026-11-14 | **Q-reglas.** `CLAUDE.md` en este PR | **Opción recomendada.** Las viñetas de la pieza 9 entran en este PR. |

Sigue abierta **Q12**: el dueño no ha confirmado el valor de `APP_URL` en Dokploy. Solo bloquea la redirección de `www`, que es una tarea de despliegue.
