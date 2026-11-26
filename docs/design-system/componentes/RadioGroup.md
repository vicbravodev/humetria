Grupo de opciones excluyentes en formularios del panel. En la app no hay un `RadioGroup` de shadcn: son radios nativos
dentro de una de estas piezas de `components/humetria/`, así las flechas se mueven entre ellos y el formulario los envía
tal cual.

- **`OptionRows`**: filas de 52 px, radio para una opción o casilla para varias.
- **`ChoiceField`**: tarjetas para una lista corta y fija; grande, su leyenda es la pregunta de la página.
- **`PillChoice`**: pastillas en fila para una pregunta corta (Sí / No).

- La elegida cambia su borde y su fondo al acento; el área táctil mide 44 px o más.
- En la encuesta no uses estas piezas: usa `SurveyQuestion`, con filas grandes.
