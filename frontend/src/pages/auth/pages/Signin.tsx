import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Eye, EyeOff } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
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
      <div className="flex min-h-screen w-full items-center justify-center bg-gray-50 p-4">
        <div className="w-full max-w-md">
          {/* Card Container */}
          <div className="bg-white rounded-lg border border-gray-200 shadow-xl p-8">
            {/* Logo */}
            <div className="flex justify-center mb-3">
              <img src="/logo.png" alt="Logo" className="h-24 w-auto" />
            </div>

            {/* Welcome Heading */}
            <div className="text-center space-y-1 mb-6">
              <h1 className="text-2xl font-semibold text-gray-900">
                Welcome
              </h1>
              <p className="text-sm text-gray-600">
                Sign in to continue to Reporting Platform.
              </p>
            </div>

            {/* Form */}
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-2">
                <Input
                  id="email"
                  type="email"
                  placeholder="Email address*"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="h-12 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  required
                  disabled={isSubmitting}
                />
              </div>

              <div className="space-y-2">
                <div className="relative">
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Password*"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="h-12 pr-10 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                    required
                    disabled={isSubmitting}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="h-5 w-5" />
                    ) : (
                      <Eye className="h-5 w-5" />
                    )}
                  </button>
                </div>
                {/* Forgot Password Link */}
                <div className="text-right">
                  <Link to="/forgot-password" className="text-sm text-blue-600 hover:underline">
                    Forgot password?
                  </Link>
                </div>
              </div>

              {error && (
                <div className="text-sm text-red-600 text-center">{error}</div>
              )}

              {/* Privacy Policy Notice */}
              <div className="text-xs text-gray-600 text-center">
                By proceeding, you agree to our{" "}
                <Link to="/privacy-policy" className="text-blue-600 hover:underline">
                  Privacy Policy
                </Link>
                .
              </div>

              <Button 
                type="submit" 
                className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg transition-shadow" 
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <Spinner variant="circle" size={16} className="mr-2" />
                    Signing in...
                  </>
                ) : (
                  "Continue"
                )}
              </Button>
            </form>

            {/* Footer Links */}
            <div className="space-y-3">
              <div className="text-center text-sm text-gray-600">
                Don't have an account?{" "}
                <Link to="/contact-admin" className="text-blue-600 hover:underline font-medium">
                  Contact your administrator
                </Link>
              </div>

              {/* Divider */}
              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-gray-300"></div>
                </div>
                <div className="relative flex justify-center text-sm">
                  <span className="px-2 bg-white text-gray-500">OR</span>
                </div>
              </div>

              {/* SSO Button (Optional - can be removed if not needed) */}
              <Button
                type="button"
                variant="outline"
                className="w-full h-12 text-base font-medium border border-gray-300 hover:bg-gray-50 shadow-sm"
                disabled
              >
                <svg className="w-5 h-5 mr-2" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect x="0.5" y="0.5" width="9" height="9" fill="#F25022"/>
                  <rect x="11.5" y="0.5" width="9" height="9" fill="#7FBA00"/>
                  <rect x="0.5" y="11.5" width="9" height="9" fill="#00A4EF"/>
                  <rect x="11.5" y="11.5" width="9" height="9" fill="#FFB900"/>
                </svg>
                Sign in with Microsoft
              </Button>
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

export default Signin;
