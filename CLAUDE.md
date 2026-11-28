# Humetria — Plataforma SaaS de herramientas de RH

SaaS multi-tenant de herramientas de RH: las empresas cargan a su gente, la plataforma invita y recolecta de forma
confidencial, y RH recibe un diagnóstico listo para actuar y para presentar ante la STPS. Arrancó con NOM-035, NOM-037
(teletrabajo) y Ley Silla; hoy suma clima y pulso, asistencia, vacaciones, denuncias, compensación y más (ver «Módulos»).

Las guías del framework (Laravel Boost) están en @AGENTS.md; este archivo manda cuando choquen («Excepciones a AGENTS.md»).
El detalle que no hace falta en cada sesión vive en `docs/` y se lee cuando toca.

## Reglas no negociables

1. **TDD estricto.** Primero el test, verlo fallar por la razón correcta, luego el código mínimo que lo pasa.
   No hay código de producción sin un test que lo haya exigido.
2. **El test define el comportamiento.** Si un test falla, se corrige el código, nunca el test.
   Un test solo se cambia cuando el usuario cambia el comportamiento esperado, y eso se dice explícitamente.
   Prohibido: `->skip()`, `markTestSkipped`, `->todo()` para esconder fallos, aflojar aserciones,
   borrar tests, o envolver en `try/catch` para que pase.
3. **Cero supresiones en el análisis estático.** Larastan corre en `level: max`. Prohibido `@phpstan-ignore`,
   baseline, `assert()` o `@var` inline para forzar tipos, y casts solo para callarlo. Se arregla la causa.
   Lo mismo aplica a TypeScript: `tsconfig.json` en modo strictest; prohibido `as`, `!`, `@ts-ignore`,
   `@ts-expect-error` y `any` para forzar tipos. Se arregla la causa.
4. **El anonimato de las respuestas es un invariante** (ver «Privacidad»). Romperlo es un bug crítico.
5. **Dependencias nuevas solo con aprobación del usuario.**
6. Código, rutas, tablas, columnas, enums e identificadores siempre en **inglés**. Textos de UI en español (es-MX).

## Definición de terminado

- **Mientras se trabaja:** solo los tests de la tarea (`php artisan test --compact --filter=…` o la ruta; `composer test:fast`
  corre lo que tu cambio afecta), Pint y PHPStan. Nunca la suite completa tras cada cambio.
- **La verificación completa es el CI** (job `ci` de `.github/workflows/tests.yml`, que espera a `build` —el frontend, una vez—,
  `checks`, `tests` en 3 shards, `coverage` —une los shards y aplica el 90 %—, `browser` en 4 shards y `ssr`; los shards se
  reparten por tiempo con `tests/.pest/shards.json`, que se escribe con los tiempos del CI y nunca con `--update-shards`:
  `docs/testing.md`, «Los shards»). `main` solo entra por PR con `ci` en verde (ruleset). Terminado = CI en verde y cobertura ≥ 90%
  (Unit/Feature y Vitest); no se baja el umbral.
  `composer ci:check` repite el CI en local: solo si el dueño lo pide o para reproducir un fallo del CI.
- Un cambio que solo trae documentación que nadie lee (`docs/`, `.md` de la raíz) se salta el CI y el deploy
  (`scripts/ci-code-changed.sh`). Un documento que un test o la app leen es código (`docs/normas`, `docs/operations`,
  `CLAUDE.md`…) y va en ese script (`tests/Unit/CiCodeChangedTest.php`); todo job nuevo de `tests.yml` espera a `changes`.
- **Humi está al corriente.** Un módulo o una función que cambia lo que la empresa tiene, debe o puede consultar no está
  terminado hasta que Humi lo sabe; es parte del entregable, en el mismo PR. Se conecta por los contratos públicos del
  módulo: un lector en `Assistant/Support/Readers` (lo cuenta `GetComplianceOverview`), una herramienta si hace falta
  detalle, sus datos vivos si los hay, y sus criterios en `docs/normas/*/criterios.md` (entran solos al buscador). Mismas
  reglas de privacidad: conteos y estados, nunca respuestas ni personas. `tests/Feature/Assistant/ModuleCoverageTest.php`
  falla con un módulo nuevo sin conexión; eximir uno exige su razón en ese test.
