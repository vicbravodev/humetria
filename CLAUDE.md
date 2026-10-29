# Humetria — Plataforma SaaS NOM-035

SaaS multi-tenant para aplicar la NOM-035-STPS-2018: las empresas cargan a su gente, la plataforma invita y
recolecta de forma confidencial, y RH recibe un diagnóstico listo para actuar y para presentar ante la STPS.

Las guías del framework (Laravel Boost) están en @AGENTS.md. Este archivo manda sobre ellas cuando choquen.

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
4. **El anonimato de las respuestas es un invariante** (ver "Privacidad"). Romperlo es un bug crítico.
5. **Dependencias nuevas solo con aprobación del usuario.**
6. Código, rutas, tablas, columnas, enums e identificadores siempre en **inglés**. Textos de UI en español (es-MX).

## Definición de terminado

Nada está terminado hasta que esto pasa en verde:

```bash
composer test          # pint --test + phpstan (max) + Pest Unit/Feature con cobertura ≥ 90% + Browser en paralelo
composer test:mutate   # mutación sobre las clases declaradas con mutates(), score ≥ 85%
npm run types:check
npm run test:js        # Vitest sobre resources/js/lib, cobertura ≥ 90%
```

El CI corre los tres (`composer ci:check` incluye `npm run test:js`). Si la cobertura o el score bajan, faltan tests: no se baja el umbral.

Para iterar, corre lo más acotado: `php artisan test --compact --filter=nombre` o la ruta del archivo.

## Arquitectura: monolito modular

La aplicación vive bajo `/admin` (login incluido); `/` y sus páginas públicas son marketing.

Cada parte del negocio es un módulo en `app/Modules/{Module}`:

```
app/Modules/{Module}/
  Contracts/   API pública: interfaces que otros módulos pueden usar
  Events/      API pública: eventos de dominio
  Data/        API pública: DTOs readonly que cruzan la frontera
  Actions/     casos de uso; una clase = una acción (`handle()`)
  Models/      Eloquent, internos al módulo
  Enums/  Policies/  Http/Controllers/  Http/Requests/  Providers/
```

- **Solo `Contracts`, `Events` y `Data` son públicos.** Todo lo demás es interno. Otro módulo jamás importa
  un modelo, acción o enum ajeno. Para leer datos de otro módulo se usa su contrato; para reaccionar a algo,
  su evento. Ningún módulo hace joins a tablas de otro.
- `Actions` y `Models` no dependen de HTTP (`Request`, `Inertia`, `request()`, `session()`, `auth()`).
  El controlador valida con su FormRequest, arma un DTO y llama a la acción.
- Controladores delgados: autorizan (Policy), delegan a una acción y devuelven `Inertia::render`.
- Kernel compartido (`App\Models\User`, `App\Http`, `App\Concerns`) no depende de internos de módulos.
- Las clases concretas son `final`; se depende de contratos, no de implementaciones (SOLID: D).
- Rutas por módulo en `routes/{module}.php`, incluidas desde `routes/web.php`. Siempre con nombre.
- Toda ruta tiene nombre y exige autenticación, salvo las de la lista pública de `tests/Feature/RouteSecurityTest.php`; agregar ahí una ruta exige una razón.
- Factories en `database/factories/{Module}/` enlazadas con el atributo `#[UseFactory]`.
- `tests/Unit/ArchitectureTest.php` hace cumplir todo esto. Si falla, se corrige el diseño, no la regla.
  Ojo: en Pest, una expectativa `arch()` con varios destinos pasa en silencio si alguno no existe;
  escribe cada regla con un solo namespace destino.

Módulos previstos: `Tenancy` (empresas, centros de trabajo), `Organization` (áreas, empleados),
`Instruments` (Guías I/II/III y calificación), `Campaigns` (campañas, invitaciones),
`Responses` (respuestas anónimas), `Results` (agregados y reportes), `Notifications` (correo/SMS/WhatsApp).

## Arquitectura del frontend

`resources/js` sigue el mismo monolito modular:

```
pages/{module}/        una página por ruta, delgada: arma layout + componentes, sin lógica
components/{module}/   componentes de una funcionalidad; espejo del módulo del backend
components/humetria/   componentes del design system (solo usan ui, hooks y lib)
components/ui/         primitivas de shadcn (generadas; solo los ajustes del design system)
components/*.tsx       el marco compartido de la app (sidebar, navegación); el header vive en components/app-header/
layouts/  hooks/  lib/  types/
```

- Un módulo nunca importa componentes de otro. Las páginas no se importan desde ningún lado.
  `hooks`, `lib` y `types` no importan componentes ni layouts. Imports siempre con `@/`, nunca `../`.
- Límites (estándar de ESLint salvo los dos marcados): 300 líneas por archivo, 100 por función o componente
  (ESLint usa 50; un componente lleva JSX), complejidad ciclomática 10 (McCabe; ESLint usa 20),
  profundidad 4, 3 parámetros. Un componente por archivo, archivos en kebab-case, sin dependencias circulares.
- Lo hace cumplir `vp lint` (`frontend-architecture.ts`, conectado en `vite.config.ts`) y
  `tests/Unit/FrontendArchitectureTest.php` lo fija. Si una regla estorba, se parte el componente: no se sube el
  límite ni se agrega una excepción.
- La lógica que no es de presentación (cálculos, formato, llamadas) va en `hooks/` o `lib/`, no dentro del
  componente.

## Multi-tenancy

- Una base de datos. Toda tabla de negocio lleva `tenant_id` y su modelo aplica un global scope por tenant.
- Cada modelo con tenant tiene un test que prueba que la empresa A no puede leer ni modificar datos de B,
  tanto por consulta directa como por URL (route model binding debe dar 404, no 403 que delate existencia).
- Nunca se confía en un `tenant_id` que venga del request.

## Seguridad

- Toda respuesta web lleva los encabezados de `App\Http\Middleware\SecurityHeaders`, con una CSP basada en nonce.
- Todo `<script>` o `<style>` inline lleva `nonce="{{ Vite::cspNonce() }}"`; en el front, las librerías que inyectan
  estilos reciben `cspNonce` (`@/lib/csp-nonce`). Los tests de navegador afirman `->assertScript(cspViolations(), '[]')`.
- Aflojar la CSP (un origen, `'unsafe-inline'`, un hash) exige explicar el motivo en el PR.

## Privacidad (invariante)

- Las respuestas de Guía II/III viven en tablas **sin ninguna llave** hacia empleado, invitación ni token.
  La invitación solo registra "ya contestó". El enlace es de un solo uso.
- IDs de respuestas: UUID v4 aleatorio (nunca ordenable por tiempo). RH nunca ve la hora exacta de cada respuesta.
- Todo resultado agregado pasa por un único servicio que aplica el **mínimo de respondentes por grupo**
  (default 5, configurable). Debajo del mínimo el grupo no se muestra. Cada filtro adicional reduce el grupo:
  el mínimo se aplica después de filtrar.
- La Guía I sí es identificada (obligación de canalizar, numeral 5.5) y solo la ven roles autorizados.
- Toda página que ve RH tiene un test que afirma que sus props de Inertia no contienen respuestas individuales.
- El modelo de amenaza es RH y la aplicación, no el operador con acceso a la base de datos.

## Logging y auditoría

- Logs en JSON, una línea por evento (tap `App\Logging\StructuredLogging` en single, daily y stderr).
- `AssignRequestContext` pone `request_id` y `user_id` en `Context`: aparecen en cada línea de log, en la
  bitácora y en los jobs en cola. Al crear el módulo Tenancy, agregar ahí `tenant_id` con `Context::add`.
- El mensaje del log es un nombre de evento fijo `modulo.accion` en pasado (`campaign.launched`,
  `invitation.sent`); los datos van en el arreglo de contexto, nunca interpolados en el texto.
- Toda acción que cambia estado escribe un log. Las acciones sensibles (lanzar o cerrar campaña, ver o exportar
  resultados, consultar la lista de canalización de Guía I, cambiar roles) además van a la bitácora con
  `App\Actions\RecordAudit`.
