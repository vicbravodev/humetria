# Tokens para shadcn/ui

Los nombres coinciden 1:1 con las variables CSS de shadcn/ui (Tailwind v4). Cada token indica el par contra el que se midió su contraste (WCAG 2.x) en claro y en oscuro. Texto: ≥ 4.5:1. Bordes de controles, foco y marcas con significado: ≥ 3:1. Los 49 colores pasan en los dos modos.

## Variables de shadcn

| Token | Claro | Oscuro | Par evaluado | Contraste claro / oscuro | Mínimo |
|---|---|---|---|---|---|
| `background` | `#FBFDFC` | `#0F1716` | texto `foreground` | 16.22 / 17.12 ✓ | ≥ 4.5 |
| `foreground` | `#14211F` | `#F1FAF8` | sobre `background` | 16.22 / 17.12 ✓ | ≥ 4.5 |
| `card` | `#FFFFFF` | `#141E1C` | texto `card-foreground` | 16.57 / 16.04 ✓ | ≥ 4.5 |
| `card-foreground` | `#14211F` | `#F1FAF8` | sobre `card` | 16.57 / 16.04 ✓ | ≥ 4.5 |
| `popover` | `#FFFFFF` | `#18241F` | texto | 16.57 / 15.08 ✓ | ≥ 4.5 |
| `popover-foreground` | `#14211F` | `#F1FAF8` | sobre `popover` | 16.57 / 15.08 ✓ | ≥ 4.5 |
| `primary` | `#0B7A6F` | `#3CCFBD` | como texto sobre `background` | 5.10 / 9.40 ✓ | ≥ 4.5 |
| `primary-foreground` | `#FFFFFF` | `#0F1716` | sobre `primary` | 5.21 / 9.40 ✓ | ≥ 4.5 |
| `secondary` | `#EDF3F1` | `#1E2B28` | texto | 14.75 / 13.81 ✓ | ≥ 4.5 |
| `secondary-foreground` | `#14211F` | `#F1FAF8` | sobre `secondary` | 14.75 / 13.81 ✓ | ≥ 4.5 |
| `muted` | `#F1F5F4` | `#1A2523` | texto `muted-foreground` | 5.71 / 6.80 ✓ | ≥ 4.5 |
| `muted-foreground` | `#56635F` | `#9DAEAA` | sobre `background` | 6.14 / 7.86 ✓ | ≥ 4.5 |
| `accent` | `#E5F1EE` | `#213330` | texto | 14.32 / 12.51 ✓ | ≥ 4.5 |
| `accent-foreground` | `#14211F` | `#F1FAF8` | sobre `accent` | 14.32 / 12.51 ✓ | ≥ 4.5 |
| `destructive` | `#B0463C` | `#EC8F84` | como texto sobre `card` | 5.54 / 7.16 ✓ | ≥ 4.5 |
| `border` | `#DCE5E2` | `#26332F` | decorativo, sobre `card` | 1.28 / 1.30 | — |
| `input` | `#7E8C88` | `#62756F` | borde sobre `background` | 3.43 / 3.72 ✓ | ≥ 3 |
| `ring` | `#0B7A6F` | `#3CCFBD` | foco sobre `background` | 5.10 / 9.40 ✓ | ≥ 3 |
| `chart-1` | `#0B7A6F` | `#3CCFBD` | marca sobre `card` | 5.21 / 8.81 ✓ | ≥ 3 |
| `chart-2` | `#3F9A8F` | `#2A9C8E` | marca sobre `card` | 3.37 / 5.07 ✓ | ≥ 3 |
| `chart-3` | `#1D5C55` | `#9EDDD2` | marca sobre `card` | 7.74 / 11.14 ✓ | ≥ 3 |
| `chart-4` | `#A08A70` | `#C2AE94` | marca sobre `card` | 3.30 / 7.93 ✓ | ≥ 3 |
| `chart-5` | `#5C5248` | `#8A7E70` | marca sobre `card` | 7.62 / 4.30 ✓ | ≥ 3 |
| `sidebar` | `#F5F8F7` | `#0B1211` | texto | 15.51 / 17.83 ✓ | ≥ 4.5 |
| `sidebar-foreground` | `#14211F` | `#F1FAF8` | sobre `sidebar` | 15.51 / 17.83 ✓ | ≥ 4.5 |
| `sidebar-primary` | `#0B7A6F` | `#3CCFBD` | sobre `sidebar` | 4.88 / 9.79 ✓ | ≥ 3 |
| `sidebar-primary-foreground` | `#FFFFFF` | `#0F1716` | sobre `sidebar-primary` | 5.21 / 9.40 ✓ | ≥ 4.5 |
| `sidebar-accent` | `#E5F1EE` | `#213330` | texto | 14.32 / 12.51 ✓ | ≥ 4.5 |
| `sidebar-accent-foreground` | `#14211F` | `#F1FAF8` | sobre `sidebar-accent` | 14.32 / 12.51 ✓ | ≥ 4.5 |
| `sidebar-border` | `#DCE5E2` | `#26332F` | decorativo | 1.20 / 1.44 | — |
| `sidebar-ring` | `#0B7A6F` | `#3CCFBD` | foco sobre `sidebar` | 4.88 / 9.79 ✓ | ≥ 3 |
| `brand-soft` | `#7FD3C6` | `#1E8C7F` | solo en el símbolo | — | — |
| `brand-deep` | `#064A43` | `#B8F5EC` | solo en el símbolo | — | — |