- **Mutación: semanal en CI** (`mutation.yml`, `--min=95`), nunca en los PR. Entra sola la clase cuyo test declara
  `mutates(Clase::class)`; por omisión toda clase de lógica entra (que sobre un test a que falte un borde crítico) y solo se
  exime lo que no decide nada (DTO, texto o catálogo fijo, adaptador externo) con su razón en `MutationCoverageTest`. Todo
  módulo, el Kernel incluido, pasa el 95. En local **solo si el usuario la pide**, acotada: `composer mutate -- --class=…`. Nunca se baja
  `--min`; si falla, falta el test que mata al mutante. Detalle: `docs/testing.md`.

## Arquitectura: monolito modular

La aplicación vive bajo `/admin` (login incluido); `/` y sus páginas públicas son marketing. Cada parte del negocio es un
módulo en `app/Modules/{Module}`:

```
Contracts/  Events/  Data/   API pública: interfaces, eventos de dominio, DTOs readonly
Actions/                     casos de uso; una clase = una acción (`handle()`)
Models/  Enums/  Support/    internos: Eloquent, enums, lógica y lecturas del módulo
Http/{Controllers,Requests,Middleware}/  Listeners/  Jobs/  Notifications/  Exceptions/  Console/  Providers/
```

- **Solo `Contracts`, `Events` y `Data` son públicos.** Otro módulo jamás importa un modelo, acción o enum ajeno: lee por
  contrato y reacciona a eventos. Ningún módulo hace joins a tablas de otro.
- `Actions` y `Models` no dependen de HTTP (`Request`, `Inertia`, `request()`, `session()`, `auth()`).
  El controlador valida con su FormRequest, arma un DTO y llama a la acción.
- **Autorización** en el `authorize()` del FormRequest (403 antes de validar: `tests/Feature/FormRequestAuthorizationTest.php`)
  y en `Gate` para permisos de plataforma; solo `FirstSteps` y `Engagement` tienen `Policies/`. Controladores delgados:
  autorizan, delegan a una acción y devuelven `Inertia::render`.
- Kernel compartido (`App\Models\User`, `App\Http`, `App\Concerns`) no depende de internos de módulos.
- Las clases concretas son `final`; se depende de contratos, no de implementaciones.
- Factories en `database/factories/{Module}/` enlazadas con el atributo `#[UseFactory]`.
- `tests/Unit/ArchitectureTest.php` hace cumplir todo esto. Si falla, se corrige el diseño, no la regla. En Pest, una
  expectativa `arch()` con varios destinos pasa en silencio si alguno no existe: una regla, un namespace destino.
- Única excepción: la historia de la empresa demo vive en `database/seeders/Demo` y arma la empresa llamando a las Actions,
  Models y Enums de los módulos (no a su Http, Jobs, Support ni Notifications). Nadie más puede usar `Database\Seeders\Demo`
  salvo el proveedor del módulo `Demos`: lo exige una regla `arch()` aparte. No se amplía a otro código.

### Rutas

- Una por módulo en `routes/{module}.php`; las del panel de una empresa (`/admin/{tenant}`) se incluyen desde
  `routes/company.php`, el resto desde `routes/web.php`.
- Toda ruta tiene nombre y exige autenticación, salvo las de la lista pública de `tests/Feature/RouteSecurityTest.php`;
  agregar ahí una ruta exige una razón.
