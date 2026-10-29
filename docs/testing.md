# Mutation testing

## Convención

- Cada test de lógica de dominio declara `mutates(Clase::class)`: reglas, cálculos, máquinas de estado, límites
  (15/16, 50/51), privacidad y seguridad. No se declara en controladores, FormRequests sin reglas propias, DTOs,
  migraciones, config ni enums que solo tienen etiquetas.
- `mutates()` compara por prefijo: `mutates(Tenant::class)` también muta `TenantInvitation` y `TenantMembership`.
- En modelos, las listas que PHP resuelve al compilar (`#[Fillable]`, `$casts`, `$hidden`) llevan
  `// @pest-mutate-ignore: RemoveArrayItem` en su línea: la cobertura nunca las marca y saldrían como "uncovered".
- Mientras desarrollas, la mutación se corre en local **solo por clase**. La corrida completa vive en CI.
- Si un mutante escapa, falta un test. Si el mutante es equivalente (no cambia el comportamiento), se reporta; no se
  escribe un test artificial para matarlo.

## Comandos

```bash
composer test:fast                                                       # Tia: solo los tests que tu cambio afecta
composer mutate -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # una clase (o un namespace), 4 procesos
composer mutate:retry -- --class='App\Modules\Tenancy\Actions\AcceptInvitation' # lo que escapó, primero
composer mutate:full                                                     # todo, --min=95: es para CI
```

No corras la mutación al mismo tiempo que `composer test`: comparten la base `testing`.

## Dónde corre

| Dónde | Qué muta | Umbral |
|---|---|---|
| Local, a mano | La clase que pasas con `--class` | — |
| `tests.yml`, en cada PR | Las clases cuyo `mutates()` alcanza un archivo del diff | 85% |
| `tests.yml`, en `main` | Todas | 85% |
| `mutation.yml`, cada noche y a mano | Todas (`composer mutate:full`) | 95% |

El 95% es el score de octubre de 2026 redondeado hacia abajo: detecta regresiones sin fallar hoy. Los 23 mutantes
"uncovered" que quedan son constantes que la cobertura no ve (`RESERVED_SLUGS`, `TRIAL_DAYS`, `VALID_DAYS`); su
comportamiento lo cubren tests explícitos (`ReservedSlugsTest`, `TenantStatusTest`, `InvitationStatusTest`).
