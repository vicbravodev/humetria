Campo de teléfono de cualquier país, México primero (`components/humetria/phone-input.tsx`). Con su etiqueta y su error:
`PhoneField` (`components/phone-field.tsx`), que se usa como `FormField`.

**Recibe:** `id`, `name`, `defaultValue` (un número guardado en E.164), `required`, `invalid`, `describedBy`,
`autoComplete` y `className` (para una caja más alta, como la del formulario de la landing).

- Es un `InputGroup`: a la izquierda la bandera y el prefijo del país (`+52`), a la derecha el número. Se ve y se enfoca
  como un `Input`; el selector de país tiene su propio contorno de foco.
- El país es un `<select>` nativo encima de la bandera, invisible: se busca tecleando y en el celular abre el selector
  del sistema. México viene elegido (`DEFAULT_COUNTRY` en `lib/phone.ts`; el servidor asume el mismo,
  `App\Support\PhoneNumber::DEFAULT_REGION`).
- La bandera es un SVG de `country-flag-icons`, un archivo por país que se carga al elegirlo (el emoji no se pinta en
  Windows). Va en `<img alt="">`: es decoración, el país lo dice el selector.
- El número se enmascara mientras se escribe, como se escribe en su país (`55 1234 5678`, `(415) 555-2671`), con
  `AsYouType` de `libphonenumber-js`. Borrar un espacio borra el dígito de antes.
- Acepta pegar en cualquier formato: con `+52`, con el `1` o el `044`/`045` de antes (se quitan: desde 2019 un celular
  mexicano es +52 y 10 dígitos), o con el código de otro país, y entonces el selector cambia a ese país.
- Lo que manda el formulario es un campo oculto con `name`: el número en E.164 (`+525512345678`), o lo escrito si no es
  un número, para que el servidor diga qué está mal. La caja visible no tiene `name`; el foco tras un error va a ella.
- En el servidor, la regla `App\Support\PhoneNumber` lo normaliza y rechaza lo que no es un número válido de su país;
  la base guarda E.164 con un `CHECK`. En pantalla se muestra con `readablePhone()` (México sin `+52`).
- Al reiniciarse el formulario (`resetOnSuccess`), vuelve vacío y a México.
- La lista de países (nombres en español de `Intl`) llega al hidratar: el servidor de SSR solo pinta el país elegido,
  así no hay diferencias de texto entre Node y el navegador.
