# Humetría · paquete del rediseño para desarrollo

La fase 0 (auditoría) ya está hecha en `PREGUNTAS-FRONT-BACK.md`: falta contestar su columna "Decisión".

**Decisión del 4 nov 2026: un PR por módulo**, no una rama `rediseno-v2` con un commit por fase. Dentro de cada PR manda
`CLAUDE.md`: TDD, un commit por comportamiento, y el PR se abre cuando se pide.

El design system no viene aquí: las copias de `decisiones.md`, `design-system.md` y `tokens.json` del paquete eran
idénticas a las de `docs/design-system/`, que es la fuente.

| Archivo | Para qué |
|---|---|
| `PROMPT.md` | Las instrucciones: subagentes en secuencia, fases, reglas |
| `PREGUNTAS-FRONT-BACK.md` | Lo que el back no tiene para el diseño, y viceversa. Se decide antes de construir |
| `MAPA.md` | Board → pantalla actual → fase |
| `pantallas/png/` | Cómo debe verse cada pantalla (56 capturas, algunas también en oscuro) |
| `pantallas/fuente/` | El código de cada pantalla del diseño, para copiar textos y estados. Se ve vivo con `sh ver-prototipos.sh` |
| `docs/` | Principios, permisos, ciclo de vida, cobertura y errores |

Lienzo del diseño: https://claude.ai/artifact/Mabe2mH9pPgE8FKA2BvY6Q

`pantallas/fuente/support.js` es un visor local simplificado, solo para ver los prototipos. No va a producción.
