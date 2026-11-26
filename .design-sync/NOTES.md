# /design-sync notes — Humetría

- **Humetría is an app, not a library.** `node .design-sync/build-dist.mjs` (cfg.buildCmd) plays the library build: it
  writes `.ds-sync/pkg/` (package.json named `humetria`, `dist/index.js`, `dist/index.d.ts` + `dist/types/`,
  `dist/styles.css`, `fonts/`) from `resources/js/components/ui` and `resources/js/components/humetria` only. It never
  writes under `resources/`. The converter then runs with `--entry ./.ds-sync/pkg/dist/index.js --node-modules ./node_modules`.
- **Run `composer worktree:setup` first on a fresh clone/worktree**: `resources/js/lib/results.ts` imports Wayfinder's
  generated `@/routes/...` (gitignored, made by `npm run build`), and Figtree is self-hosted by the app build
  (`public/build/assets/figtree-*.woff2`, from `laravel-vite-plugin/fonts` + Bunny). build-dist stops with
  `[BUILD_DIST]` when the routes are missing.
- **Tailwind is compiled by build-dist**, with `@tailwindcss/node` + `@tailwindcss/oxide`, over `resources/js/**` and
  `.design-sync/previews/**`. A class used only in a preview exists only after build-dist runs again: after editing
  previews, run build-dist, then the converter (or the driver). Utility classes the app never uses are NOT in
  `bundle.css` (the design agent must use existing classes or inline styles: see conventions).
- `use-phone-field.ts` uses Vite's `import.meta.glob` for the flags: build-dist inlines the 3x2 SVGs from
  `country-flag-icons` as data URLs (adds ~bundle weight).
- `tsc` emits declarations with 3 known type errors (shadcn `sidebar`/`skeleton`/`spinner` CSS-var and Lucide prop
  types); emit continues (`noEmitOnError: false`). Types like `PageFact` stay as names in the per-component `.d.ts`.
- Name clashes pinned in `componentSrcMap`: `ChoiceField` (also `components/compensation/choice-field.tsx`) and
  `RowMenu` (also `components/interventions/row-menu.tsx`) → the `humetria/` ones.
- Group `general` = `components/ui` (shadcn); `humetria` = `components/humetria`.
- Overlay components use `overrides.<Name>: {cardMode: single, viewport}` so the open state renders in the card.
- Guidelines published: `docs/design-system/*.md` (incl. `propuestas-y-contradicciones.md`, which says itself it is not a
  rule) and `docs/design-system/humi/03-ui-spec.md`. Per-component docs: `docs/design-system/componentes/<Name>.md`
  (`docsDir`).
- Chromium for the render check: the cached `chromium-1248` (playwright 1.64.1, installed in `.ds-sync`).

## Previews (first sync, 2026-11-25)

- 108 authored previews in `.design-sync/previews/` (core set, then every standalone piece, as the owner chose); the ~95 left (all
  shadcn sub-parts like `DialogTitle`, shown inside their parent) ship on the floor card.
- `Logo` loads `/brand/<file>.svg` from `public/`: build-dist inlines `public/brand/*.svg` as data URLs in the bundle
  (esbuild onLoad on `humetria/logo.tsx`); the app file is untouched.
- Tooltip users (`LevelBar`, `BarMeter`, `Tooltip`) are wrapped in `TooltipProvider` inside their previews; the app
  mounts one at its root. No global `cfg.provider`.
- Inertia `Link` and `<Form>` render without a page context: `CountTabs`, `Pagination`, `UrgencyList`, `FormSheet`,
  `FormDialog` and `AreaFilter` take plain props (`{ action, method }` for a form).
- `usePage()` throws outside Inertia's app. `PlanLimitNotice`, `SupportLink` and `RouteHumiCard` read shared props
  (`support.email`, `tenant.trade_name`, `tenant.can.use_assistant`): build-dist rewrites their `usePage` import to a
  `humetria-page` shim that falls back to `globalThis.HumetriaPage.props`; their previews set it at module top. The app
  files are untouched and behave the same in the app.
