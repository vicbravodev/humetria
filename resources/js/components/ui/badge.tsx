import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center gap-1.5 rounded-md border px-2 py-0.5 text-[13px] leading-[18px] font-medium w-fit whitespace-nowrap shrink-0 before:size-1.5 before:shrink-0 before:rounded-full before:content-[''] [&>svg]:size-3 [&>svg]:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        // A soft fill and a dot carry the state; the text always names it.
        default:
          "border-transparent bg-accent text-accent-foreground before:bg-primary",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground before:bg-muted-foreground",
        destructive:
          "border-destructive/40 bg-card text-destructive before:bg-destructive",
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
