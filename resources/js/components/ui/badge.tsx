import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex h-[26px] items-center justify-center gap-1.5 rounded-full border px-2.5 text-[13px] leading-[18px] font-medium w-fit whitespace-nowrap shrink-0 before:size-1.5 before:shrink-0 before:rounded-full before:content-[''] [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        // A soft fill and a dot carry the state; the text always names it.
        // Live: in progress, the one state in the accent.
        default:
          "border-transparent bg-accent text-primary before:bg-current",
        // Draft, closed, archived: the work is not moving.
        secondary:
          "border-transparent bg-secondary text-muted-foreground before:bg-current",
        // Up to date, done well.
        success:
          "border-transparent bg-risk-bajo-bg text-risk-bajo-fg before:bg-current",
        // Needs a look without alarm: missing contact, a trial ending.
        warning:
          "border-transparent bg-warning text-warning-foreground before:bg-current",
        // Expired, overdue, blocked.
        destructive:
          "border-transparent bg-risk-alto-bg text-risk-alto-fg before:bg-current",
        outline:
          "bg-card text-foreground before:bg-muted-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
