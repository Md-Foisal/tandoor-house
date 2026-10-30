import * as SwitchPrimitive from "@radix-ui/react-switch"
import * as React from "react"

import { cn } from "@/lib/utils"

function Switch({
  className,
  ...props
}: React.ComponentProps<typeof SwitchPrimitive.Root>) {
  return (
    <SwitchPrimitive.Root
      data-slot="switch"
      className={cn(
        "peer relative inline-flex h-7 w-12 after:absolute after:-inset-y-2 after:-inset-x-1 after:content-[''] shrink-0 items-center rounded-full border-[1.5px] border-line-strong bg-clay p-0.5 outline-hidden transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ember disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-ember data-[state=checked]:bg-ember",
        className
      )}
      {...props}
    >
      <SwitchPrimitive.Thumb className="pointer-events-none block size-5 rounded-full bg-surface shadow-plate transition-transform duration-200 ease-out data-[state=checked]:translate-x-5" />
    </SwitchPrimitive.Root>
  )
}

export { Switch }