`border` y `sidebar-border` son divisores decorativos: los contornos de controles usan `input`. `brand-soft` y `brand-deep` existen para que el símbolo se pueda dibujar con tokens; nunca se usan como texto, fondo ni serie de gráfica.

## Extensiones

Fuera del set de shadcn, pero necesarias para el sistema. Se agregan al mismo `:root` y `.dark`.

| Token | Claro | Oscuro | Par evaluado | Contraste claro / oscuro | Mínimo |
|---|---|---|---|---|---|
| `on-destructive` | `#FFFFFF` | `#0F1716` | sobre `destructive` | 5.54 / 7.65 ✓ | ≥ 4.5 |
| `risk-nulo` | `#5A9C91` | `#6FB5A9` | segmento sobre `card` | 3.18 / 7.19 ✓ | ≥ 3 |
| `risk-nulo-bg` | `#E9F4F1` | `#16302B` | texto | 7.19 / 10.02 ✓ | ≥ 4.5 |
| `risk-nulo-fg` | `#0A5A52` | `#A6E6DA` | sobre `risk-nulo-bg` | 7.19 / 10.02 ✓ | ≥ 4.5 |
| `risk-bajo` | `#2B7F74` | `#3CBFAE` | segmento sobre `card` | 4.78 / 7.51 ✓ | ≥ 3 |
| `risk-bajo-bg` | `#DCEFEA` | `#123A34` | texto | 5.42 / 7.60 ✓ | ≥ 4.5 |
| `risk-bajo-fg` | `#0A6A60` | `#6FDCCB` | sobre `risk-bajo-bg` | 5.42 / 7.60 ✓ | ≥ 4.5 |
| `risk-medio` | `#A47F2C` | `#D2AE62` | segmento sobre `card` | 3.72 / 8.09 ✓ | ≥ 3 |
| `risk-medio-bg` | `#F6EEDC` | `#33291A` | texto | 5.68 / 8.87 ✓ | ≥ 4.5 |
| `risk-medio-fg` | `#7A5714` | `#E9C886` | sobre `risk-medio-bg` | 5.68 / 8.87 ✓ | ≥ 4.5 |
| `risk-alto` | `#BC6A48` | `#E08A66` | segmento sobre `card` | 3.97 / 6.49 ✓ | ≥ 3 |
| `risk-alto-bg` | `#F8E7DF` | `#3B241C` | texto | 5.37 / 7.27 ✓ | ≥ 4.5 |
| `risk-alto-fg` | `#9A4527` | `#F2A686` | sobre `risk-alto-bg` | 5.37 / 7.27 ✓ | ≥ 4.5 |
| `risk-muy-alto` | `#9A3B26` | `#F07A62` | segmento sobre `card` | 6.94 / 6.22 ✓ | ≥ 3 |
| `risk-muy-alto-bg` | `#F3D8CF` | `#4A1F18` | texto | 6.24 / 6.91 ✓ | ≥ 4.5 |
| `risk-muy-alto-fg` | `#8A2E1C` | `#F7A08C` | sobre `risk-muy-alto-bg` | 6.24 / 6.91 ✓ | ≥ 4.5 |

## Radio

`--radius: 0.625rem` (10 px). shadcn deriva `radius-sm` 6 px, `radius-md` 8 px, `radius-lg` 10 px y `radius-xl` 14 px. Botones e inputs usan `rounded-md`, tarjetas y opciones de encuesta `rounded-lg`, diálogos `rounded-xl` y radios, progreso y avatar `rounded-full`.

## Escala tipográfica (Figtree)

