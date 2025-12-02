import { BrandingPanel, MobileLogo, ContactInfo } from "../components";

const ContactAdmin = () => {
  return (
    <div className="min-h-screen flex">
      <BrandingPanel />
      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <MobileLogo />
          <ContactInfo />
        </div>
      </div>
    </div>
  );
};

export default ContactAdmin;
