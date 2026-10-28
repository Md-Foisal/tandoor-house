import * as CheckboxPrimitive from "@radix-ui/react-checkbox"
import { CheckIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

function Checkbox({
  className,
  ...props
}: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
  return (
    <CheckboxPrimitive.Root
      data-slot="checkbox"
      className={cn(
        "peer relative size-5 shrink-0 rounded-[5px] border-[1.5px] border-line-strong bg-surface transition-colors duration-150 outline-none after:absolute after:-inset-3 after:content-[''] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember data-[state=checked]:border-ember data-[state=checked]:bg-ember data-[state=checked]:text-ember-ink aria-invalid:border-chilli disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        data-slot="checkbox-indicator"
        className="flex items-center justify-center text-current transition-none"
      >
        <CheckIcon className="size-3.5" />
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
}

export { Checkbox }
