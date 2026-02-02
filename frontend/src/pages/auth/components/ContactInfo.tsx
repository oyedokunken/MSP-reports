import { Mail, Phone, ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";

export function ContactInfo() {
  return (
    <div className="bg-white rounded-xl shadow-xl p-8 border border-gray-100">
      <div className="text-center space-y-1 mb-6">
        <h1 className="text-2xl font-semibold text-gray-900">
          Need an Account?
        </h1>
        <p className="text-sm text-gray-600">
          Contact your administrator to request access
        </p>
      </div>

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

      <Link to="/login" className="w-full">
        <Button className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg transition-shadow">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to Sign In
        </Button>
      </Link>
    </div>
  );
}
