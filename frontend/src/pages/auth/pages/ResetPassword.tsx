import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Lock, Eye, EyeOff, CheckCircle2 } from "lucide-react";
import { Spinner } from "@/components/ui/spinner";
import { useResetPassword } from "../hooks";

const ResetPassword = () => {
  const {
    password,
    confirmPassword,
    showPassword,
    showConfirmPassword,
    error,
    isSubmitting,
    success,
    setPassword,
    setConfirmPassword,
    setShowPassword,
    setShowConfirmPassword,
    handleSubmit
  } = useResetPassword();

  if (success) {
    return (
      <Card className="w-full max-w-[400px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
        {/* Logo */}
        <div className="flex justify-center pt-8 pb-4">
          <img src="/logo.png" alt="Logo" className="h-8 w-auto opacity-90" />
        </div>

        <CardContent className="pt-2">
          <div className="flex flex-col items-center justify-center py-8 text-center">
            <div className="mb-4 rounded-full bg-green-500/10 p-3">
              <CheckCircle2 className="h-12 w-12 text-green-500" />
            </div>
            <h2 className="text-2xl font-bold text-green-600 mb-2">Password Reset!</h2>
            <p className="text-sm text-muted-foreground mb-4">
              Your password has been reset successfully.
            </p>
            <p className="text-xs text-muted-foreground">
              Redirecting to sign in...
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="w-full max-w-[400px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
      {/* Logo */}
      <div className="flex justify-center pt-8 pb-6">
        <img src="/logo.png" alt="Logo" className="h-8 w-auto opacity-90" />
      </div>

      <CardHeader className="space-y-0.5 text-center pt-0">
        <CardTitle className="text-lg font-bold">
          Reset Your Password
        </CardTitle>
        <CardDescription className="text-sm">
          Enter your new password below
        </CardDescription>
      </CardHeader>

      <CardContent className="space-y-3">
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="space-y-1">
            <Label htmlFor="password" className="text-xs">New Password</Label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
              <Input
                id="password"
                type={showPassword ? "text" : "password"}
                placeholder="Enter new password (min 8 characters)"
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

          <div className="space-y-1">
            <Label htmlFor="confirmPassword" className="text-xs">Confirm Password</Label>
            <div className="relative">
              <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
              <Input
                id="confirmPassword"
                type={showConfirmPassword ? "text" : "password"}
                placeholder="Confirm new password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="pl-9 pr-10"
                required
                disabled={isSubmitting}
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground/60 hover:text-foreground transition-colors"
                tabIndex={-1}
              >
                {showConfirmPassword ? (
                  <EyeOff className="h-4 w-4" />
                ) : (
                  <Eye className="h-4 w-4" />
                )}
              </button>
            </div>
          </div>

          {/* Password Requirements */}
          <div className="rounded-lg border border-border bg-accent/50 p-3">
            <p className="text-xs font-medium mb-2">Password Requirements:</p>
            <ul className="text-xs text-muted-foreground space-y-1">
              <li className="flex items-center gap-2">
                <span className={password.length >= 8 ? 'text-green-600' : ''}>
                  {password.length >= 8 ? '✓' : '•'}
                </span>
                At least 8 characters
              </li>
              <li className="flex items-center gap-2">
                <span className={password === confirmPassword && password.length > 0 ? 'text-green-600' : ''}>
                  {password === confirmPassword && password.length > 0 ? '✓' : '•'}
                </span>
                Passwords match
              </li>
            </ul>
          </div>

          {error && (
            <div className="text-sm text-destructive">{error}</div>
          )}

          <Button
            type="submit"
            className="w-full text-white"
            disabled={isSubmitting || password.length < 8 || password !== confirmPassword}
          >
            {isSubmitting ? (
              <>
                <Spinner className="mr-2 h-4 w-4" />
                Resetting...
              </>
            ) : (
              "Reset Password"
            )}
          </Button>
        </form>
      </CardContent>

      {/* Secured by Footer */}
      <div className="relative">
        <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>
        <div className="flex justify-center py-3">
          <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
            Secured by <span className="text-primary font-semibold">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
          </p>
        </div>
      </div>
    </Card>
  );
};

export default ResetPassword;