- `audit_entries` es de solo inserción: un trigger de Postgres rechaza UPDATE, DELETE y TRUNCATE.
- **Nunca** se escriben respuestas de encuesta, ni nada que ligue a un empleado con una respuesta de Guía II/III,
  en logs ni en la bitácora. `RedactSensitiveData` borra llaves conocidas (`answers`, `password`, `token`…);
  una llave sensible nueva se agrega ahí con su test. Un `QueryException` se reporta como `database.query_failed` con el
  SQL, el código y la conexión, nunca con sus valores (`bootstrap/app.php`). El módulo Responses tendrá un test que envía una
  respuesta y afirma que ninguna línea de log contiene sus valores.

## Idioma y tiempo

- Locale `es` (validación y auth en `lang/es/`). Atributo nuevo en un FormRequest → su nombre en
  `lang/es/validation.php` → `attributes`.
- Se guarda siempre en UTC (`app.timezone` y la sesión de Postgres). Solo al mostrar se convierte a
  `config('app.display_timezone')` (America/Mexico_City). Columnas de fecha y hora: `timestampTz`.

## Design system

- La fuente de verdad es `docs/design-system/`; toda pantalla se compara con `capturas/` en claro y oscuro.
- Un solo acento (`primary`); `brand-soft` y `brand-deep` solo dentro del logo.
- Texto ≥ 4.5:1 y controles y foco ≥ 3:1, con `tests/Unit/DesignSystemTest.php` como protección: un color nuevo
  entra ahí con su par.
- El color va solo por tokens del design system; los colores crudos de la paleta de Tailwind o en hex los rechaza
  `tests/Unit/DesignTokensOnlyTest.php` (excepto `components/ui`).
- Los 5 ajustes a shadcn se aplican después de cada `npx shadcn add`, y `tests/Unit/ShadcnAdjustmentsTest.php`
  los hace cumplir.
- El panel tutea y la encuesta trata de "usted"; el riesgo siempre con `RiskLevel`; la encuesta nunca avanza sola.

## Base de datos y rendimiento

- `Model::shouldBeStrict()` fuera de producción: lazy loading, atributos no seleccionados y mass assignment
  silencioso lanzan excepción. Un N+1 rompe el test; se corrige con `with()`/`load()`, nunca desactivando.
- Pantallas críticas (avance de campaña, resultados) afirman el número de consultas
  con `$this->expectsDatabaseQueryCount(n)`.
- Postgres en tests también (base `testing`), nunca SQLite: el comportamiento debe ser el de producción.
- Integridad en la base de datos: llaves foráneas, `unique`, `check` y `not null` además de la validación.
- Operaciones de varios pasos dentro de `DB::transaction()`.

## Testing

| Capa | Herramienta | Qué cubre |
|------|-------------|-----------|
| Unit | Pest | Lógica pura: calificación de guías, tamaño de muestra, umbrales |
| Unit (frontend) | Vitest (`vp test`) | Lógica de `resources/js/lib` en `tests/js` (espejo de `resources/js`), cobertura ≥ 90%; hooks y componentes los cubre Browser |
| Feature | Pest + `assertInertia` | HTTP, validación, autorización, aislamiento de tenant, props que recibe cada página |
| Browser | Pest Browser (Playwright) | Solo flujos críticos: el empleado contesta desde el celular, enlace de un solo uso, flujo de Guía I |
| Arquitectura | Pest `arch()` | Aislamiento de módulos, tipos estrictos, clases final, presets php y security |
| Mutación | `composer test:mutate` | Lógica de calificación y privacidad: su test declara `mutates(Clase::class)` |

- Cada comportamiento: camino feliz + bordes + fallos. Bordes obligatorios a pensar: límites exactos
  (15/16, 50/51 trabajadores), vacíos, duplicados, otro tenant, usuario sin permiso, enlace ya usado,
  campaña cerrada, grupo debajo del mínimo.
- Usa factories y sus estados; no armes modelos a mano.
- Las peticiones HTTP externas están bloqueadas en tests (`Http::preventStrayRequests()`); usa `Http::fake()`.
- Proveedores externos (SMS, WhatsApp, correo) detrás de un contrato con un fake para tests.
- Tests de navegador: `composer test:browser` (en paralelo; requieren `npm run build`). La cobertura se mide sin ellos:
  en paralelo `--min` no se aplica, y los tests de navegador no aportan cobertura de PHP.
