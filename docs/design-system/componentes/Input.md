Campo de texto de una línea; equivale a `Input` de shadcn.

**Recibe:** las props nativas de `<input>`.

- Siempre 16 px de texto (evita el zoom automático de iOS) y 44 px de alto.
- Contorno en `input` (3.5:1 sobre `card`), fondo `card`. Al pasar el puntero el borde se oscurece (`foreground` al 60 %); con foco, el borde pasa a `ring` y aparece el contorno sólido de 2 px con separación.
- Con error, borde `destructive` de 2 px (el segundo píxel es una sombra interior, así el campo no cambia de tamaño).
- Lo que va dentro de la caja (un prefijo como `/admin/`, el botón para ver la contraseña) usa `InputGroup`: se ve y se enfoca como un solo `Input`, con el contorno alrededor de todo.
- Usa el `type` correcto (`email`, `tel`) para que el teclado móvil ayude.
