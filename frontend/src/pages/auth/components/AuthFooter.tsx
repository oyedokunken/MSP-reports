import { Link } from "react-router-dom";

export function AuthFooter() {
  return (
    <div className="mt-8 pt-6 border-t border-gray-200">
      <p className="text-center text-xs text-gray-500 mb-2">
        By signing in, you agree to our{' '}
        <Link to="/privacy-policy" className="text-indigo-600 hover:text-indigo-500 underline">
          Privacy Policy
        </Link>
        {' '}and{' '}
        <Link to="/terms-of-service" className="text-indigo-600 hover:text-indigo-500 underline">
          Terms of Service
        </Link>
      </p>
    </div>
  );
}
