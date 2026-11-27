# Mutation testing

## Convención

- Cada test de lógica de dominio declara `mutates(Clase::class)`: reglas, cálculos, máquinas de estado, límites
  (15/16, 50/51), privacidad y seguridad. No se declara en controladores, FormRequests sin reglas propias, DTOs,
  migraciones, config ni enums que solo tienen etiquetas.
- `mutates()` compara por prefijo: `mutates(Tenant::class)` también muta `TenantInvitation` y `TenantMembership`.
- Lo que PHP resuelve al compilar nunca lo marca la cobertura y saldría como "uncovered": las listas de los modelos
  (`#[Fillable]`, `$casts`, `$hidden`), las constantes (`private const int LEEWAY_SECONDS = 60;`) y los valores por omisión
  de propiedades (`protected $usesPKCE = true;`). Llevan `// @pest-mutate-ignore: <Mutador>` en su línea **y** un test que
  fije el valor (el límite exacto de la constante, la lista que se ve en la respuesta): el comentario solo dice que la
  mutación no puede medirlo, no que no se prueba.
- Mientras desarrollas, la mutación se corre en local **solo por clase**. La corrida de las clases críticas vive en CI, cada semana.
- Si un mutante escapa, falta un test. Si el mutante es equivalente (no cambia el comportamiento), se reporta; no se
  escribe un test artificial para matarlo.

## Comandos

```bash
composer test:fast                                                       # Tia: solo los tests que tu cambio afecta
composer mutate -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # una clase (o un namespace), 4 procesos
composer mutate:retry -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # lo que escapó, primero
composer mutate:critical                                                 # las clases de lógica de negocio, un módulo a la vez; CI le pone --min=95
composer mutate:full                                                     # todas las clases de mutates(), --min=95
```

Cada checkout usa su propia base de pruebas (ver `docs/setup.md`, "Worktrees en paralelo"): dos worktrees ya no se pisan. Dentro de un mismo checkout, no corras la mutación al mismo tiempo que `composer test`: comparten su base.

## Dónde corre

| Dónde | Qué muta | Umbral |
|---|---|---|
| Local, a mano | La clase que pasas con `--class` | — |
| `tests.yml` (PR y `main`) | Nada: la mutación no bloquea el merge | — |
| `mutation.yml`, domingo 23:00 (CDMX) y a mano | Las clases de `mutates()` sin controladores, FormRequests ni notificaciones (las carpetas `Notifications/` de cada módulo; el módulo `Notifications` sí entra), un módulo a la vez (`composer mutate:critical`) | 95% por módulo |

### Por qué un módulo a la vez

Pest mide la cobertura con todos los tests que corre y, para probar cada mutante, lanza un proceso con un `--filter` que
lista **cada test que cubre esa línea**. Una línea que casi toda la suite toca (el scope de tenant, un middleware) produce un
filtro de más de 128 KB, el límite del sistema para un argumento, y la corrida muere con `Argument list too long`
(pasó el 9 nov 2026, con 6086 tests, y la mutación no llegó a calcular su score). `--class` no lo arregla: solo limita
qué mutantes se generan, no qué tests corren.

