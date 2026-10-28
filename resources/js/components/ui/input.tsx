import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "flex h-11 w-full min-w-0 rounded-md border border-line-strong bg-surface px-3.5 text-base text-ink transition-[border-color,box-shadow] duration-150 outline-none placeholder:text-ink-muted file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium",
        "focus-visible:border-ember focus-visible:shadow-[0_0_0_3px_var(--ember-soft)]",
        "disabled:cursor-not-allowed disabled:bg-clay disabled:opacity-60",
        "aria-invalid:border-chilli aria-invalid:shadow-[0_0_0_3px_var(--chilli-soft)]",
        className
      )}
      {...props}
    />
  )
}

export { Input }
