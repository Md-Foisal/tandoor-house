import { cn } from "@/lib/utils"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="skeleton"
      aria-hidden="true"
      className={cn(
        "rounded-md bg-clay bg-[linear-gradient(100deg,transparent_30%,color-mix(in_oklab,var(--surface),transparent_40%)_50%,transparent_70%)] bg-[length:200%_100%] motion-safe:animate-[shimmer_1.6s_ease-in-out_infinite]",
        className
      )}
      {...props}
    />
  )
}

export { Skeleton }