| Estilo | Tamaño / interlínea | Peso | Uso |
|---|---|---|---|
| `display` | 36 / 44 | 600 | Bienvenida, portada de reporte. Una por pantalla. |
| `h1` | 30 / 38 | 600 | Título de página. |
| `h2` | 24 / 32 | 600 | Sección; título de sección en la encuesta. |
| `h3` | 20 / 28 | 600 | Tarjeta, diálogo. |
| `h4` | 18 / 26 | 500 | Subtítulo en tarjeta. |
| `question` | 20 / 30 | 500 | Enunciado de pregunta de la encuesta. |
| `body-lg` | 18 / 28 | 400 | Opciones e instrucción de la encuesta. |
| `body` | 16 / 24 | 400 | Cuerpo e inputs (también en móvil). |
| `body-strong` | 16 / 24 | 500 | Botones, labels, navegación. |
| `small` | 14 / 20 | 400 | Metadatos del panel. Nunca en la encuesta. |
| `caption` | 13 / 18 | 500, +0.02em, MAYÚSCULAS | Encabezados de tabla y etiquetas de KPI. |
| `kpi` | 32 / 40 | 600, cifras tabulares | Valor de KPI. |

Figtree se carga desde Google Fonts (`wght@400;500;600`) o con `next/font/google`. No se usan otros pesos.

## `globals.css` (Tailwind v4)

```css
@import "tailwindcss";
@import "tw-animate-css";

@custom-variant dark (&:is(.dark *));

:root {
  --radius: 0.625rem; /* 10px */
  --background: #FBFDFC;
  --foreground: #14211F;
  --card: #FFFFFF;
  --card-foreground: #14211F;
  --popover: #FFFFFF;
  --popover-foreground: #14211F;
  --primary: #0B7A6F;
  --primary-foreground: #FFFFFF;
  --secondary: #EDF3F1;
  --secondary-foreground: #14211F;
  --muted: #F1F5F4;
  --muted-foreground: #56635F;
  --accent: #E5F1EE;
  --accent-foreground: #14211F;
  --destructive: #B0463C;
  --border: #DCE5E2;
  --input: #7E8C88;
  --ring: #0B7A6F;
  --chart-1: #0B7A6F;
  --chart-2: #3F9A8F;
  --chart-3: #1D5C55;
  --chart-4: #A08A70;
  --chart-5: #5C5248;
  --sidebar: #F5F8F7;
  --sidebar-foreground: #14211F;
  --sidebar-primary: #0B7A6F;
  --sidebar-primary-foreground: #FFFFFF;
  --sidebar-accent: #E5F1EE;
  --sidebar-accent-foreground: #14211F;
  --sidebar-border: #DCE5E2;
  --sidebar-ring: #0B7A6F;
  --brand-soft: #7FD3C6;
  --brand-deep: #064A43;
  --on-destructive: #FFFFFF;
  --risk-nulo: #5A9C91;
  --risk-nulo-bg: #E9F4F1;
  --risk-nulo-fg: #0A5A52;
  --risk-bajo: #2B7F74;
  --risk-bajo-bg: #DCEFEA;
  --risk-bajo-fg: #0A6A60;
  --risk-medio: #A47F2C;
  --risk-medio-bg: #F6EEDC;
  --risk-medio-fg: #7A5714;
  --risk-alto: #BC6A48;
  --risk-alto-bg: #F8E7DF;
  --risk-alto-fg: #9A4527;
  --risk-muy-alto: #9A3B26;
  --risk-muy-alto-bg: #F3D8CF;
  --risk-muy-alto-fg: #8A2E1C;
}

.dark {
  --background: #0F1716;
  --foreground: #F1FAF8;
  --card: #141E1C;
  --card-foreground: #F1FAF8;
  --popover: #18241F;
  --popover-foreground: #F1FAF8;
  --primary: #3CCFBD;
  --primary-foreground: #0F1716;
  --secondary: #1E2B28;
  --secondary-foreground: #F1FAF8;
  --muted: #1A2523;
  --muted-foreground: #9DAEAA;
  --accent: #213330;
  --accent-foreground: #F1FAF8;
  --destructive: #EC8F84;
  --border: #26332F;
  --input: #62756F;
  --ring: #3CCFBD;
  --chart-1: #3CCFBD;
  --chart-2: #2A9C8E;
  --chart-3: #9EDDD2;
  --chart-4: #C2AE94;
  --chart-5: #8A7E70;
  --sidebar: #0B1211;
  --sidebar-foreground: #F1FAF8;
  --sidebar-primary: #3CCFBD;
  --sidebar-primary-foreground: #0F1716;
  --sidebar-accent: #213330;
  --sidebar-accent-foreground: #F1FAF8;
  --sidebar-border: #26332F;
  --sidebar-ring: #3CCFBD;
  --brand-soft: #1E8C7F;
  --brand-deep: #B8F5EC;
  --on-destructive: #0F1716;
  --risk-nulo: #6FB5A9;
  --risk-nulo-bg: #16302B;
  --risk-nulo-fg: #A6E6DA;
  --risk-bajo: #3CBFAE;
  --risk-bajo-bg: #123A34;
  --risk-bajo-fg: #6FDCCB;
  --risk-medio: #D2AE62;
  --risk-medio-bg: #33291A;
  --risk-medio-fg: #E9C886;
  --risk-alto: #E08A66;
  --risk-alto-bg: #3B241C;
  --risk-alto-fg: #F2A686;
  --risk-muy-alto: #F07A62;
  --risk-muy-alto-bg: #4A1F18;
  --risk-muy-alto-fg: #F7A08C;
}

@theme inline {
  --font-sans: "Figtree", ui-sans-serif, system-ui, sans-serif;
  --radius-sm: calc(var(--radius) - 4px); /* 6px */
  --radius-md: calc(var(--radius) - 2px); /* 8px */
  --radius-lg: var(--radius);             /* 10px */
  --radius-xl: calc(var(--radius) + 4px); /* 14px */
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--card-foreground);
  --color-popover: var(--popover);
  --color-popover-foreground: var(--popover-foreground);
  --color-primary: var(--primary);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--accent-foreground);
  --color-destructive: var(--destructive);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
  --color-chart-1: var(--chart-1);
  --color-chart-2: var(--chart-2);
  --color-chart-3: var(--chart-3);
  --color-chart-4: var(--chart-4);
  --color-chart-5: var(--chart-5);
  --color-sidebar: var(--sidebar);
  --color-sidebar-foreground: var(--sidebar-foreground);
  --color-sidebar-primary: var(--sidebar-primary);
  --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
  --color-sidebar-accent: var(--sidebar-accent);
  --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
  --color-sidebar-border: var(--sidebar-border);
  --color-sidebar-ring: var(--sidebar-ring);
  --color-brand-soft: var(--brand-soft);
  --color-brand-deep: var(--brand-deep);
  --color-on-destructive: var(--on-destructive);
  --color-risk-nulo: var(--risk-nulo);
  --color-risk-nulo-bg: var(--risk-nulo-bg);
  --color-risk-nulo-fg: var(--risk-nulo-fg);
  --color-risk-bajo: var(--risk-bajo);
  --color-risk-bajo-bg: var(--risk-bajo-bg);
  --color-risk-bajo-fg: var(--risk-bajo-fg);
  --color-risk-medio: var(--risk-medio);
  --color-risk-medio-bg: var(--risk-medio-bg);
  --color-risk-medio-fg: var(--risk-medio-fg);
  --color-risk-alto: var(--risk-alto);
  --color-risk-alto-bg: var(--risk-alto-bg);
  --color-risk-alto-fg: var(--risk-alto-fg);
  --color-risk-muy-alto: var(--risk-muy-alto);
  --color-risk-muy-alto-bg: var(--risk-muy-alto-bg);
  --color-risk-muy-alto-fg: var(--risk-muy-alto-fg);
}

@layer base {
  * { @apply border-border outline-ring; }
  body { @apply bg-background text-foreground; font-size: 16px; line-height: 24px; }
}
```

