Nivel de riesgo psicosocial (Nulo, Bajo, Medio, Alto, Muy alto) como medidor de 5 segmentos más etiqueta.

**Recibe:** `level` (`nulo`, `bajo`, `medio`, `alto`, `muy-alto`) y `prefix` opcional (“Riesgo” → “Riesgo medio”).

- El nivel se comunica tres veces: el número de segmentos llenos, el texto y el color, nunca solo con color.
- Usa los tokens `risk-*`: segmento ≥ 3:1 sobre `card`, texto ≥ 4.5:1 sobre su fondo, en ambos modos.
- Jade para Nulo y Bajo, arena para Medio y terracota para Alto y Muy alto: sin rojos ni verdes de semáforo.
