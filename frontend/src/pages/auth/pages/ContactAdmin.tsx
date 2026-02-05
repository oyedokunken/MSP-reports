import { MobileLogo, ContactInfo } from "../components";

const ContactAdmin = () => {
  return (
    <div className="min-h-screen flex items-center justify-center relative bg-[hsl(var(--auth-background))] overflow-hidden">
      {/* Background Decoration - High-contrast premium waves and blobs */}
      <div className="absolute inset-0 z-0 pointer-events-none overflow-hidden bg-[radial-gradient(circle_at_20%_20%,hsl(var(--primary)/0.08)_0%,transparent_50%),radial-gradient(circle_at_80%_80%,hsl(var(--primary)/0.08)_0%,transparent_50%)]">
        {/* Top Left Wave */}
        <svg className="absolute -top-[10%] -left-[10%] w-[70%] h-auto text-primary/40 -rotate-12 mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        {/* Bottom Right Wave */}
        <svg className="absolute -bottom-[10%] -right-[10%] w-[70%] h-auto text-primary/40 rotate-[168deg] mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        <div className="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" style={{ animationDelay: '2s' }} />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(circle_at_center,transparent_0%,hsl(var(--auth-background))_100%)] opacity-90" />
      </div>

      <div className="absolute top-6 sm:top-8 left-0 right-0 sm:left-8 sm:right-auto z-20 flex justify-center sm:block">
        <MobileLogo />
      </div>

      <div className="relative z-10 w-full max-w-xl px-4 py-12">
        <ContactInfo />
      </div>
    </div>
  );
};

export default ContactAdmin;
