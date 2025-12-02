import { MdSecurity, MdStorage } from 'react-icons/md';

interface EnterpriseAuthOptionsProps {
  isOpen: boolean;
}

export function EnterpriseAuthOptions({ isOpen }: EnterpriseAuthOptionsProps) {
  return (
    <div
      className={`overflow-hidden transition-all duration-300 ease-in-out ${
        isOpen ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
      }`}
    >
      <div className="space-y-3">
        {/* SSO Login */}
        <button
          type="button"
          className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
        >
          <MdSecurity className="w-5 h-5 mr-3 text-green-600" />
          <span>Sign in with SSO</span>
        </button>

        {/* Microsoft 365 Login */}
        <button
          type="button"
          className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
        >
          <svg className="w-5 h-5 mr-3" viewBox="0 0 23 23" fill="none">
            <rect width="11" height="11" fill="#F25022" />
            <rect x="12" width="11" height="11" fill="#7FBA00" />
            <rect y="12" width="11" height="11" fill="#00A4EF" />
            <rect x="12" y="12" width="11" height="11" fill="#FFB900" />
          </svg>
          <span>Sign in with Microsoft 365</span>
        </button>

        {/* Active Directory/LDAP Login */}
        <button
          type="button"
          className="w-full inline-flex items-center justify-center py-3 px-4 border border-gray-300 rounded-lg shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out font-athletics"
        >
          <MdStorage className="w-5 h-5 mr-3 text-indigo-600" />
          <span>Sign in with Active Directory/LDAP</span>
        </button>
      </div>
    </div>
  );
}
