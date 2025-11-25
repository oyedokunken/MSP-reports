import { useState, useEffect } from "react";
import { MdSecurity, MdStorage } from 'react-icons/md';
import { Eye, EyeOff } from 'lucide-react';
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link } from "react-router-dom";
import { useAuthForm } from "../hooks/useAuthForm";
import { useToast } from "@/components/ui/Toast";

const Signin = () => {
    const toast = useToast();
    const [rememberMe, setRememberMe] = useState(false);
    const [showAuthOptions, setShowAuthOptions] = useState(false);
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
        toast.success('Login Successful', 'Welcome back!')
      },
      onError: (err) => {
        console.error('Login failed:', err)
      }
    });

    // Lock body scroll while enterprise auth drawer is open
    useEffect(() => {
      if (!showAuthOptions) return;
      const prevOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = prevOverflow || '';
      };
    }, [showAuthOptions]);

    return (
      <div className="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full">
          {/* Main Container */}
          <div className="bg-white rounded-2xl shadow-xl p-8">
            {/* Logo and Header */}
            <div className="text-center mb-8">
              <div className="flex justify-center">
                <img 
                  src="/logo.png" 
                  alt="MSP Logo" 
                  className="h-16 w-auto"
                />
              </div>
              <p className="mt-2 text-sm text-gray-600 font-athletics">
                Sign in to your MSP account
              </p>
            </div>

            {/* Sign In Form */}
            <div
              aria-hidden={showAuthOptions}
              className={`transform transition-all duration-300 ease-in-out ${
                showAuthOptions
                  ? 'max-h-0 opacity-0 -translate-y-2 pointer-events-none overflow-hidden'
                  : 'max-h-[1000px] opacity-100 translate-y-0'
              }`}
            >
              <form className="space-y-6" onSubmit={handleSubmit}>
                {/* Email Field */}
                <div>
                  <label htmlFor="email" className="block text-sm text-left font-medium text-gray-700 mb-2 font-athletics">
                    Email address
                  </label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    disabled={isSubmitting}
                    className="appearance-none relative block w-full px-4 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150 ease-in-out"
                    placeholder="admin@example.com"
                  />
                </div>

                {/* Password Field */}
                <div>
                  <label htmlFor="password" className="block text-sm text-left font-medium text-gray-700 mb-2 font-athletics">
                    Password
                  </label>
                  <div className="relative">
                    <input
                      id="password"
                      name="password"
                      type={showPassword ? "text" : "password"}
                      autoComplete="current-password"
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      disabled={isSubmitting}
                      className="appearance-none relative block w-full px-4 py-3 pr-12 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150 ease-in-out"
                      placeholder="*******"
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
                </div>

                {/* Remember Me and Forgot Password */}
                <div className="flex items-center justify-between">
                  <div className="flex items-center">
                    <input
                      id="remember-me"
                      name="remember-me"
                      type="checkbox"
                      checked={rememberMe}
                      onChange={(e) => setRememberMe(e.target.checked)}
                      className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer"
                    />
                    <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-700 cursor-pointer font-athletics">
                      Remember me
                    </label>
                  </div>

                  <div className="text-sm">
                    <Link to="/forgot-password" className="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 font-athletics">
                      Forgot password?
                    </Link>
                  </div>
                </div>

                {/* Error Message */}
                {error && (
                  <div className="rounded-md bg-red-50 border border-red-200 p-3">
                    <p className="text-sm text-red-800 text-center">{error}</p>
                  </div>
                )}

                {/* Sign In Button */}
                <div>
                  <button
                    type="submit"
                    disabled={isSubmitting}
                    className="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out shadow-md hover:shadow-lg font-athletics disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {isSubmitting ? (
                      <>
                        <Spinner variant="circle" size={16} className="mr-2" />
                        Signing in...
                      </>
                    ) : (
                      "Sign in"
                    )}
                  </button>
                </div>
              </form>
        
            </div>

            {/* Divider with Toggle Button */}
            <div className="mt-6">
              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-gray-300"></div>
                </div>
                <div className="relative flex justify-center">
                  <button
                    type="button"
                    onClick={() => setShowAuthOptions(!showAuthOptions)}
                    className="px-4 py-1.5 bg-white border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out flex items-center gap-2 font-athletics"
                  >
                    <span>Or</span>
                    <svg 
                      className={`w-4 h-4 transition-transform duration-200 ${showAuthOptions ? 'rotate-180' : ''}`} 
                      fill="none" 
                      stroke="currentColor" 
                      viewBox="0 0 24 24"
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              </div>

              {/* Enterprise Authentication Buttons - Collapsible Drawer */}
              <div 
                className={`overflow-hidden transition-all duration-300 ease-in-out ${
                  showAuthOptions ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
                }`}
              >
                <div className="space-y-3">
                  {/* SSO Login */}
                  <button
                    type="button"
                    className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
                  >
                    <MdSecurity className="w-5 h-5 mr-3 text-green-600" />
                    <span>Sign in with SSO</span>
                  </button>

                  {/* Microsoft 365 Login */}
                  <button
                    type="button"
                    className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
                  >
                    <svg className="w-5 h-5 mr-3" viewBox="0 0 23 23" fill="none">
                      <rect width="11" height="11" fill="#F25022"/>
                      <rect x="12" width="11" height="11" fill="#7FBA00"/>
                      <rect y="12" width="11" height="11" fill="#00A4EF"/>
                      <rect x="12" y="12" width="11" height="11" fill="#FFB900"/>
                    </svg>
                    <span>Sign in with Microsoft 365</span>
                  </button>

                  {/* Active Directory/LDAP Login */}
                  <button
                    type="button"
                    className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
                  >
                    <MdStorage className="w-5 h-5 mr-3 text-indigo-600" />
                    <span>Sign in with Active Directory/LDAP</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Sign Up Link */}
            <div className="mt-6 text-center">
              <p className="text-sm text-gray-600 font-athletics">
                Don't have an account?{' '}
                <Link to="/contact-admin" className="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 font-athletics">
                  Contact your administrator
                </Link>
              </p>
            </div>

            {/* Footer */}
            <div className="mt-8 pt-6 border-t border-gray-200">
              <p className="text-center text-xs text-gray-500 mb-2 font-athletics">
                By signing in, you agree to our{' '}
                <Link to="/privacy-policy" className="text-indigo-600 hover:text-indigo-500 underline font-athletics">
                  Privacy Policy
                </Link>
                {' '}and{' '}
                <a href="#" className="text-indigo-600 hover:text-indigo-500 underline font-athletics">
                  Terms of Service
                </a>
              </p>
              <p className="text-center text-xs text-gray-500 font-athletics">
                © 2024 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
              </p>
            </div>
          </div>
        </div>
      </div>
    );
}

export default Signin;
