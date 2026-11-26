---
name: Humetría
description: Herramientas de RH claras, cercanas y rigurosas, con un solo acento jade.
colors:
  primary: "#0B7A6F"
  primary-dark: "#3CCFBD"
  background: "#FBFDFC"
  foreground: "#14211F"
  card: "#FFFFFF"
  secondary: "#EDF3F1"
  muted: "#F1F5F4"
  muted-foreground: "#56635F"
  accent: "#E5F1EE"
  border: "#DCE5E2"
  input: "#7E8C88"
  destructive: "#B0463C"
  warning-bg: "#F6EEDC"
  warning-fg: "#7A5714"
  risk-nulo: "#5A9C91"
  risk-bajo: "#2B7F74"
  risk-medio: "#A47F2C"
  risk-alto: "#BC6A48"
  risk-muy-alto: "#9A3B26"
  chart-sand: "#A08A70"
  chart-earth: "#5C5248"
  background-dark: "#0F1716"
  card-dark: "#141E1C"
typography:
  display:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "36px"
    fontWeight: 600
    lineHeight: "44px"
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "30px"
    fontWeight: 600
    lineHeight: "38px"
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "20px"
    fontWeight: 600
    lineHeight: "28px"
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: "24px"
  question:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "20px"
    fontWeight: 500
    lineHeight: "30px"
  label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 500
    lineHeight: "20px"
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "14px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  section: "48px"
  touch-min: "44px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.card}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    height: "44px"
    padding: "0 20px"
  button-outline:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.md}"
    height: "44px"
    padding: "0 20px"
  card:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.xl}"
    padding: "24px"
  input:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    height: "44px"
    padding: "0 16px"
  badge:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.muted-foreground}"
    rounded: "{rounded.full}"
    height: "26px"
    padding: "0 10px"
  sidebar-item-active:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.lg}"
    height: "44px"
---

# Design System: Humetría

## 1. Overview

**Creative North Star: "La mesa de trabajo clara"**

El panel es una mesa bien iluminada donde RH ve de un vistazo qué aplicaciones requieren atención y abre cada una para actuar. La personalidad sale de PRODUCT.md: *clara, cercana y rigurosa*. Clara en el siguiente paso que pide, cercana en el trato y el lenguaje de RH en México, rigurosa en lo que comunica sobre estado, privacidad y alcance. El sistema responde con superficies casi blancas, un solo acento jade, bordes en lugar de sombras y una jerarquía que sale del espacio, no del color.

La densidad es media: una pregunta por pantalla, una acción primaria por vista, listas con filtros junto a los datos y paso del resumen al detalle (modelo Linear y Stripe Dashboard). La encuesta de la persona trabajadora es otra cosa: letra de 18 a 20 px, un solo flujo, áreas táctiles de 44 px o más, y nunca avanza sola.

El sistema rechaza lo infantil, lo frío y el gris corporativo, lo cargado, el color sin jerarquía, la plantilla genérica de SaaS con IA y el portal de trámites.

**Key Characteristics:**
- Un solo acento (`primary`), neutros con tinte jade. Con white label, la empresa cambia su logo y su acento (`BrandPalette` lo ajusta a 4.5:1); tipografía y disposición nunca cambian.
- Tarjetas con borde y sin sombra; solo lo que flota lleva sombra.
- Riesgo en tres señales: medidor de 5 segmentos, texto y color sobrio.
- Figtree 400/500/600, cuerpo de 16 px, sin cursivas ni pesos 700+.
- Movimiento que confirma lo hecho, máximo 300 ms; reducido respeta `prefers-reduced-motion`.
- Claro y oscuro con la misma jerarquía.

## 2. Colors

Una paleta jade sobria: neutros con un tinte casi imperceptible y un solo acento que se reserva para la acción, la selección y el foco.

