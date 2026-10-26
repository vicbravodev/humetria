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
4. **El anonimato de las respuestas es un invariante** (ver "Privacidad"). Romperlo es un bug crítico.
5. **Dependencias nuevas solo con aprobación del usuario.**
6. Código, rutas, tablas, columnas, enums e identificadores siempre en **inglés**. Textos de UI en español (es-MX).

## Definición de terminado

Nada está terminado hasta que esto pasa en verde:

```bash
composer test   # pint --test + phpstan (max) + toda la suite de Pest
npm run types:check
```

Para iterar, corre lo más acotado: `php artisan test --compact --filter=nombre` o la ruta del archivo.

## Arquitectura: monolito modular

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
- Factories en `database/factories/{Module}/` enlazadas con el atributo `#[UseFactory]`.
- `tests/Unit/ArchitectureTest.php` hace cumplir todo esto. Si falla, se corrige el diseño, no la regla.
  Ojo: en Pest, una expectativa `arch()` con varios destinos pasa en silencio si alguno no existe;
  escribe cada regla con un solo namespace destino.

Módulos previstos: `Tenancy` (empresas, centros de trabajo), `Organization` (áreas, empleados),
`Instruments` (Guías I/II/III y calificación), `Campaigns` (campañas, invitaciones),
`Responses` (respuestas anónimas), `Results` (agregados y reportes), `Notifications` (correo/SMS/WhatsApp).

## Multi-tenancy

- Una base de datos. Toda tabla de negocio lleva `tenant_id` y su modelo aplica un global scope por tenant.
- Cada modelo con tenant tiene un test que prueba que la empresa A no puede leer ni modificar datos de B,
  tanto por consulta directa como por URL (route model binding debe dar 404, no 403 que delate existencia).
- Nunca se confía en un `tenant_id` que venga del request.

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
| Feature | Pest + `assertInertia` | HTTP, validación, autorización, aislamiento de tenant, props que recibe cada página |
| Browser | Pest Browser (Playwright) | Solo flujos críticos: el empleado contesta desde el celular, enlace de un solo uso, flujo de Guía I |
| Arquitectura | Pest `arch()` | Aislamiento de módulos, tipos estrictos, clases final, presets php y security |
| Mutación | `pest --mutate` | Lógica de calificación y privacidad: los tests deben detectar el error |

- Cada comportamiento: camino feliz + bordes + fallos. Bordes obligatorios a pensar: límites exactos
  (15/16, 50/51 trabajadores), vacíos, duplicados, otro tenant, usuario sin permiso, enlace ya usado,
  campaña cerrada, grupo debajo del mínimo.
- Usa factories y sus estados; no armes modelos a mano.
- Las peticiones HTTP externas están bloqueadas en tests (`Http::preventStrayRequests()`); usa `Http::fake()`.
- Proveedores externos (SMS, WhatsApp, correo) detrás de un contrato con un fake para tests.
- Tests de navegador: `php artisan test --compact tests/Browser` (requieren `npm run build`).

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

- PHP corre local; solo Postgres corre en Docker: `docker compose up -d pgsql` (puerto 5433).
- El correo local va a `log`: ningún correo, SMS ni WhatsApp sale del entorno de desarrollo.
- Configuración nueva → `config/*.php` + `.env.example` (sin secretos reales).
- Hooks de Claude (`.claude/hooks/`): Pint + PHPStan en cada PHP editado; `composer test` al terminar si hay cambios.
- Commits a nombre del usuario, sin atribución a Claude. El push siempre pide permiso.