- Sin cuenta: `*-portal.php` son el **portal** de la persona trabajadora (`/me/{slug}/...`; también `notifications-portal.php`, la
  suscripción a avisos push), que lee su sesión
  (`worker-access.php`, que además sirve, sin sesión, el manifest, el service worker y el cascarón de la app instalable:
  entra con su llave o con un enlace de un solo uso); ninguna dirección suya lleva una credencial que
  sirva más de una vez (`RouteSecurityTest`). El token del enlace es la credencial (con throttle por enlace y por
  dirección) en `survey.php` y `engagement-survey.php`, las encuestas; `complaints-public.php`, el canal de denuncias;
  `shared-report.php` y `compensation-shared.php`, reportes por enlace temporal; `sso.php`, el inicio de sesión con el proveedor de la empresa (callback con `state`, `nonce` y PKCE); `workers.php`, la página de trabajadores;
  `attendance-device.php`, los checadores: la pantalla del QR de un centro (`/a/{slug}`, con su cookie) y Marcar y Mi semana de la
  persona (`/me/{slug}/…`), firmados por acción; los boletos y la entrega de marcas sin conexión van sin sesión, cookies ni
  CSRF, como `complaints-public.php` (`RejectCrossSiteRequests`), autorizados por la firma de cada marca y un boleto de un solo uso.
  En `messaging.php` la credencial es la firma del proveedor (Meta, Resend) sobre el cuerpo crudo, con throttle por dirección y
  tamaño máximo.

### Módulos

- `Announcements`: avisos de la plataforma a las empresas (banner en su panel, por plan o a todas) y el modo mantenimiento.
- `Assistant`: Humi, el asistente de IA (ver su README).
- `Attendance`: asistencia: checadores y kiosco, zonas en mapa, horarios, correcciones, marcas sin conexión y su revisión.
- `Billing`: cuentas, suscripciones, cargos, estados de cuenta y pagos.
- `Blog`: blog público para SEO: artículos en `content/blog/*.md`, validados en CI y publicados solo con el visto bueno del dueño
  (`reviewed_at`); páginas de Inertia con SSR y el header de la landing, sin base de datos (ver `docs/blog/README.md`).
- `Campaigns`: evaluaciones NOM-035: campañas, participantes e invitaciones, la encuesta del empleado.
- `Compensation`: compensación y contrato colectivo: prestaciones, salarios por nivel, escenarios y su costo.
- `Complaints`: canal de denuncias: público, seguimiento anónimo, gestores de casos.
- `Demos`: solicitudes de demo de la landing y su consola (el alta de empresas es solo por demo); la empresa demo: sala
  (`/admin/demo`), puerta y reinicio (`docs/operations/demo.md`).
- `Disclosure`: difusión: documentos y políticas, acuses, publicación de resultados, página de trabajadores.
- `Engagement`: encuestas de clima y pulso, anónimas (ver su README).
- `FirstSteps`: el recorrido de primeros pasos del dueño.
- `Instruments`: catálogo y calificación de instrumentos (Guías I/II/III, lista de NOM-037, Ley Silla); sin tablas.
- `Interventions`: programa de intervención: acciones, avance, evidencia.
- `Leave`: vacaciones y permisos: políticas, saldos, solicitudes, días festivos.
- `Messaging`: transporte de WhatsApp y de eventos de correo: gateway, webhooks, plantillas, consentimiento, historial de
  estados (ver su README).
- `Notifications`: avisos push (Web Push con VAPID) a la persona trabajadora, que dicen solo de qué tipo son (cifrado, sin nombres ni datos): suscripciones y envío; reacciona a
  eventos públicos de Attendance y WorkerAccess; nunca manda correo (ver su README).
- `Operations`: consola de la plataforma para el superadmin: salud del sistema (colas, trabajos fallidos, latido del
  programador, webhooks) y mensajería en conteos; sin tablas, nunca datos de personas.
- `Organization`: áreas, empleados, niveles de puesto, líneas de reporte (organigrama).
- `Responses`: respuestas anónimas y tamizaje de Guía I.
- `Results`: agregados con mínimo por grupo, reportes, canalización de Guía I.
- `Seating`: Ley Silla: asientos, evaluación de riesgo, medidas, encuesta.
- `Sso`: inicio de sesión único (OIDC) de las personas con cuenta, plan Integral (ver su README).
- `Telework`: NOM-037: teletrabajadores, lista de verificación, documentos, comité, su sección del portal.
- `Tenancy`: empresas, centros de trabajo, miembros y roles, módulos y límites por empresa (`CompanyModules`).
- `WorkerAccess`: identidad de la persona trabajadora sin cuenta: llaves, enlaces de un solo uso, sesión y prueba reciente;
  el inicio de su portal en `/me/{slug}` (ver su README).

