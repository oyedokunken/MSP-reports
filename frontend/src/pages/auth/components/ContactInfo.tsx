import { Mail, Phone, ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Link } from "react-router-dom";

export function ContactInfo() {
  return (
    <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
      <div className="absolute top-0 left-0 right-0 h-1 bg-brand-gradient opacity-80" />

      <div className="text-center space-y-2 mb-10">
        <h1 className="text-3xl font-extrabold text-foreground tracking-tight">
          Account Access
        </h1>
        <p className="text-sm font-medium text-muted-foreground">
          Contact your administrator to request access
        </p>
      </div>

      <div className="space-y-6 mb-8">
        <div className="bg-muted/30 rounded-xl p-6 space-y-5 border border-border/50">
          <div className="flex items-start gap-4">
            <div className="p-2.5 bg-primary/10 rounded-lg">
              <Mail className="h-5 w-5 text-primary" />
            </div>
            <div>
              <p className="text-sm font-bold text-foreground">Email Support</p>
              <a
                href={`mailto:${import.meta.env.VITE_SUPPORT_EMAIL || 'support@athenanetworks.com.au'}`}
                className="text-sm text-primary hover:text-primary/80 transition-colors font-medium break-all"
              >
                {import.meta.env.VITE_SUPPORT_EMAIL || 'support@athenanetworks.com.au'}
              </a>
            </div>
          </div>

          <div className="flex items-start gap-4">
            <div className="p-2.5 bg-primary/10 rounded-lg">
              <Phone className="h-5 w-5 text-primary" />
            </div>
            <div>
              <p className="text-sm font-bold text-foreground">Phone Support</p>
              <a
                href={`tel:${import.meta.env.VITE_SUPPORT_PHONE || '1300000000'}`}
                className="text-sm text-primary hover:text-primary/80 transition-colors font-medium"
              >
                {import.meta.env.VITE_SUPPORT_PHONE || '1300 000 000'}
              </a>
            </div>
          </div>
        </div>

        <div className="bg-primary/5 border border-primary/20 rounded-xl p-5">
          <p className="text-xs font-medium text-muted-foreground leading-relaxed">
            <strong className="text-foreground">Security Note:</strong> Only authorized users can access this platform.
            Please contact your system administrator to request an account or reset your credentials.
          </p>
        </div>
      </div>

      <Link to="/login" className="w-full">
        <Button className="w-full h-12 text-sm font-bold bg-primary hover:bg-primary/90 text-primary-foreground uppercase tracking-wider rounded-xl shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-all duration-200">
          <ArrowLeft className="mr-2 h-4 w-4" />
          Back to Sign In
        </Button>
      </Link>
    </div>
  );
}
