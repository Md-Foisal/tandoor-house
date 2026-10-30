import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md font-semibold select-none transition-[background-color,color,border-color,box-shadow,transform] duration-150 ease-out active:translate-y-px disabled:pointer-events-none disabled:opacity-45 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-[1.125rem] [&_svg]:shrink-0 outline-hidden focus-visible:outline-2 focus-visible:outline-solid focus-visible:outline-offset-2 focus-visible:outline-ember aria-invalid:border-chilli",
  {
    variants: {
      variant: {
        default:
          "bg-ember text-ember-ink shadow-[inset_0_-2px_0_rgb(0_0_0/0.18)] hover:bg-[color-mix(in_oklab,var(--ember),var(--ink)_14%)]",
        destructive:
          "bg-chilli text-ember-ink hover:bg-[color-mix(in_oklab,var(--chilli),var(--ink)_14%)]",
        outline:
          "border-[1.5px] border-ink/80 bg-transparent text-ink hover:bg-clay",
        secondary:
          "bg-clay text-ink hover:bg-[color-mix(in_oklab,var(--clay),var(--ink)_8%)]",
        ghost: "text-ink hover:bg-clay",
        link: "h-auto px-0 text-ember underline decoration-1 underline-offset-4 hover:decoration-2",
      },
      size: {
        default: "h-11 px-5 text-[0.9375rem] has-[>svg]:px-4",
        sm: "h-9 px-3.5 text-sm has-[>svg]:px-3 after:absolute after:-inset-1 after:content-['']",
        lg: "h-12 px-7 text-base has-[>svg]:px-6",
        icon: "size-11",
        "icon-sm": "size-9 after:absolute after:-inset-1 after:content-['']",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
