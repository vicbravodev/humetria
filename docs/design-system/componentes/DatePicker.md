Selectores de fecha, periodo, mes y hora (`DatePicker`, `DateRangePicker`, `MonthPicker`, `TimePicker`). Reemplazan
a los `<input type="date|month|time">` del navegador, que se ven distinto en cada sistema, no respetan el tema, no se
borran igual y no leen lo que se pega. `tests/Unit/NativeDatePickersTest.php` prohíbe los nativos.

**Regla común:** se escribe o se elige, y al servidor siempre llega su forma (`Y-m-d`, `Y-m` o `HH:mm`).

- Se ven como `Input` dentro de un `InputGroup`: 44 px, borde `input`, el contorno de foco rodea texto y botones; los
  botones de ícono (abrir, borrar) miden 44 × 44 y se nombran para el lector de pantalla.
- **Escribir o pegar** en los formatos de México (analizador propio, sin dependencias: `lib/date-text.ts`,
  `lib/time-text.ts`): `07/10/2018`, `7-10-18`, `7.10.2018`, `07102018`, `7 oct 2018`, `7 de octubre de 2018`,
  `2018-10-07`; el mes como `10/2026`, `102026`, `oct 2026`, `2026-10`; la hora como `7:30`, `730`, `7`, `7:30 pm`,
  `12 am`. Los espacios de más y los acentos no importan. Un año de dos dígitos cae en este siglo hasta veinte años
  adelante y, después, en el pasado (`95` → 1995).
- Nada se agrega mientras se teclea (una barra metida a la mitad rompería `2018-10-07`); una tira de dígitos completa sí
  toma su forma (`07102018` → `07/10/2018`, `0730` → `07:30`). Al salir del campo se reescribe como `dd/mm/aaaa`,
  `mm/aaaa` o `HH:mm`.
- Un texto que no es fecha, o queda fuera de `min`/`max`, se dice debajo al salir del campo, en `destructive` y unido al
  campo con `aria-describedby`: «No es una fecha: escríbela como 07/10/2018.», «Elige una fecha desde el 10/11/2026.».
  El valor enviado queda vacío; el portal pasa `voice="usted"`.

## DatePicker

**Recibe:** `id`, `name`, `defaultValue` o `value` + `onChange` (`Y-m-d`), `min`, `max`, `required`, `invalid`,
`describedBy`, `prompt` (nombra el calendario; el portal pasa el suyo) y `voice`.

- Campo de texto con `dd/mm/aaaa` de ejemplo, botón «Borrar la fecha» (solo si no es `required` y hay fecha) y botón
  «Abrir el calendario».
- Un clic en el campo abre el calendario sin quitarle el foco, para seguir escribiendo; el botón, `ArrowDown` o
  `Alt+ArrowDown` llevan el foco al día (el elegido, hoy o el primero permitido). `Escape` o elegir un día lo cierran y
  devuelven el foco al campo; tocar o tabular fuera lo cierra sin robarlo.
- El encabezado del `Calendar` trae menús de mes y año (de 100 años atrás a 20 adelante, o acotados por `min`/`max`):
  una fecha de ingreso de 2018 se alcanza en dos toques. Semanas desde el lunes, días de 44 px, hoy subrayado en
  `primary`, el elegido con fondo `primary`; los días fuera de `min`/`max` quedan atenuados.
- **En el teléfono** (< 768 px) el campo no abre el teclado: abre una hoja inferior (`Sheet`) con un campo para escribir
  la fecha, el calendario, «Borrar» (si no es requerido) y «Listo». El foco entra al día, no al texto, para que el
  teclado no tape el calendario.
- Un campo que no tiene `<label>` que lo apunte toma `prompt` como nombre.

## DateRangePicker

**Recibe:** `id` (las puntas son `${id}-from` y `${id}-to`), `label` (leyenda del grupo), `value` + `onChange`
(`{ from, to }` en `Y-m-d`), `min`, `max`, `shortcuts` (`periodShortcuts()` da «Esta semana», «Semana pasada» y
«Último mes», los del reporte de asistencia) y `names` si va en un formulario.

- Un solo campo con dos textos («Desde» – «Hasta») y un calendario de rango: el primer toque fija el inicio y deja abierto
  el fin, el segundo cierra el periodo (si cae antes, se voltea) y cierra el calendario. Abrir desde «Hasta» con un
  inicio ya puesto fija el fin.
- Lo escrito se toma **al salir del campo** (o con `Enter`), no con cada tecla: un filtro pregunta al servidor una vez.
- Los atajos van a la izquierda del calendario; en el teléfono, arriba, en la hoja inferior.
- El inicio nunca queda después del fin: cada punta usa a la otra como límite.

## MonthPicker

**Recibe:** `id`, `name`, `defaultValue` o `value` + `onChange` (`Y-m`), `min`, `max`, `invalid`, `describedBy`.

- Texto `mm/aaaa` y botón «Abrir los meses»: una cuadrícula de 3 × 4 meses de 44 px con «Año anterior» y «Año
  siguiente». Las flechas recorren los meses; el elegido va con fondo `primary` y `aria-pressed`.
- En el teléfono, la cuadrícula sale en la hoja inferior.

## TimePicker

**Recibe:** `id`, `name` (opcional), `defaultValue` o `value` + `onChange` (`HH:mm`, 24 h), `step` (minutos que mueven
las flechas; 15), `required`, `invalid`, `describedBy`.

- Solo texto, con reloj de ícono y `hh:mm` de ejemplo; en el teléfono abre el teclado numérico (`inputmode="numeric"`).
- `ArrowUp`/`ArrowDown` mueven un cuarto de hora y dan la vuelta a la medianoche; sin hora, empiezan en 08:00.
- Con `name`, el formulario envía el texto mismo: al salir del campo o con `Enter` ya quedó como `HH:mm`.

## Movimiento y pruebas

- Al cambiar de mes las semanas entran de lado (200 ms) y las anteriores salen hacia el otro (150 ms); con movimiento
  reducido solo se desvanecen.
- Pruebas: Vitest del analizador y las conversiones (`tests/js/lib/date-text.test.ts`, `time-text.test.ts`,
  `date-range.test.ts`, `calendar-date.test.ts`) y de navegador a escritorio y 375 px (`tests/Browser/DatePickerTest.php`,
  `DateRangePickerTest.php`, `MonthPickerTest.php`, `TimePickerTest.php`).