Cada módulo manda sus correos con su carpeta `Notifications/`; el módulo `Notifications` solo manda avisos push, nunca correo ni WhatsApp.
No hay SMS. WhatsApp sale solo por `Messaging`.

## Arquitectura del frontend

- `resources/js`: `pages/{module}/` (una página por ruta, delgada, sin lógica), `components/{module}/` (espejo del módulo
  del backend), `components/humetria/` (design system: solo usa ui, sus propias piezas, hooks y lib), `components/ui/` (shadcn generado),
  `components/*.tsx` (el marco de la app), `layouts/ hooks/ lib/ types/`.
- Un módulo nunca importa componentes de otro. Las páginas no se importan desde ningún lado.
  `hooks`, `lib` y `types` no importan componentes ni layouts. Imports siempre con `@/`, nunca `../`.
- Límites: 300 líneas por archivo, 100 por función, complejidad 10, profundidad 4, 3 parámetros; un componente por archivo,
  kebab-case, sin ciclos (`vp lint` con `frontend-architecture.ts`; `tests/Unit/FrontendArchitectureTest.php`). Si una regla
  estorba, se parte el componente: no se sube el límite. La lógica no visual va en `hooks/` o `lib/`.

## Multi-tenancy

- Una base de datos. Toda tabla de negocio lleva `tenant_id` y su modelo usa `BelongsToCurrentTenant`; las excepciones, cada
  una con su razón, están en `tests/Feature/TenantScopedModelsTest.php`.
- Cada modelo con tenant prueba que la empresa A no lee ni modifica datos de B, por consulta y por URL (404, no 403).
- Nunca se confía en un `tenant_id` que venga del request.

## Seguridad

- CSP con nonce (`App\Http\Middleware\SecurityHeaders`): todo `<script>`/`<style>` inline lleva `nonce="{{ Vite::cspNonce() }}"`
  y las librerías que inyectan estilos reciben `cspNonce` (`@/lib/csp-nonce`). Aflojar la CSP exige su motivo en el PR.
- Una ruta nueva de la cuenta personal (perfil, contraseña, 2FA, passkeys) va en `ForbidWhileImpersonating::protectedRoutes()`.
- La app instalable de la persona (`docs/operations/pwa.md`): el service worker vive en `/me/{slug}/sw.js` con alcance
  `/me/{slug}`, guarda solo archivos del build y el cascarón, nunca HTML de Inertia ni datos de personas; `PWA_SW_KILL` lo apaga
  sin apagar la entrega. Todo `GET` bajo `/me/` permite cámara y ubicación a su propio origen (`SecurityHeaders`); el resto de la
  app las cierra.
- Toda respuesta lleva `X-Robots-Tag: noindex, nofollow`, salvo las rutas de `App\Http\IndexablePages` (con su test en
  `tests/Feature/IndexablePagesTest.php`) que responden 200 en producción; staging y local nunca se indexan.
- Solo las páginas de `App\Http\SsrPages` pasan por Node, y solo sin sesión; una ruta nueva no activa SSR. Si Node falla
  la página responde 200 y React la pinta: nunca 5xx por Node.
- PostHog mide solo las páginas públicas (landing, página de cada herramienta y guía: `Analytics::measuredRoutes()`): lo deciden `App\Http\Analytics` y `App\Http\Consent`; con consentimiento `denied` no hay
  medición. Ningún dato de la persona va a PostHog. Todo enlace que sale de la landing o llega a ella es un `<a href>`.
- La visita a la landing se graba (anónima, lo escrito enmascarado) y se mide su velocidad solo con `POSTHOG_LANDING_REPLAY=true` y consentimiento `granted`; la build completa se descarga tras el `load` (`docs/operations/ssr.md`).
- El panel de una empresa se graba solo con `POSTHOG_SESSION_RECORDING=true`, anónimo y enmascarado (`lib/session-recording.ts`):
  se ve la interfaz y se oculta todo dato de la empresa (`lib/recording-mask.ts`: lo que viene en las props, datos con forma
  personal, opciones de búsqueda, `ph-mask`), campos y atributos; sin persona, empresa ni dirección; nunca quejas, Guía I,
  Humi (`ph-no-capture`), la cuenta personal ni al suplantar. Una pantalla sensible nueva entra en `UNRECORDED` con su test;
  un dato calculado en el navegador que no venga en las props lleva `ph-mask`.