`scripts/mutate-critical.sh` corre un módulo a la vez (el namespace bajo `App\Modules\`; lo demás es `Kernel`) y
`scripts/mutation-classes.php tests --plan` le da a cada uno sus clases y un `--filter` con solo los archivos de test que
declaran `mutates()` de ellas. Eso fija el tamaño del filtro al de un módulo, no al de la suite. Consecuencias:

- Un mutante lo tienen que matar los tests que declaran `mutates()` de su clase, no los de otro módulo que la toquen de paso.
  Un archivo de test que muta clases de dos módulos corre en los dos.
- `--min=95` se aplica a cada módulo, no al promedio. Un módulo que baja de 95 deja la corrida en rojo, pero los demás se corren
  igual y el log trae el score de cada uno (`=== mutation: <módulo>`).
- Una clase nueva entra sola: basta que su test declare `mutates()`. Un módulo nuevo, también.
- `mutate:full` (y `mutate` sin `--class`) no se parte así: con un filtro de esa línea compartida puede caer igual. Para medir, usa `mutate:critical`.

Si la corrida semanal falla, el workflow queda en rojo y GitHub avisa al correo de quien editó el cron; no toca ningún PR.

### Si la corrida falla

- Se reproduce con `composer mutate -- --class='App\Modules\X\Actions\Y'` y se agrega el test que mata al mutante.
- No se baja `--min`, no se excluye la clase ni se vuelve a poner la mutación en los PR.
- El umbral solo sube: si el score sostiene más de 2 puntos por encima, súbelo en `mutation.yml` y aquí.
- Para dejar una clase fuera de la mutación, su test no declara `mutates()`; la lista sale de `scripts/mutation-classes.php`,
  no hay una lista que mantener a mano.

### Cobertura

Unit/Feature corren en paralelo (`composer test:coverage`); como Pest no aplica `--min` en paralelo, `scripts/coverage-min.php`
lee el clover y falla bajo 90%. Los tests de navegador no aportan cobertura de PHP. Si la cobertura baja, faltan tests: no se
baja el umbral.

El 95% es el score de las clases críticas (97,44% el 1 nov 2026) redondeado hacia abajo: detecta regresiones sin fallar hoy.
Esa medición tuvo 7432 mutantes probados, ninguno escapó, y 195 "uncovered" (líneas que la cobertura no marca, como el
`mkdir` de `UpdateGeoIp`): son los que bajan el score. Un mutante que escapa es un test que falta.

# Pruebas que no fallan por azar

Una prueba que falla una vez de cada cien en CI cuesta más que una que falla siempre: nadie sabe si el cambio la rompió.
`tests/TestCase.php` siembra `mt_srand` con el nombre de cada prueba, así que Faker (`fake()`) da los mismos datos en cada
corrida del mismo código; pero basta añadir una fila o una línea a un `beforeEach` para que esos datos cambien. Una prueba
tiene que pasar con **cualquier** dato que Faker pueda dar, no solo con el de hoy.

## Reglas

1. **Datos que se buscan, se fijan.** Si una prueba busca, filtra, ordena o cuenta por un texto (`q=ana`, un nombre, un
   correo, un teléfono), todo lo que la búsqueda puede leer lleva valor explícito: nombre, correo, móvil y número de empleado.
   Un correo de Faker contiene «ana» más seguido de lo que parece.
2. **No se busca un número corto en texto con ids o fechas.** Un UUID v7 y un timestamp tienen «50000» por azar. Para afirmar
   que algo no se filtró, se busca en lo que la entrada *dice* (`auditContent($entry)` en `tests/Pest.php`: evento, sujeto y
   metadatos, sin UUID, actor ni fechas) o se usa un valor centinela que no puede salir de un id (`'E-001'`, `'Soldador secreto'`).
   `tests/Unit/FlakyPatternsTest.php` lo hace cumplir.
3. **El reloj es el de la app.** `now()`, `travelTo()`, `Carbon::setTestNow()`; nunca `time()`, `date()` ni `strtotime()`, que
   no se mueven con `travelTo` (`auth.password_confirmed_at` con `time()` hacía redirigir la página de seguridad si el reloj era otro).
4. **Una fecha fija en la prueba se acompaña de un reloj fijo.** Si el código compara contra «hoy» (vigencias, vencimientos,
   «la fecha no puede ser futura»), la prueba hace `$this->travelTo('2027-01-10 12:00:00')` antes de crear los datos; una
   prueba con `expires_at = '2027-02-01'` y el reloj real dejó de dar su resultado el 1 feb 2027.
5. **Hora de México, no UTC.** De 18:00 a 24:00 en México ya es el día siguiente en UTC. Las pruebas de «hoy», «este mes» o
   «vencido» se fijan con `config('app.display_timezone')` (ver el commit 323c49c8).
6. **Orden total.** Una consulta que se compara con `toBe([...])` lleva `orderBy` con desempate (`id`), y las marcas
   que se crean en el mismo instante no se distinguen por su hora.
7. **Navegador: se espera lo que se afirma.** Nada de `->wait(n)` antes de medir; `->assertScript(eventually('() => …'), true)`
   espera la condición (hasta 10 s), `settled()` espera a que terminen las animaciones (un toast, un menú que cierra) y
   `pathBecomes()` la ruta tras un clic que navega. La excepción son las ventanas de observación de algo que *no* debe pasar.
8. **Un nombre que se busca para probar que no se filtró no puede salir de Faker.** `tests/Support/SafeFakerNames.php`
   hace que `fake()->name()`, `company()`, `userName()` y `safeEmail()` nunca den un nombre con una palabra capitalizada
   que alguna prueba pase a `not->toContain('Rosa')` o `assertDontSee`: las lee de las propias pruebas, no hay lista que mantener.
   `memberOf()` da a cada miembro un nombre fijo (`Miembro owner`).
9. **Ninguna prueba depende de otra, del orden, del puerto ni de un id.** Cada una crea sus datos con factories.
10. **Quien recorre `resources/js` usa `frontendSourceFiles()`** (`tests/Pest.php`), no un `RecursiveDirectoryIterator` ni
    un `glob` propios: las sondas de lint de `FrontendArchitectureTest` crean y borran `zz-probe-*` mientras otras pruebas
    corren en paralelo, y un recorrido a mano tropieza con la carpeta que desapareció (`UnexpectedValueException`).
11. **Un texto no prueba que la página cambió.** Tras un clic que navega, el panel que se cierra puede decir lo mismo que la
    página nueva: se espera la ruta con `pathBecomes()` antes de afirmar lo demás.
12. **Un script que hace algo y tarda más de 1 s va en `runOnce()`.** Pest da 1 s a cada intento de `script()` y
    `assertScript()` y lo vuelve a mandar mientras el anterior sigue corriendo en la página (uno de 2.5 s corrió 16 veces):
    un script que contesta la encuesta la contestaba varias veces a la vez y escribía el comentario en tres preguntas.
    Uno que solo lee (`eventually()`) no lo necesita.
13. **La página se pinta después de `load`.** React monta el componente un momento después de que `visit()` regresa: antes de
    medir algo de la página se espera a que exista (`eventually()`), o la medida da `null` en un runner ocupado.
14. **Una página se abre con `openPath()`, nunca con `navigate()`.** Pest reintenta `navigate()` como una aserción, con 1 s por
    intento: una página que tarda más (un runner ocupado) se pide otra vez mientras carga, y la siguiente lectura cae en la
    carga nueva («Execution context was destroyed», captura en blanco). `openPath()` la abre una vez con el tiempo de toda la
    prueba. Lo mismo para recargar: `reloadPage($page)`, nunca `refresh()`, que Pest también reintenta.
    `tests/Unit/FlakyPatternsTest.php` hace cumplir las dos.
15. **La accesibilidad se revisa con `accessible($page)`, nunca con `assertNoAccessibilityIssues()`.** Pest le da a axe 5 s fijos,
    sin importar el timeout de la suite, y una página con el hilo principal ocupado (los videos de la landing en un runner sin
    GPU) se quedaba sin ellos aunque axe tarda una décima de segundo. `accessible()` revisa lo mismo con el tiempo de la prueba.
    `tests/Unit/FlakyPatternsTest.php` lo hace cumplir.

## Cómo se estresa

Dos variables de entorno, leídas por `tests/TestCase.php`, cambian los datos y el reloj de toda la suite sin tocar un test:

| Variable | Qué hace | Ejemplo |
|---|---|---|
| `TEST_SEED` | Se mezcla en la semilla de cada prueba: otros nombres, correos y números de Faker | `TEST_SEED=7` |
| `TEST_NOW` | Cada prueba arranca con su reloj en ese instante (UTC) y sigue corriendo; las que fijan su hora no cambian | `TEST_NOW='2026-11-14 23:59:59'` |

```bash
TEST_SEED=7 TEST_NOW='2028-02-29 05:30:00' vendor/bin/pest --parallel --processes=4 --exclude-testsuite=Browser,Ssr --compact
```

Relojes que valen la pena: 2026-11-15 03:00 UTC (sábado 21:00 en México), 31 dic 23:59:59 UTC, 29 feb de un año bisiesto,
el 1 de enero 00:00 UTC, domingo y lunes, y uno de años adelante (una fecha fija del 2027 que ya pasó se nota). Un reloj **anterior** a las
fechas fijas de las pruebas no es un caso real (el tiempo solo avanza) y falla por diseño las pruebas de «fecha futura».

Una prueba o un archivo sospechoso se repite aislado: `for s in $(seq 1 20); do TEST_SEED=$s php artisan test --compact ruta; done`.
Vitest: `for i in 1 2 3 4 5; do npm run test:js; done`.

# CI (`.github/workflows/tests.yml`)

Los jobs corren en paralelo; `ci`, el check obligatorio, espera a todos:

| Job | Qué hace |
|---|---|
| `changes` | Decide si el cambio necesita el pipeline (`scripts/ci-code-changed.sh`). |
| `build` | Construye el frontend una vez (`npm run build:ssr`) y lo sube como artefacto `assets`: `public/build`, `bootstrap/ssr` y los archivos de Wayfinder. |
| `checks` | Auditoría, Pint, PHPStan, `vp check`, tsc y Vitest. No construye: genera Wayfinder con `wayfinder:generate --with-form`. |
| `tests` (3 shards) | Unit y Feature con PCOV; cada shard guarda su cobertura (`--coverage-php`). |
| `coverage` | Junta la cobertura de los shards (`scripts/coverage-merge.php`, con el merger de php-code-coverage) y aplica el 90 % (`scripts/coverage-min.php`). |
| `browser` (4 shards) | Pest Browser. Si falla, sube las capturas (`tests/Browser/Screenshots`) como artefacto. |
| `ssr` | El bundle SSR con un Node real (usa el del job `build`: `SSR_PREBUILT=true`) y la imagen `ssr` con Docker. |

- Los shards se reparten por **tiempo** con `tests/.pest/shards.json` (Pest: `--shard` usa los tiempos de cada clase).
  Un archivo de prueba nuevo sin tiempo va por turno y Pest avisa que el archivo está viejo; se regenera de vez en cuando,
  con la suite completa en verde: `php artisan test --update-shards --parallel --processes=4 --exclude-testsuite=Ssr`
  (con `artisan test`: el servidor de Playwright lo arranca él). Los tiempos son de una Mac; en CI cambian las magnitudes, no
  las proporciones.
- Postgres en CI vive en un tmpfs y con `fsync`, `synchronous_commit` y `full_page_writes` apagados (la acción `setup`):
  la base muere con el job.
- Los navegadores de Playwright se guardan en caché por la versión de Playwright.
- Cada job tiene `timeout-minutes`; el flujo también corre en `merge_group`, listo para una cola de merge.
- En local nada cambia: `composer test` mide la cobertura en una sola corrida (`test:coverage`) y `composer setup` construye.
