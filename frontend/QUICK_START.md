# Quick Start Guide - Adding shadcn/ui Components

## Add Your First Component (Button)

```bash
cd /home/mark/reporting/frontend
npx shadcn@latest add button
```

This will:
1. Create `src/components/ui/button.tsx`
2. Install any required dependencies
3. Configure the component with your theme

## Test the Button Component

Update `src/App.tsx`:

```tsx
import { Button } from "@/components/ui/button"

function App() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <Button>Click me</Button>
    </div>
  )
}

export default App
```

## Add Multiple Components at Once

```bash
# Essential UI components for your platform
npx shadcn@latest add button input label card table dialog toast form select badge progress tabs sidebar
```

## Component Categories for Your Project

### Authentication & Layout (Phase 1)
```bash
npx shadcn@latest add button input label card avatar dropdown-menu
```

### Data Display (Phase 2-4)
```bash
npx shadcn@latest add table badge progress dialog toast tabs
```

### Forms & Inputs (All Phases)
```bash
npx shadcn@latest add form select checkbox radio-group switch textarea
```

### Navigation (Phase 1)
```bash
npx shadcn@latest add sidebar navigation-menu breadcrumb
```

## Using Components with Path Aliases

Thanks to the `@/` alias configuration, you can import like this:

```tsx
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card } from "@/components/ui/card"
import { cn } from "@/lib/utils"
```

## Customizing Components

All shadcn/ui components are in your codebase, so you can customize them:

```tsx
// src/components/ui/button.tsx
// Edit this file to change button styles globally
```

## Next Steps

1. **Add components**: `npx shadcn@latest add [component-name]`
2. **Start dev server**: `npm run dev`
3. **Build your UI**: Import and use components
4. **Customize**: Edit component files as needed

## Full Component List

Visit: https://ui.shadcn.com/docs/components

Available components:
- accordion, alert, alert-dialog, aspect-ratio, avatar
- badge, breadcrumb, button, calendar, card
- carousel, checkbox, collapsible, command, context-menu
- dialog, drawer, dropdown-menu, form, hover-card
- input, label, menubar, navigation-menu, pagination
- popover, progress, radio-group, resizable, scroll-area
- select, separator, sheet, sidebar, skeleton
- slider, sonner, switch, table, tabs
- textarea, toast, toggle, toggle-group, tooltip

And many more!
