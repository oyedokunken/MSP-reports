import { Link } from "react-router-dom";

export function ContactAdminLink() {
  return (
    <div className="text-center">
      <p className="text-sm text-gray-700">
        Don't have an account?{' '}
        <Link to="/contact-admin" className="font-semibold text-blue-600 hover:text-blue-800 transition duration-150">
          Contact your administrator
        </Link>
      </p>
    </div>
  );
}
