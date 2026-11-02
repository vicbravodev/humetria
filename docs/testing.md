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
composer mutate:critical                                                 # las clases de lógica de negocio, ~6 min; CI le pone --min=95
composer mutate:full                                                     # las 230 de mutates(), --min=95
```

No corras la mutación al mismo tiempo que `composer test`: comparten la base `testing`.

## Dónde corre

| Dónde | Qué muta | Umbral |
|---|---|---|
| Local, a mano | La clase que pasas con `--class` | — |
| `tests.yml` (PR y `main`) | Nada: la mutación no bloquea el merge | — |
| `mutation.yml`, domingo 23:00 (CDMX) y a mano | Las clases de `mutates()` sin controladores, FormRequests ni notificaciones (`composer mutate:critical`) | 95% |

Si la corrida semanal falla, el workflow queda en rojo y GitHub avisa al correo de quien editó el cron; no toca ningún PR.

El 95% es el score de las clases críticas (97,44% el 1 nov 2026) redondeado hacia abajo: detecta regresiones sin fallar hoy.
Esa medición tuvo 7432 mutantes probados, ninguno escapó, y 195 "uncovered" (líneas que la cobertura no marca, como el
`mkdir` de `UpdateGeoIp`): son los que bajan el score. Un mutante que escapa es un test que falta.
