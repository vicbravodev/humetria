# Mutation testing

## Convención

- Cada test de lógica de dominio declara `mutates(Clase::class)`: reglas, cálculos, máquinas de estado, límites
  (15/16, 50/51), privacidad y seguridad. No se declara en controladores, FormRequests sin reglas propias, DTOs,
  migraciones, config ni enums que solo tienen etiquetas.
- `mutates()` compara por prefijo: `mutates(Tenant::class)` también muta `TenantInvitation` y `TenantMembership`.
- En modelos, las listas que PHP resuelve al compilar (`#[Fillable]`, `$casts`, `$hidden`) llevan
  `// @pest-mutate-ignore: RemoveArrayItem` en su línea: la cobertura nunca las marca y saldrían como "uncovered".
- Mientras desarrollas, la mutación se corre en local **solo por clase**. La corrida de las clases críticas vive en CI, cada semana.
- Si un mutante escapa, falta un test. Si el mutante es equivalente (no cambia el comportamiento), se reporta; no se
  escribe un test artificial para matarlo.

## Comandos

```bash
composer test:fast                                                       # Tia: solo los tests que tu cambio afecta
composer mutate -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # una clase (o un namespace), 4 procesos
composer mutate:retry -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # lo que escapó, primero
composer mutate:critical                                                 # las clases de lógica de negocio, un módulo a la vez; CI le pone --min=95
composer mutate:full                                                     # las 293 de mutates(), --min=95
```

Cada checkout usa su propia base de pruebas (ver CLAUDE.md, "Worktrees en paralelo"): dos worktrees ya no se pisan. Dentro de un mismo checkout, no corras la mutación al mismo tiempo que `composer test`: comparten su base.

## Dónde corre

| Dónde | Qué muta | Umbral |
|---|---|---|
| Local, a mano | La clase que pasas con `--class` | — |
| `tests.yml` (PR y `main`) | Nada: la mutación no bloquea el merge | — |
| `mutation.yml`, domingo 23:00 (CDMX) y a mano | Las clases de `mutates()` sin controladores, FormRequests ni notificaciones, un módulo a la vez (`composer mutate:critical`) | 95% por módulo |

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

El 95% es el score de las clases críticas (97,44% el 1 nov 2026) redondeado hacia abajo: detecta regresiones sin fallar hoy.
Esa medición tuvo 7432 mutantes probados, ninguno escapó, y 195 "uncovered" (líneas que la cobertura no marca, como el
`mkdir` de `UpdateGeoIp`): son los que bajan el score. Un mutante que escapa es un test que falta.
