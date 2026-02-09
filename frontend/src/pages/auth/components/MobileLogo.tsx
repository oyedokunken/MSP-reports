export function MobileLogo() {
  return (
    <div className="text-center mb-8">
      <img
        src="/logo.png"
        alt="MSP Logo"
        className="h-12 w-auto mx-auto animate-logo-periodic dark:hidden"
      />
      <img
        src="/logo-white.png"
        alt="MSP Logo"
        className="h-12 w-auto mx-auto animate-logo-periodic hidden dark:block"
      />
    </div>
  );
}
