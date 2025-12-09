import { Shield, BarChart3, Zap } from 'lucide-react';

export function BrandingPanel() {
  return (
    <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-blue-600 to-indigo-700 relative overflow-hidden">
      {/* Animated Background Pattern */}
      <div className="absolute inset-0 opacity-10">
        <div className="absolute top-0 -left-4 w-72 h-72 bg-white rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
        <div className="absolute top-0 -right-4 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
        <div className="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
      </div>

      {/* Content - Compact layout to fit without scrolling */}
      <div className="relative z-10 flex flex-col justify-between p-8 lg:p-10 text-white w-full h-full">
        {/* Logo */}
        <div className="flex-shrink-0">
          <img
            src="/logo.png"
            alt="MSP Logo"
            className="h-10 w-auto brightness-0 invert"
          />
        </div>

        {/* Center Content - Text and Features */}
        <div className="flex-1 flex flex-col justify-center space-y-4 py-4">
          <div>
            <h1 className="text-2xl lg:text-3xl font-bold font-athletics mb-1 leading-tight">
              Welcome to MSP Reports Platform
            </h1>
            <p className="text-sm lg:text-base text-indigo-100 font-athletics">
              Centralized insights, automated reports, and seamless integration management.
            </p>
          </div>

          {/* Features - Compact */}
          <div className="grid grid-cols-3 gap-2">
            <div className="text-center">
              <div className="flex justify-center mb-1">
                <div className="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                  <BarChart3 className="w-3.5 h-3.5" />
                </div>
              </div>
              <h3 className="font-semibold font-athletics text-xs">Real-Time Analytics</h3>
            </div>

            <div className="text-center">
              <div className="flex justify-center mb-1">
                <div className="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                  <Shield className="w-3.5 h-3.5" />
                </div>
              </div>
              <h3 className="font-semibold font-athletics text-xs">Enterprise Security</h3>
            </div>

            <div className="text-center">
              <div className="flex justify-center mb-1">
                <div className="w-7 h-7 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                  <Zap className="w-3.5 h-3.5" />
                </div>
              </div>
              <h3 className="font-semibold font-athletics text-xs">Automated Workflows</h3>
            </div>
          </div>
        </div>

        {/* Bottom - Dashboard Image (constrained size) */}
        <div className="flex-shrink-0 flex flex-col items-center">
          <img
            src="/web-programming.png"
            alt="Dashboard Preview"
            className="w-full max-w-sm lg:max-w-md"
          />
          
          {/* Footer */}
          <div className="text-indigo-200 text-xs font-athletics text-center pt-3 mt-2 border-t border-white/10 w-full">
            © 2025 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
          </div>
        </div>
      </div>
    </div>
  );
}
