import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Lock, Eye, EyeOff, CheckCircle2 } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { useResetPassword } from "../hooks";
import { MobileLogo } from "../components";

const ResetPassword = () => {
  const {
    form,
    showPassword,
    showConfirmPassword,
    error,
    isSubmitting,
    success,
    setShowPassword,
    setShowConfirmPassword,
    handleSubmit
  } = useResetPassword();

  const { register, formState: { errors }, watch } = form;
  const passwordValue = watch("password") || "";
  const confirmPasswordValue = watch("confirmPassword") || "";

  return (
    <div className="min-h-screen flex items-center justify-center relative bg-[hsl(var(--auth-background))] overflow-hidden">
      {/* Background Decoration - High-contrast premium waves and blobs */}
      <div className="absolute inset-0 z-0 pointer-events-none overflow-hidden bg-[radial-gradient(circle_at_20%_20%,hsl(var(--primary)/0.08)_0%,transparent_50%),radial-gradient(circle_at_80%_80%,hsl(var(--primary)/0.08)_0%,transparent_50%)]">
        {/* Top Left Wave */}
        <svg className="absolute -top-[10%] -left-[10%] w-[70%] h-auto text-primary/40 -rotate-12 mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        {/* Bottom Right Wave */}
        <svg className="absolute -bottom-[10%] -right-[10%] w-[70%] h-auto text-primary/40 rotate-[168deg] mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        <div className="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" style={{ animationDelay: '2s' }} />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(circle_at_center,transparent_0%,hsl(var(--auth-background))_100%)] opacity-90" />
      </div>

      <div className="absolute top-6 sm:top-8 left-0 right-0 sm:left-8 sm:right-auto z-20 flex justify-center sm:block">
        <MobileLogo />
      </div>

      <div className="relative z-10 w-full max-w-xl px-4 py-12">

        {success ? (
          <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
            <div className="absolute top-0 left-0 right-0 h-1 bg-green-500 opacity-80" />
            <div className="flex flex-col items-center justify-center py-8 text-center uppercase tracking-wider">
              <div className="mb-6 rounded-full bg-green-500/10 p-4 animate-bounce">
                <CheckCircle2 className="h-12 w-12 text-green-500" />
              </div>
              <h2 className="text-2xl font-extrabold text-foreground mb-3">Password Reset!</h2>
              <p className="text-sm font-medium text-muted-foreground mb-6">
                Your password has been reset successfully.
              </p>
              <div className="flex items-center gap-2 text-xs font-bold text-muted-foreground/60">
                <Spinner className="h-3 w-3" />
                Redirecting to sign in...
              </div>
            </div>
          </div>
        ) : (
          <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
            <div className="absolute top-0 left-0 right-0 h-1 bg-brand-gradient opacity-80" />

            <div className="text-center mb-10">
              <h1 className="text-3xl font-extrabold text-foreground tracking-tight mb-2">
                Reset Password
              </h1>
              <p className="text-sm font-medium text-muted-foreground">
                Enter your new password below
              </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="space-y-2">
                <Label htmlFor="password" className="text-sm font-semibold text-foreground">New Password</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Min 8 characters"
                    {...register("password")}
                    className={`h-11 pl-10 pr-10 bg-background/50 border-border text-foreground placeholder:text-muted-foreground focus:ring-primary ${errors.password ? 'border-destructive' : ''}`}
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
                {errors.password && (
                  <p className="mt-1.5 text-xs font-medium text-destructive">{errors.password.message}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="confirmPassword" className="text-sm font-semibold text-foreground">Confirm Password</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                  <Input
                    id="confirmPassword"
                    type={showConfirmPassword ? "text" : "password"}
                    placeholder="Repeat new password"
                    {...register("confirmPassword")}
                    className={`h-11 pl-10 pr-10 bg-background/50 border-border text-foreground placeholder:text-muted-foreground focus:ring-primary ${errors.confirmPassword ? 'border-destructive' : ''}`}
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showConfirmPassword ? (
                      <EyeOff className="h-4 w-4" />
                    ) : (
                      <Eye className="h-4 w-4" />
                    )}
                  </button>
                </div>
                {errors.confirmPassword && (
                  <p className="mt-1.5 text-xs font-medium text-destructive">{errors.confirmPassword.message}</p>
                )}
              </div>

              {/* Password Requirements */}
              <div className="rounded-xl border border-border/50 bg-muted/30 p-4">
                <p className="text-xs font-bold mb-3 text-foreground uppercase tracking-wider">Requirements:</p>
                <ul className="text-xs font-medium text-muted-foreground space-y-2">
                  <li className="flex items-center gap-2">
                    <div className={`w-4 h-4 rounded-full flex items-center justify-center text-[10px] ${passwordValue.length >= 8 ? 'bg-green-500 text-white' : 'bg-muted border border-border'}`}>
                      {passwordValue.length >= 8 ? '✓' : ''}
                    </div>
                    At least 8 characters
                  </li>
                  <li className="flex items-center gap-2">
                    <div className={`w-4 h-4 rounded-full flex items-center justify-center text-[10px] ${passwordValue === confirmPasswordValue && passwordValue.length > 0 ? 'bg-green-500 text-white' : 'bg-muted border border-border'}`}>
                      {passwordValue === confirmPasswordValue && passwordValue.length > 0 ? '✓' : ''}
                    </div>
                    Passwords match
                  </li>
                </ul>
              </div>

              {error && (
                <div className="rounded-lg bg-destructive/10 border border-destructive/20 p-4 animate-shake">
                  <p className="text-sm font-medium text-destructive text-center">{error}</p>
                </div>
              )}

              <Button
                type="submit"
                className="w-full h-12 text-sm font-bold bg-primary hover:bg-primary/90 text-primary-foreground uppercase tracking-wider rounded-xl shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-all duration-200"
                disabled={isSubmitting || !form.formState.isValid}
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

            {/* Secured by Footer */}
            <div className="mt-8 pt-6 border-t border-border/50">
              <p className="text-[10px] text-muted-foreground font-bold tracking-[0.2em] uppercase text-center">
                Secured by <span className="text-primary font-extrabold">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default ResetPassword;
