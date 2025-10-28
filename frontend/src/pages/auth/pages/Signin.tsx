import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Eye, EyeOff, BarChart3 } from "lucide-react";
import { Spinner } from "@/components/ui/spinner";
import { Link } from "react-router-dom";
import { useAuthForm } from "../hooks/useAuthForm";

const Signin = () => {
    const [showPassword, setShowPassword] = useState(false);
    
    const {
      email,
      password,
      error,
      isSubmitting,
      setEmail,
      setPassword,
      handleSubmit,
    } = useAuthForm({
      redirectTo: '/dashboard',
      onSuccess: () => {
        console.log('Login successful!')
      },
      onError: (err) => {
        console.error('Login failed:', err)
      }
    });

    return (
      <div className="flex h-screen w-full overflow-hidden">
        {/* Left Panel - Branding */}
        <div className="relative hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-16 text-white overflow-hidden" style={{ backgroundColor: '#243f5f' }}>
          {/* Subtle Grid Pattern Background */}
          <div className="absolute inset-0 opacity-10">
            <div className="absolute inset-0" style={{
              backgroundImage: `
                linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px)
              `,
              backgroundSize: '50px 50px'
            }}></div>
          </div>
          
          {/* Gradient Overlay */}
          <div className="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-black/20"></div>
          
          {/* Decorative Circles */}
          <div className="absolute top-20 right-20 w-64 h-64 bg-emerald-400/10 rounded-full blur-3xl"></div>
          <div className="absolute bottom-20 left-20 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl"></div>
          
          <div className="relative z-10 flex flex-col gap-6 w-full max-w-lg">
            <div className="flex items-center gap-3">
              <BarChart3 className="h-8 w-8 text-emerald-400" />
              <span className="text-xl font-bold">MSP Reports</span>
            </div>
            <h1 className="text-5xl font-black leading-tight">
              Unlock Your Data Insights.
            </h1>
            <p className="text-lg text-slate-300">
              Access, analyze, and share your managed services reports with confidence.
            </p>
          </div>
        </div>

        {/* Right Panel - Login Form */}
        <div className="flex w-full lg:w-1/2 flex-col items-center justify-center bg-background p-6 sm:p-12 overflow-y-auto">
          <div className="w-full max-w-md space-y-6">
            {/* Logo for Mobile */}
            <div className="flex items-center gap-3 lg:hidden">
              <BarChart3 className="h-8 w-8 text-primary" />
              <span className="text-xl font-bold">MSP Reports</span>
            </div>

            {/* Page Heading */}
            <div>
              <h1 className="text-3xl font-bold text-foreground">
                Sign In to Your Account
              </h1>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="space-y-2">
                <Label htmlFor="email" className="text-sm font-medium">Email Address</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="Enter your email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="h-11"
                  required
                  disabled={isSubmitting}
                />
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="password" className="text-sm font-medium">Password</Label>
                  <Link to="/forgot-password" className="text-sm text-primary hover:underline">
                    Forget Password?
                  </Link>
                </div>
                <div className="relative">
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Enter your password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="h-11 pr-10"
                    required
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                </div>
              </div>

              {error && (
                <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive">
                  {error}
                </div>
              )}

              <Button type="submit" className="w-full h-11 text-base font-semibold text-white" style={{ backgroundColor: '#243f5f' }} disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Spinner className="mr-2 h-4 w-4" />
                    Signing in...
                  </>
                ) : (
                  "Sign In"
                )}
              </Button>
            </form>

            {/* Footer */}
            <div className="space-y-4 pt-4">
              <div className="text-center text-sm text-muted-foreground">
                Don't have an account?{" "}
                <Link to="/sign-up" className="text-primary font-semibold hover:underline">
                  Contact your administrator
                </Link>
                .
              </div>
              
              {/* Secured by Footer */}
              <div className="relative">
                <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>
                <div className="flex justify-center pt-4">
                  <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                    Secured by <span className="text-primary font-semibold">Athena Networks</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
      
}

export default Signin;