### Primary
- **Jade de Humetría** (#0B7A6F claro / #3CCFBD oscuro): botón primario, opción elegida, avance, enlaces y anillo de foco. 5.1:1 sobre el fondo en claro, 9.4:1 en oscuro. Sobre él, texto blanco en claro y #0F1716 en oscuro.

### Neutral
- **Aire jade** (#FBFDFC): fondo de página, casi blanco sin llegar al blanco clínico.
- **Hoja** (#FFFFFF): tarjetas, tablas y la hoja de la encuesta; se separa del fondo con borde.
- **Tinta del logo** (#14211F): texto principal, 16:1 o más.
- **Tinta suave** (#56635F): texto secundario (fechas, ayudas, metadatos), ≥ 4.5:1 sobre todas las superficies.
- **Neblina** (#F1F5F4) y **Secundario** (#EDF3F1): encabezados de tabla, pistas de progreso, chips neutros.
- **Acento tenue** (#E5F1EE): hover y elemento activo en menús y filas; no es un segundo acento.
- **Filo** (#DCE5E2): divisores y contorno de tarjetas, decorativo.
- **Contorno de control** (#7E8C88): borde de inputs, select y botón outline, ≥ 3:1.
- Oscuro: fondo #0F1716, tarjeta #141E1C, texto #F1FAF8.

### Status
- **Terracota** (#B0463C claro / #EC8F84 oscuro): error y acciones destructivas.
- **Arena de aviso** (#F6EEDC fondo, #7A5714 texto): avisos.
- **Escala de riesgo**, desaturada y sin semáforo: Nulo #5A9C91, Bajo #2B7F74 (jades), Medio #A47F2C (arena), Alto #BC6A48 y Muy alto #9A3B26 (terracotas). Etiqueta con texto `risk-*-fg` sobre `risk-*-bg`.
- **Gráficas:** jade en tres valores más arena (#A08A70) y tierra (#5C5248). Con más de 5 series, se agrupa.

### Named Rules
**The One Voice Rule.** `primary` es el único acento. `brand-soft` y `brand-deep` viven solo dentro del símbolo del logo, nunca como texto, fondo ni serie.

**The Status Is Earned Rule.** Terracota, arena y jade de riesgo solo aparecen sobre información que lo amerita. Si una pantalla tiene color de estado sin un dato que lo justifique, sobra.

**The Never Color Alone Rule.** Ningún estado ni nivel se comunica solo con color: va con texto, posición o medidor.

## 3. Typography

**Display, Body y Label:** Figtree (con ui-sans-serif, system-ui, sans-serif). Una sola familia, la del logotipo.

**Character:** geometría amable y moderna, sin pesos pesados que suenen a anuncio. La jerarquía sale del tamaño y de 400/500/600, con razón ≥ 1.25 entre pasos.

### Hierarchy
- **Display** (600, 36/44, -0.01em): una vez por pantalla, bienvenida o portada de reporte.
- **Headline** (600, 30/38, -0.01em): título de página.
- **Title** (600, 20/28): título de tarjeta y de diálogo; h2 a 24/32 para secciones.
- **Body** (400, 16/24): texto por defecto e inputs (16 px evita el zoom de iOS). Texto corrido con tope ≈ 620 px (65 a 75ch).
- **Question** (500, 20/30): enunciado de cada pregunta de la encuesta; opciones en 18/28.
- **Label** (500, 14/20): etiquetas de campo, botones y metadatos; `caption` es la única que va en mayúsculas.

### Named Rules
**The Tabular Rule.** Cifras de KPI y de tablas llevan `font-variant-numeric: tabular-nums` y se alinean a la derecha.

**The One Family Rule.** Nada de segunda familia, cursivas ni pesos 700+.

## 4. Elevation

Casi plano. La jerarquía la dan el espacio y los bordes; hay dos niveles de profundidad y nada más. En oscuro la sombra se pierde, así que la profundidad sale de un filo superior iluminado y de un marco más oscuro que el panel.

### Shadow Vocabulary
- **Raised** (`box-shadow: 0 1px 2px rgb(20 33 31 / 5%), 0 4px 16px -6px rgb(20 33 31 / 8%)`): solo el panel de la página, que se levanta sobre el marco del sidebar. En oscuro: `inset 0 1px 0` a 5 %.
- **Float** (`box-shadow: 0 2px 4px rgb(20 33 31 / 4%), 0 12px 32px -8px rgb(20 33 31 / 16%)`): lo que flota sobre la página, como popover, select abierto, diálogo, tooltip y toast.

### Named Rules
**The Border Is Enough Rule.** Botones, inputs y tarjetas no llevan sombra. Si una tarjeta necesita sombra para separarse, falta espacio o borde.

## 5. Components

### Buttons
- **Shape:** esquinas suaves (8px), 44 px de alto; `sm` mide 36 px a la vista con área táctil de 44.
- **Primary:** fondo `primary`, texto blanco, padding 20 px. Uno por vista o tarjeta.
- **Hover / Focus / Press:** el hover oscurece el fondo 12 %; foco con anillo sólido de 2 px en `ring` con 2 px de separación; al presionar baja a `scale(0.97)`.
- **Outline / Ghost:** `outline` usa el borde de control (#7E8C88). Las acciones secundarias nunca llevan relleno.
- **Destructive:** solo para lo irreversible, siempre detrás de un diálogo.
- Texto en verbo más objeto: «Enviar recordatorio», nunca «Aceptar».

### Cards / Containers
- **Corner Style:** 14px (`rounded-xl`, la utilidad `card`).
- **Background:** `card`, borde `border`, sin sombra.
- **Internal Padding:** 24 px; separación entre tarjetas de 16 o 24 px.
- **Rule:** nunca tarjetas dentro de tarjetas. Pocas entidades vivas con estado y acción propios van en tarjetas; muchas y comparables van en tabla `muted`.

### Inputs / Fields
- **Style:** fondo `card`, contorno #7E8C88 (≥ 3:1), 44 px de alto, texto de 16 px, radio 8px.
- **Focus:** borde `ring` más anillo sólido de 2 px con separación.
- **Error:** borde de 2 px en terracota (el segundo píxel es sombra interior, el campo no cambia de tamaño), mensaje que dice cómo corregir sin culpar.

### Navigation
- **Sidebar:** marco de la app; la página se levanta en un panel con radio 14px y Raised. Arriba la marca de la empresa (white label) y su tarjeta; los grupos son familias plegables con ícono (Cumple la norma, Escucha a tu gente, Ordena tu operación, Administración).
- **Activo:** fondo `card` con anillo de 1 px en `sidebar-border`, texto en 500, ícono en `sidebar-primary`. Se pliega a íconos (⌘B).
- **Barra superior:** fija al desplazar, con divisor al pasar contenido por debajo; migas a la izquierda, ⌘K y tema a la derecha.
- **Celular:** barra inferior flotante (Panel, Normas, Quejas, Más); «Más» abre el sidebar como `Sheet`. El portal de la persona trabajadora tiene la suya (Inicio, Marcar, Pendientes, Cuenta).

### Badges
- Píldora de 26 px con relleno suave y un punto del color del texto; el texto siempre dice el estado. Variantes: default, secondary, success, warning, destructive, outline.

### RiskLevel (componente firma)
Medidor de 5 segmentos llenos según el nivel, más la etiqueta escrita («Riesgo medio»), más color. Tres señales redundantes, legible en impresión en blanco y negro. Etiqueta con fondo tintado, nunca un bloque sólido terracota.

### Survey Shell (componente firma)
Hoja de la encuesta con radio 14px, avance visible, aviso de confidencialidad antes de la primera pregunta, en cabecera y al pie. Trata de «usted», opciones de 18 px con 12 px entre ellas, y no avanza sola.

## 6. Do's and Don'ts

### Do:
- **Do** poner el siguiente paso de cada pantalla en un solo botón primario `primary` de 44 px.
- **Do** mostrar el estado real de cada aplicación primero, y abrir el detalle desde él.
- **Do** decir la confidencialidad antes de la primera pregunta y prometer solo lo que el sistema garantiza.
- **Do** usar `RiskLevel` para todo nivel de riesgo, con medidor, texto y color.
- **Do** usar `shadow-float` solo en lo que flota y `shadow-raised` solo en el panel.
- **Do** mantener texto ≥ 4.5:1, controles ≥ 3:1 y áreas táctiles ≥ 44 px, en claro y oscuro.
- **Do** escribir panel en tuteo, encuesta en «usted», es-MX, sin signos de exclamación ni emoji.

### Don't:
- **Don't** verse infantil: ni burbujas, ni radios mayores a 14 px en contenedores, ni ilustraciones juguetonas.
- **Don't** verse frío ni gris corporativo: ningún gris puro, todo neutro lleva el tinte jade.
- **Don't** cargar la pantalla: más de 3 KPIs, o más de una acción primaria por vista.
- **Don't** usar color sin jerarquía: ningún segundo acento, ningún color de estado sin un dato que lo justifique.
- **Don't** hacer plantilla genérica de SaaS con IA: nada de titulares válidos para cualquier empresa, rejillas de tarjetas idénticas para cada módulo, etiquetas decorativas ni mockups que no muestren el producto.
- **Don't** hacer portal de trámites: nada de menús que reflejen la estructura del sistema, formularios que piden todo de golpe ni lenguaje normativo donde la persona necesita una instrucción sencilla.
- **Don't** usar rojo ni verde de semáforo, ni comunicar un nivel solo con color.
- **Don't** usar `border-left` o `border-right` mayor a 1px como acento de tarjeta o alerta, texto con degradado, glassmorphism decorativo ni tarjetas dentro de tarjetas.
- **Don't** animar propiedades de layout, usar rebotes ni pasar de 300 ms (salvo el informe al abrirse); nunca avanzar la encuesta sola.