- Reglas y operación de SSR, medición, eventos y errores a PostHog: `docs/operations/ssr.md` (se actualiza en el mismo PR).
- Errores: el navegador de toda página manda a PostHog `$exception` con el mensaje enmascarado (`lib/error-tracking.ts`,
  solo errores, sin persona ni persistencia); `AppErrorBoundary` muestra «Algo salió mal» con el código de soporte. El servidor
  manda (`App\Support\PostHogExceptions`, en cola, mejor esfuerzo, máx. 10 por clase al minuto) solo clase, archivo, línea y
  traza sin argumentos, nunca mensaje, URL ni usuario; un `report()` nuevo en `bootstrap/app.php` va después del de PostHog.
- Humi (y todo agente de `laravel/ai`) manda cada llamada como `$ai_generation` (`SendLlmAnalytics`): solo Humi lleva prompt
  y respuesta; un agente sobre texto de encuestas manda solo números, y un test lo exige. Detalle y alertas:
  `docs/operations/observability.md`.

## Privacidad (invariante)

- Las respuestas anónimas (Guías II/III, Ley Silla, clima y pulso) viven en tablas **sin ninguna llave** hacia empleado,
  invitación ni token. La invitación solo registra "ya contestó". El enlace es de un solo uso.
- IDs de respuestas: UUID v4 aleatorio (nunca ordenable por tiempo). RH nunca ve la hora exacta de cada respuesta.
- Todo resultado agregado aplica el **mínimo de respondentes por grupo** de la empresa (`Tenancy\Contracts\GroupMinimums`):
  5 por omisión, de 1 a 50, solo el dueño lo cambia, y cada campaña o encuesta lo congela al lanzarse; `RESULTS_MIN_GROUP`
  es solo el default de la plataforma. Debajo del mínimo el grupo no se muestra; se aplica después de cada filtro. Con 1
  (sin mínimo) ningún texto promete ocultar: advierte con honestidad que un grupo muy pequeño podría identificar respuestas.
- La Guía I sí es identificada (obligación de canalizar, numeral 5.5) y solo la ven roles autorizados.
- Toda página que ve RH tiene un test que afirma que sus props de Inertia no contienen respuestas individuales.
- El modelo de amenaza es RH y la aplicación, no el operador con acceso a la base de datos.

## Logging y auditoría

- Logs en JSON, una línea por evento (`App\Logging\StructuredLogging`); `request_id`, `user_id`, `tenant_id` e
  `impersonator_id` llegan por `Context`. El mensaje es un evento fijo `modulo.accion` en pasado (`campaign.launched`);
  los datos van en el contexto, nunca interpolados.
- Toda acción que cambia estado escribe un log. Las sensibles (lanzar o cerrar campaña, ver o exportar resultados, la lista
  de canalización de Guía I, cambiar roles) además van a la bitácora con `App\Actions\RecordAudit`. `audit_entries` es de
  solo inserción (trigger de Postgres).
- **Nunca** van a logs ni a la bitácora respuestas de encuesta ni nada que ligue a un empleado con una respuesta anónima
  (`tests/Feature/Responses/SeatingResponsesPrivacyTest.php` lo prueba). `RedactSensitiveData` borra llaves conocidas; una
  nueva se agrega ahí con su test. Un `QueryException` se reporta sin sus valores.
- En producción `LOG_STACK=stderr,posthog`: el canal `posthog` (`App\Logging\PostHogLogs`) manda los logs ya redactados, en lote.
  Fallos de jobs y del scheduler se loguean en un solo lugar (`queue.job_failed`, `queue.long_wait`, `schedule.command_failed`,
  `schedule.heartbeat` cada 5 min) y de ahí salen las alertas. Un job nuevo de un solo correo lleva `tries=3`, `backoff=[60,300]`,
  salvo que reintentar no sea seguro.

## Idioma y tiempo

- Locale `es`; atributo nuevo en un FormRequest → `lang/es/validation.php` → `attributes`.
- Todo en UTC (`timestampTz`); solo al mostrar se convierte a `config('app.display_timezone')` (America/Mexico_City).

