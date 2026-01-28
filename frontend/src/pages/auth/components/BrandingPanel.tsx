import { Shield, BarChart3, Zap } from 'lucide-react';

export function BrandingPanel() {
  return (
    <div className="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-blue-600 to-indigo-700 relative overflow-hidden h-screen">
      <div className="absolute inset-0 opacity-10">
        <div className="absolute top-0 -left-4 w-72 h-72 bg-white rounded-full mix-blend-multiply filter blur-xl animate-blob"></div>
        <div className="absolute top-0 -right-4 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-2000"></div>
        <div className="absolute -bottom-8 left-20 w-72 h-72 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl animate-blob animation-delay-4000"></div>
      </div>

      <div className="relative z-10 flex flex-col p-6 text-white w-full h-screen max-h-screen overflow-hidden">
        <div className="flex-shrink-0 mb-4">
          <img
            src="/logo.png"
            alt="MSP Logo"
            className="h-8 w-auto brightness-0 invert"
          />
        </div>

        <div className="flex-1 flex items-center justify-center min-h-0 mb-4">
          <img
            src="/web-programming.png"
            alt="Dashboard Preview"
            className="max-w-full max-h-full object-contain"
          />
        </div>

        <div className="flex-shrink-0 mb-3">
          <h1 className="text-xl xl:text-2xl font-bold font-athletics mb-1 leading-tight">
            Welcome to MSP Reports Platform
          </h1>
          <p className="text-xs xl:text-sm text-indigo-100 font-athletics">
            Centralized insights, automated reports, and seamless integration management.
          </p>
        </div>

        <div className="flex-shrink-0 grid grid-cols-3 gap-2 mb-2">
          <div className="text-center">
            <div className="flex justify-center mb-1">
              <div className="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                <BarChart3 className="w-3 h-3" />
              </div>
            </div>
            <h3 className="font-semibold font-athletics text-[10px] xl:text-xs">Real-Time Analytics</h3>
          </div>

          <div className="text-center">
            <div className="flex justify-center mb-1">
              <div className="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                <Shield className="w-3 h-3" />
              </div>
            </div>
            <h3 className="font-semibold font-athletics text-[10px] xl:text-xs">Enterprise Security</h3>
          </div>

          <div className="text-center">
            <div className="flex justify-center mb-1">
              <div className="w-6 h-6 bg-white/20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                <Zap className="w-3 h-3" />
              </div>
            </div>
            <h3 className="font-semibold font-athletics text-[10px] xl:text-xs">Automated Workflows</h3>
          </div>
        </div>
          
        <div className="flex-shrink-0 text-indigo-200 text-[10px] font-athletics text-center pt-2 mt-2 border-t border-white/10">
          © 2025 {import.meta.env.VITE_COMPANY_NAME || 'MSP'}. All rights reserved.
        </div>
      </div>
    </div>
  );
}