## Ajustes a los componentes de shadcn

Cinco cambios en el código que genera `npx shadcn add`. Sin ellos, los tokens no bastan para cumplir el contraste:

1. **Foco:** reemplaza `focus-visible:ring-[3px] focus-visible:ring-ring/50` por `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring`. Un anillo al 50 % no llega a 3:1.
2. **Botón `destructive`:** `bg-destructive text-on-destructive` en los dos modos; elimina `dark:bg-destructive/60`.
3. **Bordes de controles:** `Input`, `SelectTrigger`, `Checkbox`, `RadioGroupItem` y el botón `outline` usan `border-input`, no `border-border`. Quita `dark:bg-input/30` (el fondo es `bg-card`).
4. **Tamaño:** controles en `h-11` (44 px), `px-4`, y `text-base` también en `md:`. Elimina `md:text-sm`.
5. **Sombras y movimiento:** elimina `shadow-xs`/`shadow-sm` de botones, inputs y tarjetas. Lo que flota (`Popover`, `Select` abierto, `Dialog`, `Tooltip`, `Sonner`) usa `shadow-float`; el panel de la página, `shadow-raised`. Las transiciones usan `ease-out` (que el tema redefine como curva fuerte), nunca `ease-linear`; el `Button` lleva `active:scale-[0.97]`, y `Select`, `DropdownMenu` y `Popover` crecen desde su disparador. `Calendar` (react-day-picker) no se genera con shadcn: vive en `components/ui/calendar.tsx` con los tokens del design system.
