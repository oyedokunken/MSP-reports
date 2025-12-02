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
            © 2025 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
          </div>
        </div>
      </div>
    </div>
  );
}
