import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ArrowLeft, CheckCircle2, BarChart3 } from "lucide-react";
import { Spinner } from "@/components/ui/spinner";
import { Link } from "react-router-dom";
import { api } from "@/lib/api";

const ForgotPassword = () => {
  const [email, setEmail] = useState("");
  const [error, setError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setIsSubmitting(true);

    try {
      await api.post("/auth/password/request", { email });
      setSuccess(true);
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || "Failed to send reset email";
      setError(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (success) {
    return (
      <div className="flex h-screen w-full overflow-hidden">
        {/* Left Panel - Branding */}
        <div className="relative hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-16 text-white bg-primary">
          <div className="relative z-10 flex flex-col gap-6 w-full max-w-lg">
            <div className="flex items-center gap-3">
              <BarChart3 className="h-8 w-8 text-emerald-400" />
              <span className="text-xl font-bold">{import.meta.env.VITE_APP_NAME || 'Reporting Platform'}</span>
            </div>
            <h1 className="text-5xl font-black leading-tight">
              Unlock Your Data Insights.
            </h1>
            <p className="text-lg text-slate-300">
              Access, analyze, and share your managed services reports with confidence.
            </p>
          </div>
        </div>

        {/* Right Panel - Success Message */}
        <div className="flex w-full lg:w-1/2 flex-col items-center justify-center bg-background p-6 sm:p-12 overflow-y-auto">
          <div className="w-full max-w-md space-y-6">
            <div className="flex items-center gap-3 lg:hidden">
              <BarChart3 className="h-8 w-8 text-primary" />
              <span className="text-xl font-bold">{import.meta.env.VITE_APP_NAME || 'Reporting Platform'}</span>
            </div>

            <div className="flex flex-col items-center justify-center py-8 text-center">
              <div className="mb-4 rounded-full bg-green-500/10 p-3">
                <CheckCircle2 className="h-12 w-12 text-green-500" />
              </div>
              <h2 className="text-2xl font-bold text-green-600 mb-2">Check Your Email</h2>
              <p className="text-sm text-muted-foreground mb-6">
                We've sent a password reset link to <strong>{email}</strong>
              </p>
              <p className="text-xs text-muted-foreground mb-4">
                Didn't receive the email? Check your spam folder or try again.
              </p>
              <Link to="/sign-in" className="w-full">
                <Button variant="outline" className="w-full h-11">
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  Back to Sign In
                </Button>
              </Link>
            </div>

            {/* Secured by Footer */}
            <div className="space-y-4 pt-4">
              <div className="relative">
                <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>
                <div className="flex justify-center pt-4">
                  <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                    Secured by <span className="text-primary font-semibold">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-screen w-full overflow-hidden">
      {/* Left Panel - Branding */}
      <div className="relative hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-16 text-white bg-primary">
        <div className="relative z-10 flex flex-col gap-6 w-full max-w-lg">
          <div className="flex items-center gap-3">
            <BarChart3 className="h-8 w-8 text-emerald-400" />
            <span className="text-xl font-bold">{import.meta.env.VITE_APP_NAME || 'Reporting Platform'}</span>
          </div>
          <h1 className="text-5xl font-black leading-tight">
            Unlock Your Data Insights.
          </h1>
          <p className="text-lg text-slate-300">
            Access, analyze, and share your managed services reports with confidence.
          </p>
        </div>
      </div>

      {/* Right Panel - Form */}
      <div className="flex w-full lg:w-1/2 flex-col items-center justify-center bg-background p-6 sm:p-12 overflow-y-auto">
        <div className="w-full max-w-md space-y-6">
          <div className="flex items-center gap-3 lg:hidden">
            <BarChart3 className="h-8 w-8 text-primary" />
            <span className="text-xl font-bold">MSP Reports</span>
          </div>

          <div>
            <h1 className="text-3xl font-bold text-foreground">
              Forgot Password?
            </h1>
            <p className="text-sm text-muted-foreground mt-2">
              Enter your email and we'll send you a reset link
            </p>
          </div>

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

            {error && (
              <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive">
                {error}
              </div>
            )}

            <Button type="submit" className="w-full h-11 text-base font-semibold" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Spinner className="mr-2 h-4 w-4" />
                  Sending...
                </>
              ) : (
                "Send Reset Link"
              )}
            </Button>
          </form>

          <div className="space-y-4 pt-4">
            <div className="text-center">
              <Link to="/sign-in" className="text-sm text-primary hover:underline flex items-center justify-center gap-1">
                <ArrowLeft className="h-4 w-4" />
                Back to Sign In
              </Link>
            </div>

            {/* Secured by Footer */}
            <div className="relative">
              <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-primary to-transparent opacity-60"></div>
              <div className="flex justify-center pt-4">
                <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                  Secured by <span className="text-primary font-semibold">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
