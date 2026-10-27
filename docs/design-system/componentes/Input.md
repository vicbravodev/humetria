Campo de texto de una línea; equivale a `Input` de shadcn.

**Recibe:** las props nativas de `<input>`.

- Siempre 16 px de texto (evita el zoom automático de iOS) y 44 px de alto.
- Contorno en `input` (3.5:1 sobre `card`), fondo `card`, foco con `ring` sólido.
- Usa el `type` correcto (`email`, `tel`) para que el teclado móvil ayude.
