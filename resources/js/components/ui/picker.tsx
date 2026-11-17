import * as React from "react"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { InputGroup, InputGroupInput } from "@/components/ui/input-group"
import { Label } from "@/components/ui/label"
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { calendarSpan, fromCalendarDate, openingMonth, pickableDays, pickerFocusTarget, toCalendarDate } from "@/lib/calendar-date"
import { cn } from "@/lib/utils"

/*
 * The pieces the pickers of the design system (DatePicker, DateRangePicker, MonthPicker, TimePicker) are made of: the
 * text inside the field's box, the 44 px buttons beside it, the line that says why a text is not taken, the calendar of
 * one day and the sheet a phone gets instead of a popover.
 */

type TypedField = {
  text: string
  type: (text: string) => void
  focus: () => void
  leave: () => void
}

/** The text a person types in a picker, inside an InputGroup. With `label` it carries its own (in a sheet). */
function PickerText({
  field,
  invalid = false,
  describedBy,
  label,
  className,
  ...props
}: Omit<React.ComponentProps<"input">, "value" | "onChange" | "onFocus" | "onBlur"> & {
  field: TypedField
  invalid?: boolean
  describedBy?: string | undefined
  label?: string
}) {
  const input = (
    <InputGroupInput
      type="text"
      autoComplete="off"
      spellCheck={false}
      value={field.text}
      onChange={(event) => field.type(event.target.value)}
      onFocus={field.focus}
      onBlur={field.leave}
      aria-invalid={invalid || undefined}
      aria-describedby={describedBy === "" ? undefined : describedBy}
      className={cn("pr-1 tabular-nums read-only:cursor-pointer", className)}
      {...props}
    />
  )

  if (label === undefined) {
    return input
  }

  return (
    <div className="grid w-full gap-2">
      <Label htmlFor={props.id}>{label}</Label>
      <InputGroup>{input}</InputGroup>
    </div>
  )
}

/** A 44 px icon button inside a picker's box, named for screen readers; it takes what a Popover trigger hands down. */
function PickerButton({
  label,
  icon,
  className,
  ...props
}: React.ComponentProps<"button"> & { label: string; icon: React.ReactNode }) {
  return (
    <button
      type="button"
      className={cn(
        "flex size-11 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground",
        "focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring",
        className
      )}
      {...props}
    >
      {icon}
      <span className="sr-only">{label}</span>
    </button>
  )
}

/** Why the text left in a picker is not taken, under its box. */
function PickerProblem({ id, message }: { id: string; message: string | undefined }) {
  return message === undefined ? null : (
    <p id={id} className="text-sm text-destructive">
      {message}
    </p>
  )
}

/**
 * One month to pick a day from, with menus to jump to any month and year between the first and last day allowed. It
 * opens on the chosen day, or on today moved inside the limits; the days outside them are closed.
 */
function DayCalendar({
  value,
  min,
  max,
  onPick,
}: {
  value: string
  min?: string | undefined
  max?: string | undefined
  onPick: (day: string) => void
}) {
  const disabled = pickableDays(min, max)

  return (
    <Calendar
      // A day typed in the field moves the calendar to its month.
      key={value}
      mode="single"
      required
      captionLayout="dropdown"
      selected={fromCalendarDate(value)}
      defaultMonth={openingMonth(value, { min, max })}
      {...calendarSpan(min, max)}
      {...(disabled === undefined ? {} : { disabled })}
      onSelect={(day) => onPick(toCalendarDate(day))}
    />
  )
}

/**
 * A picker on a phone: a sheet from the bottom edge with the field to type in and the calendar, and «Borrar» (when the
 * field can be empty) and «Listo» under them. It opens with the focus on the calendar, so the keyboard does not cover it.
 */
function PickerSheet({
  open,
  onOpenChange,
  title,
  hint,
  onClear,
  children,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  hint: string
  onClear?: (() => void) | undefined
  children: React.ReactNode
}) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="bottom"
        className="max-h-[92dvh] gap-0 overflow-y-auto rounded-t-xl pb-[env(safe-area-inset-bottom)]"
        onOpenAutoFocus={(event) => {
          event.preventDefault()

          if (event.currentTarget instanceof HTMLElement) {
            event.currentTarget.querySelector<HTMLElement>(pickerFocusTarget)?.focus()
          }
        }}
      >
        <SheetHeader className="pr-14">
          <SheetTitle>{title}</SheetTitle>
          <SheetDescription>{hint}</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col items-center gap-4 px-4">{children}</div>
        <SheetFooter className="flex-row justify-end">
          {onClear !== undefined && (
            <Button
              type="button"
              variant="outline"
              size="lg"
              onClick={() => {
                onClear()
                onOpenChange(false)
              }}
            >
              Borrar
            </Button>
          )}
          <Button type="button" size="lg" onClick={() => onOpenChange(false)}>
            Listo
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}

/**
 * One month to pick the two ends of a period from, each a tap, with the days between them marked. It opens on the
 * period's first day, or on today moved inside the limits.
 */
function RangeCalendar({
  from,
  to,
  min,
  max,
  onPick,
}: {
  from: string
  to: string
  min?: string | undefined
  max?: string | undefined
  onPick: (day: string) => void
}) {
  const disabled = pickableDays(min, max)

  return (
    <Calendar
      mode="range"
      captionLayout="dropdown"
      selected={{ from: fromCalendarDate(from), to: fromCalendarDate(to) }}
      defaultMonth={openingMonth(from, { min, max })}
      {...calendarSpan(min, max)}
      {...(disabled === undefined ? {} : { disabled })}
      onSelect={(_period, day) => onPick(toCalendarDate(day))}
      classNames={{
        range_start: "[&_button]:bg-primary [&_button]:text-primary-foreground",
        range_end: "[&_button]:bg-primary [&_button]:text-primary-foreground",
        // Every day of the period is also «selected»: the ones between its ends lose the solid fill.
        range_middle: "bg-accent [&_button]:rounded-none [&_button]:bg-transparent! [&_button]:font-normal! [&_button]:text-foreground!",
      }}
    />
  )
}

export { DayCalendar, PickerButton, PickerProblem, PickerSheet, PickerText, RangeCalendar }
