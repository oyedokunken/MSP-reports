interface CustomersHeaderProps {
  title?: string
  subtitle?: string
}

export function CustomersHeader({ 
  title = "Customers", 
  subtitle = "Manage customers and configure their report schedules" 
}: CustomersHeaderProps) {
  return (
    <div>
      <h1 className="text-3xl font-bold text-foreground">{title}</h1>
      <p className="mt-2 text-muted-foreground">
        {subtitle}
      </p>
    </div>
  )
}
