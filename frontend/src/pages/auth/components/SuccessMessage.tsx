import { Button } from "@/components/ui/button";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import { Link } from "react-router-dom";

interface SuccessMessageProps {
  email: string;
}

export function SuccessMessage({ email }: SuccessMessageProps) {
  return (
    <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
      <div className="absolute top-0 left-0 right-0 h-1 bg-green-500 opacity-80" />

      {/* Success Icon and Message */}
      <div className="flex flex-col items-center text-center space-y-6">
        <div className="rounded-full bg-green-500/10 p-4 animate-pulse">
          <CheckCircle2 className="h-16 w-16 text-green-500" />
        </div>

        <div className="space-y-2">
          <h2 className="text-3xl font-extrabold text-foreground tracking-tight">Check Your Email</h2>
          <p className="text-sm font-medium text-muted-foreground">
            We've sent a password reset link to<br />
            <strong className="text-foreground block mt-1 font-bold">{email}</strong>
          </p>
        </div>

        <p className="text-xs font-medium text-muted-foreground bg-muted/30 p-3 rounded-lg">
          Didn't receive the email? Check your spam folder or try again.
        </p>

        <Link to="/login" className="w-full pt-2">
          <Button variant="outline" className="w-full h-12 border-border/80 font-bold uppercase tracking-wider rounded-xl hover:bg-muted/50 transition-all duration-200">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Sign In
          </Button>
        </Link>
      </div>
    </div>
  );
}
