## Qué feature agrega

<!-- Un PR = el trabajo que se pidió, completo. Lista los comportamientos que trae; cada uno es un commit. -->

## Por qué

## Cómo se probó

<!-- RED: el test que falló primero y por qué falló. GREEN: el comando y su resultado. -->

## Checklist

- [ ] Un solo trabajo, completo; el PR se puede revertir solo, sin dejar código a medias.
- [ ] Un commit por comportamiento, cada uno con los tests en verde.
- [ ] Test primero (RED → GREEN). Ningún test se aflojó, se saltó ni se borró.
- [ ] `composer ci:check` en verde (incluye cobertura ≥ 90 % y `tests/Browser`); la mutación ≥ 85 % la corre el CI.
- [ ] Sin dependencias nuevas, o aprobadas explícitamente.
- [ ] Migraciones con `down()` y seguras sobre datos existentes (o no aplica).
- [ ] Tenancy: los modelos nuevos con `tenant_id` tienen su test de aislamiento A/B, por consulta y por URL (404) (o no aplica).
- [ ] Privacidad: ninguna respuesta de Guía II/III ni nada que la ligue a un empleado en logs, bitácora ni props de RH (o no aplica).
- [ ] Rutas nuevas con nombre y `auth`, o en la lista pública con su razón.
- [ ] Páginas nuevas con su test `visitPage()`; textos de UI en español (es-MX).
- [ ] Acciones que cambian estado escriben log; las sensibles, además, van a la bitácora.

## Riesgos y pendientes

<!-- Lo que este PR no cubre a propósito y dónde se va a cubrir. -->
