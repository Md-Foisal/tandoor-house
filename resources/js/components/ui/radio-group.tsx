import * as RadioGroupPrimitive from "@radix-ui/react-radio-group"
import * as React from "react"

import { cn } from "@/lib/utils"

function RadioGroup({
  className,
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Root>) {
  return (
    <RadioGroupPrimitive.Root
      data-slot="radio-group"
      className={cn("grid gap-2", className)}
      {...props}
    />
  )
}

function RadioGroupItem({
  className,
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Item>) {
  return (
    <RadioGroupPrimitive.Item
      data-slot="radio-group-item"
      className={cn(
        "peer relative grid size-5 shrink-0 place-items-center rounded-full border-[1.5px] border-line-strong bg-surface outline-none transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-ember aria-invalid:border-chilli",
        className
      )}
      {...props}
    >
      <RadioGroupPrimitive.Indicator className="size-2.5 rounded-full bg-ember" />
    </RadioGroupPrimitive.Item>
  )
}

export { RadioGroup, RadioGroupItem }
