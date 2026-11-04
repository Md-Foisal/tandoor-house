import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "flex min-h-24 w-full rounded-md border border-line-strong bg-surface px-3.5 py-3 text-base text-ink transition-[border-color,box-shadow] duration-150 outline-hidden placeholder:text-ink-muted",
        "focus-visible:border-ember focus-visible:shadow-[0_0_0_3px_var(--ember-soft)]",
        "disabled:cursor-not-allowed disabled:bg-clay disabled:opacity-60",
        "aria-invalid:border-chilli aria-invalid:shadow-[0_0_0_3px_var(--chilli-soft)]",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
