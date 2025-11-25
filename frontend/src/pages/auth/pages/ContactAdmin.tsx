import { Mail, Phone, ArrowLeft, Shield, BarChart3, Zap } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";
const ContactAdmin = () => {
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
      {/* Right Panel - Contact Info */}
      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <div className="lg:hidden text-center mb-8">
            <img src="/logo.png" alt="MSP Logo" className="h-12 w-auto mx-auto" />
          </div>
          <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
          <div className="text-center space-y-1 mb-6">
            <h1 className="text-2xl font-semibold text-gray-900">
              Need an Account?
            </h1>
            <p className="text-sm text-gray-600">
              Contact your administrator to request access
            </p>
          </div>

          {/* Contact Information */}
          <div className="space-y-4 mb-6">
            <div className="bg-gray-50 rounded-lg p-4 space-y-3">
              <div className="flex items-start gap-3">
                <Mail className="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-sm font-medium text-gray-900">Email Support</p>
                  <a 
                    href={`mailto:${import.meta.env.VITE_SUPPORT_EMAIL || 'support@athenanetworks.com.au'}`}
                    className="text-sm text-blue-600 hover:underline"
                  >
                    {import.meta.env.VITE_SUPPORT_EMAIL || 'support@athenanetworks.com.au'}
                  </a>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <Phone className="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                <div>
                  <p className="text-sm font-medium text-gray-900">Phone Support</p>
                  <a 
                    href={`tel:${import.meta.env.VITE_SUPPORT_PHONE || '1300000000'}`}
                    className="text-sm text-blue-600 hover:underline"
                  >
                    {import.meta.env.VITE_SUPPORT_PHONE || '1300 000 000'}
                  </a>
                </div>
              </div>
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <p className="text-sm text-gray-700">
                <strong className="text-gray-900">Note:</strong> Only authorized users can access this platform. 
                Please contact your system administrator to request an account or reset your credentials.
              </p>
            </div>
          </div>

          {/* Back to Sign In */}
          <Link to="/login" className="w-full">
            <Button className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg transition-shadow">
              <ArrowLeft className="mr-2 h-4 w-4" />
              Back to Sign In
            </Button>
          </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ContactAdmin;