- Toda página Inertia tiene un test de navegador con `visitPage()` (componente exacto + sin errores de JS + sin violaciones
  de CSP + axe); `tests/Unit/BrowserCoverageTest.php` lo hace cumplir.

## Reglas de dominio NOM-035 (fuente: DOF 23/10/2018)

- La guía aplica **por centro de trabajo** (num. 4.4 y 2), no por empresa:
  hasta 15 trabajadores → solo Guía I; 16 a 50 → Guías I y II; más de 50 → Guías I y III.
- Guía I aplica a todos. Requiere valoración clínica si en la Sección I hay algún "SÍ" y además:
  algún "SÍ" en Sección II, o 3+ "SÍ" en Sección III, o 2+ "SÍ" en Sección IV. Si la Sección I es toda "NO", termina ahí.
- Muestra representativa (Guía III, num. III.1, más de 50 trabajadores):
  `n = 0.9604·N / (0.0025·(N−1) + 0.9604)` (N=100 → n=80), selección aleatoria y proporcional por género.
- Resultados disponibles para consulta de los trabajadores (num. 7.8).
- Informe (num. 7.7): datos del centro, objetivo, actividades, método, resultados, conclusiones,
  acciones de intervención y responsable con cédula profesional.
- Evaluación al menos cada 2 años (num. 7.9). Evidencia conservada al menos 1 año (num. 10.4).
  Registrar nombres de trabajadores con valoración clínica (num. 5.8).

## Entorno local

- La app corre siempre en Docker con Sail: `./vendor/bin/sail up -d` levanta la app en `http://localhost` (puerto 80),
  Postgres, Vite y los demás servicios. Vite arranca solo y Docker lo reinicia si se cae (`restart: unless-stopped`);
  su `node_modules` de Linux vive en un volumen, aparte del de tu máquina. Todo comando de la app va por el
  contenedor: `sail artisan …`, `sail composer …`, `sail npm …`. No se corre `php artisan serve` ni `npm run dev` local.
- Los tests son la excepción: corren en local (`composer test`, Vitest, navegador) contra el Postgres de Docker en el
  puerto 5433 (`FORWARD_DB_PORT`); `phpunit.xml` fija ese host y puerto, así que el `.env` puede apuntar a `pgsql`.
- Primera vez: `composer install`, `cp .env.example .env`, `sail up -d`, `sail artisan key:generate`, `sail artisan migrate`.
  El primer superadmin: `sail artisan humetria:create-superadmin`.
- El correo local va a mailtrap-local (bandeja en `http://localhost:3550`): ningún correo, SMS ni WhatsApp sale del entorno de desarrollo.
- Configuración nueva → `config/*.php` + `.env.example` (sin secretos reales).
- Hooks de Claude (`.claude/hooks/`): Pint + PHPStan en cada PHP editado; `vp fmt` + `vp lint` en cada TS/TSX editado;
  al terminar, `composer test` si hay cambios y además `npm run check` + `npm run types:check` si cambió frontend.
- Commits a nombre del usuario, sin atribución a Claude. El push siempre pide permiso.

## Flujo de trabajo: un PR por trabajo, commits atómicos

- Todo PR llena `.github/pull_request_template.md`: qué feature, por qué, cómo se probó (RED/GREEN) y su checklist.
- Nada se commitea directo a `main`: todo entra por PR desde una rama propia, con el CI en verde.
- **Un PR = el trabajo que se pidió, completo** (p. ej. "consola de solicitudes de demo", "importar empleados por CSV",
  "subir el nivel del design system"): trae su migración, backend, UI, tests y los refactors que necesita.
  El proyecto lo trabaja una sola persona: no se parte un trabajo en varios PRs ni se apilan PRs; la granularidad
  vive en los commits. Nunca dos trabajos sin relación en el mismo PR.
- **Un commit = un comportamiento** dentro del PR (test + código que lo pasa). Cada commit deja los tests en verde,
  para poder revertir o hacer bisect commit por commit.
- Cada PR deja el sistema funcionando y es seguro de revertir solo: no deja código a medias detrás de él.
- Cambios de dependencias y de harness (CI, hooks, reglas) que la feature no necesita van en su propio PR.
