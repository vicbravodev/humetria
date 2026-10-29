import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * One field made of an input and what sits inside its box (a prefix, a button). It looks and focuses like Input:
 * the outline goes around the whole box, so the inner input draws none of its own.
 */
function InputGroup({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="input-group"
      className={cn(
        "border-input flex h-11 w-full min-w-0 items-center rounded-md border bg-card transition-[color,border-color,box-shadow] duration-150 ease-out hover:border-foreground/60",
        "has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-ring has-[input:focus-visible]:border-ring",
        "has-[input[aria-invalid=true]]:border-destructive has-[input[aria-invalid=true]]:shadow-[inset_0_0_0_1px_var(--destructive)]",
        "has-[input:disabled]:bg-muted has-[input:disabled]:opacity-60",
        className
      )}
      {...props}
    />
  )
}

function InputGroupText({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="input-group-text"
      className={cn("text-muted-foreground shrink-0 pl-4 select-none [&+input]:pl-0.5", className)}
      {...props}
    />
  )
}

function InputGroupInput({ className, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      data-slot="input-group-control"
      className={cn(
        "placeholder:text-muted-foreground h-full w-full min-w-0 flex-1 rounded-md bg-transparent px-4 text-base outline-hidden disabled:cursor-not-allowed",
        className
      )}
      {...props}
    />
  )
}

export { InputGroup, InputGroupInput, InputGroupText }