- `InstallGuide` reads `navigator.userAgent` on mount: its preview redefines it per cell (iPhone, Android, in-app
  browser). Works because cells render in order.
- `NewVersionBanner` is `position: fixed`: its preview frame uses `transform: translateZ(0)` to keep it in the cell.
- `GlideChips`' hover chip needs the app's `<LazyMotion features={domMax}>`; a preview importing `motion/react` gets a
  second framer-motion copy, so only the current chip is shown.
- `DayCalendar`, `RangeCalendar`, `MonthGrid` live in `components/ui/picker.tsx`; previews pin `min`/`max` so the
  screenshot does not move with today's date. `DateRangePicker` is never shown open (no `open` prop).
- `SidebarMenuButton` with `tooltip` needs `TooltipProvider`; the `Sidebar` preview is a static copy of the app's
  sidebar (inset variant, real group names) at 1000x640.
- `PosterPage` needs `lg` (controls left, sheet right): `viewport: 1280x900`. `PosterQr`/`PosterPage` previews inline
  real QR SVGs made once with the installed `qr` package.
- `RowMenu` has no `open` prop: its preview opens it on mount with a native keydown Enter on `[data-slot="row-menu"]`.
- Radix focuses the first field of an open `Dialog`/`Sheet`; the `ui` previews pass
  `onOpenAutoFocus={(e) => e.preventDefault()}`; the humetria form ones keep the app's focus (selected text shows).
- Classes missing from the compiled CSS met while authoring: `text-[10px]`, `max-w-[360px]`,
  `grid-cols-[minmax(140px,180px)_112px_minmax(0,1fr)]`: previews switched to existing classes or inline styles.
- `StackedTable` turns rows into cards only below `md`; its card captures at desktop width, so only the table shows.
- `Select` stays in grid mode: no open-dropdown cell.

## Frames (added before the first publish)

- build-dist also exports 9 frame files (`FRAMES` list): `AppSidebarLayout` (the whole panel frame), `AppSidebar`,
  `AppSidebarHeader`, `AppBreadcrumbs`, `MobileTabBar`, `CompanyMark`, `PersonFrame`, `WorkerFrame`, `PortalTabBar`.
  Group `marcos` comes from the stub `.design-sync/docs/marcos.md` (docsMap); `WorkerFrame`/`PortalTabBar` keep the
  path group `worker-access` because their source is found under that folder.
- Every `@inertiajs/react` import in the bundle goes through a wrapper whose `usePage` falls back to
  `globalThis.HumetriaPage = { url, props }` (shape: `resources/js/types/global.d.ts` sharedPageProps). Previews set it at
  module top: an owner of «Aceros del Norte» with every module and permission, `brand: null`. `AppSidebarLayout` stories
  set `sidebarOpen` in their own body (AppShell reads it once on mount). Humi, first-steps, command palette and
  impersonation docks render without errors.
- Phone frames: the card area is ~340 px of a 390 px viewport, and fixed bars attach to the viewport, so the previews
  wrap them in a box with `transform: translateZ(0)` and draw the phone with CSS `zoom` (0.9–0.94).
- `CompanyMark` with a company brand is not shown (would need `brand` in that file's page props).

## Known render warns

- Floor-card components flagged `[RENDER_BLANK]`/`[RENDER_THIN]` with no authored preview (e.g. `InputOTPSeparator`,
  `SidebarMenuSkeleton`, `PickerButton`, `IconTile`, `DocumentPreview`, `PersonHeading`, `PosterPage`,
  `QuestionHeading`, `ReportLinkBar`): unauthored, not failures.

## Re-sync risks

- build-dist parses exports with regexes (`export default function X`, `export function|const X`, `export { … }`): a
  component exported another way is silently left out. Compare `entry: N exports` against the last run.
- Wayfinder routes and fonts come from `npm run build`; a stale `public/build` ships stale fonts.
- The app runs React 19; the bundle vendors it (the artifact page's own React is 18).
