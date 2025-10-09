import { LoginForm } from "@/components/login-form"
import { ModeToggle } from "@/components/mode-toggle"

function App() {
  return (
    <div className="min-h-screen bg-background">
      {/* Theme toggle in top right */}
      <div className="absolute top-4 right-4">
        <ModeToggle />
      </div>

      {/* Login form centered */}
      <div className="flex min-h-screen items-center justify-center p-4">
        <LoginForm />
      </div>
    </div>
  )
}

export default App
