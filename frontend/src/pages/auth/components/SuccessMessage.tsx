import { Button } from "@/components/ui/button";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import { Link } from "react-router-dom";

interface SuccessMessageProps {
  email: string;
}

export function SuccessMessage({ email }: SuccessMessageProps) {
  return (
    <div className="bg-white rounded-xl shadow-xl p-8 border border-gray-100">
      {/* Success Icon and Message */}
      <div className="flex flex-col items-center text-center space-y-5">
        <div className="rounded-full bg-green-500/10 p-3">
          <CheckCircle2 className="h-16 w-16 text-green-500" />
        </div>

        <div className="space-y-2">
          <h2 className="text-2xl font-semibold text-gray-900">Check Your Email</h2>
          <p className="text-sm text-gray-600">
            We've sent a password reset link to<br />
            <strong className="text-gray-900">{email}</strong>
          </p>
        </div>

        <p className="text-xs text-gray-500">
          Didn't receive the email? Check your spam folder or try again.
        </p>

        <Link to="/login" className="w-full pt-2">
          <Button variant="outline" className="w-full h-12 border-gray-300">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Sign In
          </Button>
        </Link>
      </div>
    </div>
  );
}
