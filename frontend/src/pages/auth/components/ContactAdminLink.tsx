import { Link } from "react-router-dom";

export function ContactAdminLink() {
  return (
    <div className="mt-6 text-center">
      <p className="text-sm text-gray-600 font-athletics">
        Don't have an account?{' '}
        <Link to="/contact-admin" className="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 font-athletics">
          Contact your administrator
        </Link>
      </p>
    </div>
  );
}
