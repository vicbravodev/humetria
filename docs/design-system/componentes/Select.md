Lista desplegable de opciones; en la app usa el `Select` de shadcn (Radix) con estos mismos estilos, y el `<select>` nativo en la encuesta móvil.

**Recibe:** `options` (`{value, label}`), `placeholder` y las props nativas.

- Mismo alto, borde y foco que `Input`.
- Más de 7 opciones o búsqueda: usa `Combobox`.
- El menú abierto usa `popover` y la única sombra permitida, `shadow-float`.
