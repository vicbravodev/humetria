# Rediseño de Organigrama, Vacaciones, Clima y Pulso

Fuente de verdad del rediseño de las cuatro herramientas de personas (lienzo de Claude Design, versión 6).

- `PLAN.md`: evaluación, principios, catálogo pantalla por pantalla (sección C), kit (D) e implementación (F).
- `ORG-V2.md`: especificación del organigrama v2 (árbol con React Flow, edición, lista, móvil).
- `research.md`: patrones de UX investigados.
- `BRIEF-COMUN.md` y `defects.md`: brief común del dibujo y defectos verificados con sus datos de ejemplo.
- `tablero/`: los boards "después" (`.dc.html`, una pantalla cada uno) y `canvas.json`. Cada board se renderiza con
  `herramientas/render.mjs` (Playwright; ver su cabecera: ajusta `ROOT` a una copia de `tablero/` con el runtime del lienzo).
  Los datos de ejemplo son ilustrativos; las reglas de privacidad del diseño son las del repo.
