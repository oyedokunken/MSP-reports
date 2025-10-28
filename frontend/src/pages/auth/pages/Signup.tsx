import { Button } from "@/components/ui/button";
import { AlertCircle, ArrowLeft, BarChart3 } from "lucide-react";
import { Link } from "react-router-dom";

const Signup = () => {
  return (
    <div className="flex h-screen w-full overflow-hidden">
      {/* Left Panel - Branding */}
      <div className="relative hidden lg:flex lg:w-1/2 flex-col items-center justify-center p-16 text-white" style={{ backgroundColor: '#243f5f' }}>
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

      {/* Right Panel - Content */}
      <div className="flex w-full lg:w-1/2 flex-col items-center justify-center bg-background p-6 sm:p-12 overflow-y-auto">
        <div className="w-full max-w-md space-y-6">
          <div className="flex items-center gap-3 lg:hidden">
            <BarChart3 className="h-8 w-8 text-primary" />
            <span className="text-xl font-bold">MSP Reports</span>
          </div>

          <div className="space-y-4 text-center">
            <div className="mx-auto w-16 h-16 rounded-full bg-blue-500/10 flex items-center justify-center">
              <AlertCircle className="h-8 w-8 text-blue-500" />
            </div>
            <h1 className="text-3xl font-bold text-foreground">Invitation Only</h1>
            <p className="text-sm text-muted-foreground">
              This is an enterprise platform. New accounts must be created by your organization's administrator.
            </p>
          </div>

          <div className="rounded-lg border border-border bg-muted/50 p-4 space-y-3">
            <h3 className="font-semibold text-sm">Need Access?</h3>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li className="flex items-start gap-2">
                <span className="text-primary mt-0.5">•</span>
                <span>Contact your system administrator to request an account</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-primary mt-0.5">•</span>
                <span>Your admin can create users from the dashboard</span>
              </li>
              <li className="flex items-start gap-2">
                <span className="text-primary mt-0.5">•</span>
                <span>You'll receive login credentials via email</span>
              </li>
            </ul>
          </div>

          <Link to="/sign-in" className="w-full block">
            <Button className="w-full h-11 text-base font-semibold text-white" style={{ backgroundColor: '#243f5f' }}>
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Sign In
            </Button>
          </Link>

          {/* Footer */}
          <div className="space-y-4 pt-4">
            <div className="text-center text-sm text-muted-foreground">
              Already have an account?{" "}
              <Link to="/sign-in" className="text-primary font-semibold hover:underline">
                Sign in here
              </Link>
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

export default Signup;
