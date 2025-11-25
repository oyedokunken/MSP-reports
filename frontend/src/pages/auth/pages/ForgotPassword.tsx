import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ArrowLeft, CheckCircle2, Shield, BarChart3, Zap } from "lucide-react";
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
      <div className="min-h-screen flex">
        {/* Left Panel - Branding */}
        <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-blue-600 to-indigo-700 relative overflow-hidden">
          {/* Animated Background Pattern */}
          <div className="absolute inset-0 opacity-10">
            <div className="absolute top-0 -left-4 w-72 h-72 bg-white rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
            <div className="absolute top-0 -right-4 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
            <div className="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
          </div>

          {/* Content */}
          <div className="relative z-10 flex flex-col justify-between p-12 text-white w-full">
            {/* Logo */}
            <div>
              <img 
                src="/logo.png" 
                alt="MSP Logo" 
                className="h-12 w-auto brightness-0 invert"
              />
            </div>

            {/* Center Dashboard Image */}
            <div className="flex items-center justify-center">
              <img 
                src="/web-programming.png" 
                alt="Dashboard Preview" 
                className="w-full max-w-lg"
              />
            </div>

            {/* Bottom Content */}
            <div className="space-y-6">
              <div>
                <h1 className="text-3xl font-bold font-athletics mb-2 leading-tight">
                  Welcome to MSP Reports Platform
                </h1>
                <p className="text-base text-indigo-100 font-athletics">
                  Centralized insights, automated reports, and seamless integration management.
                </p>
              </div>

              {/* Features - Compact */}
              <div className="grid grid-cols-3 gap-3">
                <div className="text-center">
                  <div className="flex justify-center mb-2">
                    <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                      <BarChart3 className="w-4 h-4" />
                    </div>
                  </div>
                  <h3 className="font-semibold font-athletics text-xs">Real-Time Analytics</h3>
                </div>

                <div className="text-center">
                  <div className="flex justify-center mb-2">
                    <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                      <Shield className="w-4 h-4" />
                    </div>
                  </div>
                  <h3 className="font-semibold font-athletics text-xs">Enterprise Security</h3>
                </div>

                <div className="text-center">
                  <div className="flex justify-center mb-2">
                    <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                      <Zap className="w-4 h-4" />
                    </div>
                  </div>
                  <h3 className="font-semibold font-athletics text-xs">Automated Workflows</h3>
                </div>
              </div>

              {/* Footer */}
              <div className="text-indigo-200 text-xs font-athletics text-center pt-2 border-t border-white/10">
                © 2024 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
              </div>
            </div>
          </div>
        </div>
        {/* Right Panel - Success Message */}
        <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
          <div className="max-w-md w-full">
            <div className="lg:hidden text-center mb-8">
              <img src="/logo.png" alt="MSP Logo" className="h-12 w-auto mx-auto" />
            </div>
            <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
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
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex">
      {/* Left Panel - Branding */}
      <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-blue-600 to-indigo-700 relative overflow-hidden">
        {/* Animated Background Pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-0 -left-4 w-72 h-72 bg-white rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
          <div className="absolute top-0 -right-4 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
          <div className="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
        </div>

        {/* Content */}
        <div className="relative z-10 flex flex-col justify-between p-12 text-white w-full">
          {/* Logo */}
          <div>
            <img 
              src="/logo.png" 
              alt="MSP Logo" 
              className="h-12 w-auto brightness-0 invert"
            />
          </div>

          {/* Center Dashboard Image */}
          <div className="flex items-center justify-center">
            <img 
              src="/web-programming.png" 
              alt="Dashboard Preview" 
              className="w-full max-w-lg"
            />
          </div>

          {/* Bottom Content */}
          <div className="space-y-6">
            <div>
              <h1 className="text-3xl font-bold font-athletics mb-2 leading-tight">
                Welcome to MSP Reports Platform
              </h1>
              <p className="text-base text-indigo-100 font-athletics">
                Centralized insights, automated reports, and seamless integration management.
              </p>
            </div>

            {/* Features - Compact */}
            <div className="grid grid-cols-3 gap-3">
              <div className="text-center">
                <div className="flex justify-center mb-2">
                  <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                    <BarChart3 className="w-4 h-4" />
                  </div>
                </div>
                <h3 className="font-semibold font-athletics text-xs">Real-Time Analytics</h3>
              </div>

              <div className="text-center">
                <div className="flex justify-center mb-2">
                  <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                    <Shield className="w-4 h-4" />
                  </div>
                </div>
                <h3 className="font-semibold font-athletics text-xs">Enterprise Security</h3>
              </div>

              <div className="text-center">
                <div className="flex justify-center mb-2">
                  <div className="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                    <Zap className="w-4 h-4" />
                  </div>
                </div>
                <h3 className="font-semibold font-athletics text-xs">Automated Workflows</h3>
              </div>
            </div>

            {/* Footer */}
            <div className="text-indigo-200 text-xs font-athletics text-center pt-2 border-t border-white/10">
              © 2024 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
            </div>
          </div>
        </div>
      </div>
      {/* Right Panel - Form */}
      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <div className="lg:hidden text-center mb-8">
            <img src="/logo.png" alt="MSP Logo" className="h-12 w-auto mx-auto" />
          </div>
          <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
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
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
