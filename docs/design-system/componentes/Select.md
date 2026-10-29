Lista desplegable de opciones; en la app usa el `Select` de shadcn (Radix) con estos mismos estilos, y el `<select>` nativo en la encuesta móvil.

**Recibe:** `options` (`{value, label}`), `placeholder` y las props nativas.

- Mismo alto, borde y foco que `Input`.
- Más de 7 opciones o búsqueda: usa `Combobox`.
- El menú abierto usa `popover` y `shadow-float`, crece desde el control (200 ms al abrir, 100 ms al cerrar) y la flecha gira media vuelta.
- Opciones de 40 px en texto `body`; la elegida va en 500 con palomita en `primary`.
- La captura es del diseño original: todavía no hay una pantalla que use `Select`. Al usarlo en una, se toma la captura de ahí.
