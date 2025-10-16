import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Mail, Lock, Eye, EyeOff } from "lucide-react";
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
        <Card className="w-full max-w-[400px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
          <CardHeader className="space-y-0.5 text-center">
            <CardTitle className="text-lg font-bold">
              Sign in to My Application
            </CardTitle>
            <CardDescription className="text-sm">
              Welcome back! Please sign in to continue
            </CardDescription>
          </CardHeader>
      
          <CardContent className="space-y-3">
            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="space-y-1">
                <Label htmlFor="email" className="text-xs">Email address</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="john@company.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-9"
                    required
                    disabled={isSubmitting}
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="password" className="text-xs">Password</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Your password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="pl-9 pr-10"
                    required
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground/60 hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
              </div>

              {error && (
                <div className="text-sm text-destructive">{error}</div>
              )}

              <Button type="submit" className="w-full text-white" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Spinner className="mr-2 h-4 w-4" />
                    Signing in...
                  </>
                ) : (
                  "Continue →"
                )}
              </Button>
            </form>
          </CardContent>
      
          {/* Footer */}
          <CardFooter className="flex justify-center border-t border-border mt-2 pt-2 border-0">
            <p className="text-sm text-muted-foreground">
              Don't have an account?{" "}
              <Link to="/sign-up" className="text-primary font-medium hover:underline">
                Sign up
              </Link>
            </p>
          </CardFooter>
          
          {/* Secured by Footer */}
          <div className="relative">
            <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-60"></div>
            <div className="flex justify-center py-3">
              <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                Secured by <span className="text-blue-500 font-semibold">Athena Networks</span>
              </p>
            </div>
          </div>
        </Card>
      );
      
}

export default Signin;
