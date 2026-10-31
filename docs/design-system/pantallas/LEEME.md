# Humetría · rediseño de pantallas

Propuesta del 30 oct 2026. 29 pantallas con el design system de Humetría (jade #0B7A6F / #3CCFBD, Figtree 400/500/600, radio 10 px).

## Carpetas
- html-claro/ y html-oscuro/: cada pantalla como HTML suelto. Ábrelo en el navegador (carga Figtree de Google Fonts).
  Ojo: en Ajustes y Encuesta-Movil, que son interactivas en el canvas, aquí se ven todos sus estados apilados.
- fuente-claude-design/: los .dc.html del canvas y su canvas.json, tal como están publicados.
- capturas/: PNG en tema claro, a 1440 px.

## Mapa de pantallas
| Archivo | Pantalla |
|---|---|
| Panel (Main en la fuente) | Panel de inicio de RH |
| Paleta | Búsqueda global ⌘K |
| NOM035, NOM035-Nueva | Centros a evaluar y asistente de nueva evaluación |
| Eval-Borrador, Eval-Activa, Eval-Cerrada | Evaluación en cada estado |
| Guia-I-Acontecimiento, Canalizacion | Guía I tras un acontecimiento y canalización |
| Empleados, Empleados-Alta, Empleados-CSV | Plantilla, alta en panel lateral, carga CSV en 3 pasos |
| Centros, Usuarios, Usuarios-Invitar, Ajustes | Empresa y cuenta |
| Encuesta-Movil, Encuesta-Escritorio | Encuesta del trabajador |
| Login, Olvide, Restablecer, Invitacion, Confirmar | Acceso |
| Error-403, Error-404, Enlace-Vencido | Errores |
| Consola-Empresas, Consola-Solicitudes | Superadmin |
| Landing | Marketing |

## Para implementar (Tailwind v4 + shadcn)
- Las variables CSS (--primary, --mfg, --border, --r-*) corresponden 1:1 a los tokens de shadcn del design system: mfg = muted-foreground, sec = secondary, pop = popover, dest = destructive.
- El medidor de arco (forma de la "h" del logo) es un SVG con el path "M10 49V33A22 22 0 0 1 54 33V49", pathLength=100 y stroke-dashoffset = 100 − porcentaje.
- Funciones nuevas que piden backend: recordar por área, respuestas por día, estimado de cierre, registro de canalización, registro de consultas en canalización e invitaciones pendientes.
- Los datos entre corchetes y las solicitudes de demo son de ejemplo.
