import { ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from "lucide-react"
import * as React from "react"
import { DayPicker } from "react-day-picker"
import { es } from "react-day-picker/locale"

import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const monthName = new Intl.DateTimeFormat("es-MX", { month: "long" })

/** A month grid in Mexican Spanish, weeks from Monday; react-day-picker styled with the design system tokens. */
function Calendar({
  className,
  classNames,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      data-slot="calendar"
      locale={es}
      animate
      className={cn("w-fit", className)}
      classNames={{
        months: "relative flex flex-col",
        month: "flex flex-col gap-3",
        nav: "absolute inset-x-0 top-0 flex items-center justify-between",
        button_previous: cn(buttonVariants({ variant: "ghost", size: "icon" }), "size-11 text-muted-foreground hover:text-foreground aria-disabled:pointer-events-none aria-disabled:opacity-30"),
        button_next: cn(buttonVariants({ variant: "ghost", size: "icon" }), "size-11 text-muted-foreground hover:text-foreground aria-disabled:pointer-events-none aria-disabled:opacity-30"),
        month_caption: "flex h-11 items-center justify-center",
        caption_label: "text-base font-semibold first-letter:uppercase [&_svg]:ml-1 [&_svg]:inline",
        // With the month and year menus, each label sits over its own native select, which takes the click and the keyboard.
        dropdowns: "relative z-10 flex items-center gap-1",
        dropdown_root: "relative flex h-11 items-center rounded-md px-2 hover:bg-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring",
        dropdown: "absolute inset-0 cursor-pointer appearance-none bg-popover text-popover-foreground opacity-0",
        month_grid: "border-collapse",
        weekdays: "flex",
        weekday: "w-11 pb-1 text-[13px] leading-[18px] font-medium text-muted-foreground first-letter:uppercase",
        week: "flex",
        day: "size-11 text-center",
        day_button: cn(
          "size-11 rounded-md text-base tabular-nums transition-[color,background-color,transform] duration-150 ease-out hover:bg-accent active:scale-95 motion-reduce:active:scale-100",
          "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none"
        ),
        today: "font-semibold text-primary [&_button]:underline [&_button]:underline-offset-4",
        selected: "[&_button]:bg-primary [&_button]:text-primary-foreground [&_button]:font-medium [&_button]:no-underline [&_button:hover]:bg-primary",
        outside: "text-muted-foreground",
        disabled: "text-muted-foreground opacity-40",
        hidden: "invisible",
        // react-day-picker adds each of these as one class (app.css, "Calendar"), and removes the old month
        // when its caption's exit animation ends: exits must animate too.
        weeks_before_enter: "calendar-weeks-before-enter",
        weeks_after_enter: "calendar-weeks-after-enter",
        weeks_before_exit: "calendar-weeks-before-exit",
        weeks_after_exit: "calendar-weeks-after-exit",
        caption_before_enter: "calendar-caption-enter",
        caption_after_enter: "calendar-caption-enter",
        caption_before_exit: "calendar-caption-exit",
        caption_after_exit: "calendar-caption-exit",
        ...classNames,
      }}
      formatters={{ formatMonthDropdown: (month) => monthName.format(month).replace(/^./, (letter) => letter.toUpperCase()) }}
      components={{
        Chevron: ({ orientation }) =>
          orientation === "left" ? (
            <ChevronLeftIcon className="size-5" />
          ) : orientation === "down" ? (
            <ChevronDownIcon aria-hidden className="size-4 text-muted-foreground" />
          ) : (
            <ChevronRightIcon className="size-5" />
          ),
      }}
      {...props}
    />
  )
}

export { Calendar }
