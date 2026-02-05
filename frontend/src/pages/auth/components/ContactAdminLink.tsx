import { Link } from "react-router-dom";

export function ContactAdminLink() {
  return (
    <div className="text-center">
      <p className="text-sm text-muted-foreground">
        Don't have an account?{' '}
        <Link to="/contact-admin" className="font-semibold text-primary hover:text-primary/80 transition duration-150">
          Contact your administrator
        </Link>
      </p>
    </div>
  );
}