## Design system

- Fuente de verdad: **la app misma** y su contexto de diseño: `PRODUCT.md` (para quién, tono, anti-referencias),
  `DESIGN.md` (tokens y reglas, formato de Impeccable) y `docs/design-system/` (marcos, piezas y cuándo usar cada una,
  «Composición y espacio»). La skill `impeccable` los lee sola; el design system «Humetría» de claude.ai
  (https://claude.ai/artifact/JA4ZDeATtbEkn7cHymwPmb) se genera de ellos y del código con `/design-sync`.
- **Pantalla nueva o rediseño: primero el diseño, aprobado.** Se diseña en Claude Design con el design system «Humetría»
  (o con `/impeccable shape`) y se construye solo cuando el dueño lo aprueba; el PR enlaza el diseño. El código lo replica
  con las piezas que nombra; si una regla del proyecto obliga a desviarse (paginar, privacidad, tenant), se dice en el PR.
  Un ajuste a una pantalla existente parte de esa pantalla. Las referencias externas (cómo lo resolvió Linear, Stripe o un
  design system público) informan el diseño, pero se traducen a las piezas y tokens de Humetría: nunca lo reemplazan.
- Toda pantalla parte de la más parecida y reusa sus piezas (marco con migas, encabezado con estado, tarjetas, tablas
  `muted`, pestañas con conteo, estados vacíos con siguiente paso); una que se vea como un formulario sin diseñar no está
  terminada. Una pieza que usan dos o más módulos vive en `components/humetria/`: es la que llega al design system.
- **Antes de cerrar un trabajo con UI:** `/impeccable critique` y `/impeccable polish` sobre lo cambiado, y revisión en el
  navegador en claro y oscuro a 375, 768, 1024, 1350 y 2000 px, junto al diseño aprobado: sin huecos muertos ni columnas
  de relleno.
- **El design system se mantiene en el mismo PR:** un cambio a `components/ui`, `components/humetria`, los tokens de
  `resources/css/app.css` o `docs/design-system/` actualiza `DESIGN.md` y los docs que toca, y termina con `/design-sync`
  (o el PR dice que queda pendiente). Lo que vive solo en claude.ai se pierde en la siguiente sincronización.
- Un solo acento (`primary`; `brand-*` solo en el logo), salvo el color de cada familia de herramientas en el sitio público (`family-*`); color solo por tokens; texto ≥ 4.5:1, controles ≥ 3:1; los ajustes
  a shadcn tras cada `npx shadcn add` (`DesignTokensOnlyTest`, `DesignSystemTest`, `ShadcnAdjustmentsTest`).
- El panel tutea y la encuesta trata de "usted"; el riesgo siempre con `RiskLevel`; la encuesta nunca avanza sola.

## Base de datos y listas

- `Model::shouldBeStrict()` fuera de producción: un N+1 rompe el test; se corrige con `with()`/`load()`.
- Pantallas críticas (avance de campaña, resultados) afirman `$this->expectsDatabaseQueryCount(n)`.
- Postgres en tests, nunca SQLite. Integridad en la base (FK, `unique`, `check`, `not null`). Varios pasos: `DB::transaction()`.
- Toda lista de registros se pagina y busca en el servidor (`->paginate()->withQueryString()`, `Paginated<T>`,
  `SearchesText`); antes, evalúa si un resumen con conteos sirve mejor. Un selector de registros que crecen usa
  `serverSource`. Lo exigen `tests/Unit/PaginatedListsTest.php` y `tests/Feature/PaginatedListsTest.php`.

## Testing

- Unit (lógica pura), Feature (`assertInertia`: HTTP, validación, autorización, tenant, props), `arch()`, Vitest
  (`resources/js/lib` en `tests/js`).
- **Browser:** toda página Inertia tiene su prueba de humo con `visitPage()` (componente, sin errores de JS ni de CSP, axe;
  `tests/Unit/BrowserCoverageTest.php`). Flujos largos e interactivos solo para caminos críticos (el empleado contesta desde
  el celular, enlace de un solo uso, Guía I). `composer test:browser` requiere `npm run build`.
- Cada comportamiento: camino feliz + bordes + fallos. Bordes a pensar: límites exactos (15/16, 50/51), vacíos, duplicados,
  otro tenant, usuario sin permiso, enlace ya usado, campaña cerrada, grupo debajo del mínimo.
- Usa factories y sus estados. HTTP externo bloqueado (`Http::preventStrayRequests()`): `Http::fake()`. Proveedores
  externos detrás de un contrato con un fake. Pruebas deterministas: `docs/testing.md`, «Pruebas que no fallan por azar».

## Normas y documentos

- `docs/normas/` (`nom-035`, `nom-037`, `ley-silla`, `jornada`, `vacaciones`, `compensation`; `permisos`, de la plataforma):
  criterios con su fuente y texto de referencia. **Mandan**: si el código los contradice, se corrige el código o se actualiza el criterio con su justificación.
  Las pruebas de los instrumentos se escriben contra el texto de referencia.
- NOM-035 aplica **por centro de trabajo**: hasta 15 trabajadores → solo Guía I; 16 a 50 → Guías I y II; más de 50 →
  Guías I y III. El resto (Guía I, muestra, informe, plazos): `docs/normas/nom-035/criterios.md`.
- `docs/superpowers/{specs,plans}`, `docs/rediseno*`: registros históricos de diseño. Si chocan con el código y los tests,
  ganan el código y los tests. Excepción: el banco de reactivos de clima y pulso
  (`docs/superpowers/specs/2026-11-09-engagement-item-bank.md`) lo lee un test.

## Entorno local

- La app corre en Sail (`./vendor/bin/sail up -d` → `http://localhost`, con Vite, Horizon y el scheduler): comandos de la app
  con `sail artisan …`, `sail npm …`. Los tests corren en local contra el Postgres de Docker (5433); cada worktree tiene su
  base (`composer worktree:setup`). Configuración nueva → `config/*.php` + `.env.example`, sin secretos.
- Hooks de Claude: Pint/PHPStan y `vp fmt`/`vp lint` al editar; al terminar, Pint `--test`, PHPStan, `npm run check` y
  `types:check` sobre lo cambiado. No corren tests. Detalle (primera vez, worktrees, colas, correo): `docs/setup.md`.

## Excepciones a AGENTS.md

`AGENTS.md` lo genera Laravel Boost (`boost:update` en `post-update-cmd`): no se edita a mano. Donde choca, manda esto:

- "Copy, estilos y layout no requieren tests" → aquí TDD para todo cambio de comportamiento visible.
- "Pídele al usuario que corra la suite" → tests acotados mientras se trabaja; la suite completa la corre el CI.
- `npm run dev` / `composer run dev` → Vite ya corre en Sail; si falta el manifiesto, `npm run build`.
- `php artisan …` → los comandos de la app van por `sail artisan …`; los tests corren en local.
- Laravel Cloud y la skill `deploying-to-cloud` → producción es Dokploy (`docs/operations/ssr.md`).
- Factories en `database/factories/{Module}/` con `#[UseFactory]`; la configuración de Vite es `vite.config.ts`.

## Sesiones y agentes

- Subagentes: Opus por omisión; Fable solo para análisis genuinamente difícil. En paralelo solo sobre archivos disjuntos,
  cada uno en su worktree (`composer worktree:setup`). Todo brief lleva la regla de tests acotados.
- Las notas de memoria sobre el estado del proyecto envejecen: verifícalas contra git antes de actuar.

## Flujo de trabajo: un PR por trabajo, commits atómicos

- Nada se commitea directo a `main`: todo entra por PR desde una rama propia, con el CI en verde.
- **Un PR = el trabajo que se pidió, completo**; no se parte ni se apilan PRs, y nunca dos trabajos sin relación juntos.
  Dependencias y harness (CI, hooks, reglas) que la feature no necesita van en su propio PR.
- **El PR se abre solo cuando el usuario dice "crea el PR"**, con `.github/pull_request_template.md` (RED/GREEN, checklist).
- **Un commit = un comportamiento** (test + código que lo pasa), cada uno en verde. Cada PR es seguro de revertir solo.
- Commits a nombre del usuario, sin atribución a Claude. El push siempre pide permiso.
