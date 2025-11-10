import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
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
      <div className="flex min-h-screen w-full items-center justify-center bg-gray-50 p-4">
        <div className="w-full max-w-md">
          {/* Card Container */}
          <div className="bg-white rounded-lg border border-gray-200 shadow-xl p-8">
            {/* Logo */}
            <div className="flex justify-center mb-10">
              <img src="/logo.png" alt="Logo" className="h-8 w-auto opacity-90" />
            </div>

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

          {/* Bottom Footer */}
          <div className="mt-6 text-center">
            <p className="text-xs text-gray-500">
              Secured by <span className="font-semibold text-gray-700">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen w-full items-center justify-center bg-gray-50 p-4">
      <div className="w-full max-w-md">
        {/* Card Container */}
        <div className="bg-white rounded-lg border border-gray-200 shadow-xl p-8">
          {/* Logo */}
          <div className="flex justify-center mb-10">
            <img src="/logo.png" alt="Logo" className="h-8 w-auto opacity-90" />
          </div>

          {/* Heading */}
          <div className="text-center space-y-2 mb-10">
            <h1 className="text-3xl font-bold text-gray-900">
              Forgot Password?
            </h1>
            <p className="text-base text-gray-600">
              Enter your email and we'll send you a reset link
            </p>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-2">
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email Address
              </label>
              <Input
                id="email"
                type="email"
                placeholder="your.email@company.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="h-11 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                required
                disabled={isSubmitting}
              />
            </div>

            {error && (
              <div className="rounded-md bg-red-50 border border-red-200 p-3">
                <p className="text-sm text-red-800 text-center">{error}</p>
              </div>
            )}

            <Button 
              type="submit" 
              className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white" 
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <Spinner variant="circle" size={16} className="mr-2" />
                  Sending...
                </>
              ) : (
                "Send Reset Link"
              )}
            </Button>
          </form>

          {/* Back to Sign In */}
          <div className="mt-6 text-center">
            <Link to="/login" className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1">
              <ArrowLeft className="h-4 w-4" />
              Back to Sign In
            </Link>
          </div>
        </div>

        {/* Bottom Footer */}
        <div className="mt-6 text-center">
          <p className="text-xs text-gray-500">
            Secured by <span className="font-semibold text-gray-700">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
          </p>
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
