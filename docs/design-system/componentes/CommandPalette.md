Paleta de comandos del panel: se abre con ⌘K (Ctrl+K) o con el botón “Buscar” de la barra superior.

**Ofrece** (`lib/palette-commands.ts`): las páginas que la persona puede abrir (las de Plataforma solo al superadmin), Configuración, los tres temas y plegar o desplegar el menú.

- Al escribir filtra por nombre y por palabras clave, sin distinguir mayúsculas ni acentos (`lib/commands.ts`). Sin coincidencias dice “Sin resultados”.
- Flechas, Inicio y Fin mueven la opción activa; Enter la ejecuta; Esc cierra. El puntero también la marca.
- Es un `combobox` con su `listbox`: el lector de pantalla anuncia la opción activa con `aria-activedescendant`.
- Abre arriba (18 % de la ventana), sin animación: se usa muchas veces al día y el movimiento la haría sentir lenta.
