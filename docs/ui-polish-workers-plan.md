# Plan de ejecución: pulido de pantallas de la persona trabajadora

Origen: `docs/ui-polish-workers.md` (38 hallazgos, revisión cerrada el 2026-11-17) y el lienzo
https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt. Sesión maestra: esta. Nadie más toca `ui/personas`.

## Base y ramas
- `ui/personas` nace de `ui/pulido-de-interfaz` (PR #123, 7dff7b5e): ya trae `punch-done`, `punch-view`, `step-story` de quejas y los pickers. Cuando #123 entre a `main`, `ui/personas` se rebasa sobre `main`.
- Cada subagente corta `ui/<tema>` desde `ui/personas` **en su propio worktree** (`composer worktree:setup`; base de pruebas propia). La maestra integra con `git merge --no-ff` en `ui/personas`, **una rama a la vez**, y corre solo los tests de lo integrado.
- Un PR = un trabajo: `ui/personas` es el PR de pantallas de personas. **Zonas horarias es otro trabajo (otro PR, `feat/zonas-horarias`, desde `ui/pulido-de-interfaz`)**; no se apila.
- Nadie hace commit en `main`, push ni PR sin que Victor lo pida. Commits a su nombre, sin atribución.

## Reglas para todos los subagentes (van en cada brief)
1. Solo tests acotados (`php artisan test --compact --filter=…` o ruta; Vitest del archivo); Pint, PHPStan y `npm run check`/`types:check` sobre lo cambiado. **Nunca la suite completa**; el CI la corre.
2. TDD: el test falla primero por la razón correcta. Prohibido aflojar/borrar tests, `@ts-ignore`, `as`, `!`, `any`, `@phpstan-ignore`.
3. Solo toca los archivos de su área; si necesita uno ajeno, se detiene y lo reporta.
4. Revisar en navegador claro, oscuro y 375 px (el lienzo manda; la app de Sail sirve el checkout principal, no el worktree: usar `vp`/build del worktree).
5. Sin dependencias nuevas. Textos en es-MX, «usted».
6. Privacidad: nada liga a una persona con una respuesta anónima; props de Inertia sin respuestas individuales.

## Olas
| Ola | Rama | Hallazgos | Modelo | Por qué |
|---|---|---|---|---|
| 1 (paralelo) | `ui/marco-persona` | 1, 2, 3, 4, 8, 29, 30, 33 (marco, barra inferior, estados, movimiento, 5xx) | Opus | cimiento: `person-frame`, `worker-access`, `error-page`, `demo-mark` |
| 1 (paralelo) | `ui/quejas` | 35 | Sonnet | `complaints-public/*`, su propio marco; sin solape |
| 1 (paralelo) | `feat/zonas-horarias` | 23 (+ 26 aceptación entre centros) | Opus | backend; otro PR |
| 2 (paralelo, tras integrar marco) | `ui/encuestas` | 5, 6, 7, 9, 10, 11, 12 | Opus | `campaigns/survey`, `engagement/survey` (un solo cierre) |
| 2 | `ui/ley-silla` | 13, 14, 15, 34 (+ no guardar el puesto) | Opus | privacidad: `Seating`, `ReportSeat` |
| 2 | `ui/portal` | 31, 32, 37 (+ vacaciones, política, reportes por enlace) | Sonnet | `leave`, `disclosure`, reportes |
| 3 (secuencial, tras zonas en `main`) | `ui/asistencia-persona` | 16–22, 24, 27, 38 | Opus | usa horas por centro; toca `punch-*` |
| 4 (tras prueba del QR en teléfono) | `ui/checador` | 25, 26, 28 | Opus | kiosco y QR de marca |

Por qué en ese orden: el marco define la acción fija y la barra inferior que usan las demás pantallas; asistencia depende de zonas (el spec lo exige) y comparte `punch-view` con ellas.

## Estado al abrir el PR (2026-11-17)
- Hechas y en `ui/personas`: marco (1, 2, 3, 4, 8, 29, 30), quejas (35), encuestas (5–7, 9–12), Ley Silla (13–15, 34), portal (31–33, 37), asistencia de la persona (16–22, 24, 27, 38) y la guardia de privacidad del hallazgo 3.
- Zonas horarias (23, y la parte de servidor del 26) entró aparte como PR #124.
- Pendiente a propósito (ola 4): checador y QR de marca (25, 28), a la espera de la prueba de escaneo en un Android de gama baja; y el hallazgo 26 en la demo (40 personas repartidas en 4 centros).
- Pendiente de Victor: en la pregunta abierta de las encuestas «no escriba su nombre» sale dos veces (el aviso del banco y el nuevo); se dejó el del banco porque apunta al canal de quejas.
