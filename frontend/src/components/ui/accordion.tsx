import * as React from "react"
import { cn } from "@/lib/utils"
import { ChevronDown } from "lucide-react"

interface AccordionProps {
  type?: "single" | "multiple"
  collapsible?: boolean
  defaultValue?: string | string[]
  value?: string | string[]
  onValueChange?: (value: string | string[]) => void
  className?: string
  children: React.ReactNode
}

const Accordion = React.forwardRef<HTMLDivElement, AccordionProps>(
  ({ type = "single", collapsible = false, defaultValue, onValueChange, className, children }, ref) => {
    const [openItems, setOpenItems] = React.useState<string[]>(
      defaultValue ? (Array.isArray(defaultValue) ? defaultValue : [defaultValue]) : []
    )

    const toggleItem = (itemValue: string) => {
      if (type === "single") {
        const newValue = openItems.includes(itemValue) && collapsible ? [] : [itemValue]
        setOpenItems(newValue)
        onValueChange?.(newValue[0] || "")
      } else {
        const newValue = openItems.includes(itemValue)
          ? openItems.filter(v => v !== itemValue)
          : [...openItems, itemValue]
        setOpenItems(newValue)
        onValueChange?.(newValue)
      }
    }

    return (
      <div ref={ref} className={className}>
        {React.Children.map(children, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<any>, {
              openItems,
              toggleItem,
            })
          }
          return child
        })}
      </div>
    )
  }
)
Accordion.displayName = "Accordion"

interface AccordionItemProps {
  value: string
  className?: string
  children: React.ReactNode
  openItems?: string[]
  toggleItem?: (value: string) => void
}

const AccordionItem = React.forwardRef<HTMLDivElement, AccordionItemProps>(
  ({ value, className, children, openItems = [], toggleItem }, ref) => {
    const isOpen = openItems.includes(value)

    return (
      <div ref={ref} className={cn("border-b", className)}>
        {React.Children.map(children, (child) => {
          if (React.isValidElement(child)) {
            return React.cloneElement(child as React.ReactElement<any>, {
              isOpen,
              toggleItem: () => toggleItem?.(value),
            })
          }
          return child
        })}
      </div>
    )
  }
)
AccordionItem.displayName = "AccordionItem"

interface AccordionTriggerProps {
  className?: string
  children: React.ReactNode
  isOpen?: boolean
  toggleItem?: () => void
}

const AccordionTrigger = React.forwardRef<HTMLButtonElement, AccordionTriggerProps>(
  ({ className, children, isOpen, toggleItem }, ref) => (
    <button
      ref={ref}
      type="button"
      onClick={toggleItem}
      className={cn(
        "flex w-full flex-1 items-center justify-between py-4 text-sm font-medium transition-all hover:underline text-left",
        className
      )}
    >
      {children}
      <ChevronDown
        className={cn(
          "h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200",
          isOpen && "rotate-180"
        )}
      />
    </button>
  )
)
AccordionTrigger.displayName = "AccordionTrigger"

interface AccordionContentProps {
  className?: string
  children: React.ReactNode
  isOpen?: boolean
}

const AccordionContent = React.forwardRef<HTMLDivElement, AccordionContentProps>(
  ({ className, children, isOpen }, ref) => (
    <div
      ref={ref}
      className={cn(
        "overflow-hidden text-sm transition-all",
        isOpen ? "animate-accordion-down" : "animate-accordion-up hidden"
      )}
    >
      <div className={cn("pb-4 pt-0", className)}>{children}</div>
    </div>
  )
)
AccordionContent.displayName = "AccordionContent"

export { Accordion, AccordionItem, AccordionTrigger, AccordionContent }
