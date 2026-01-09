import { MdSecurity, MdStorage } from 'react-icons/md';
import { Badge } from '@/components/ui/badge';

interface EnterpriseAuthOptionsProps {
  isOpen: boolean;
  onLdapClick: () => void;
  onOAuthClick: () => void;
  onSamlClick: () => void;
  ldapConfigured?: boolean;
  oauthConfigured?: boolean;
  samlConfigured?: boolean;
}

export function EnterpriseAuthOptions({ isOpen, onLdapClick, onOAuthClick, onSamlClick, ldapConfigured = false, oauthConfigured = false, samlConfigured = false }: EnterpriseAuthOptionsProps) {
  return (
    <div
      className={`overflow-hidden transition-all duration-300 ease-in-out ${
        isOpen ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
      }`}
    >
      <div className="space-y-3">
        {/* SAML/SSO Login */}
        <button
          type="button"
          onClick={onSamlClick}
          disabled={!samlConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${
            samlConfigured
              ? 'border-purple-300 bg-purple-50 text-purple-700 hover:bg-purple-100'
              : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
          }`}
        >
          <MdSecurity className={`w-5 h-5 mr-3 ${samlConfigured ? 'text-purple-600' : 'text-gray-400'}`} />
          <span>Sign in with SSO</span>
          {samlConfigured ? (
            <Badge className="ml-auto text-xs bg-green-500 hover:bg-green-600">Ready</Badge>
          ) : (
            <Badge variant="secondary" className="ml-auto text-xs">Pending Config</Badge>
          )}
        </button>

        {/* Microsoft 365 Login */}
        <button
          type="button"
          onClick={onOAuthClick}
          disabled={!oauthConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${
            oauthConfigured
              ? 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100'
              : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
          }`}
        >
          <svg className={`w-5 h-5 mr-3 ${oauthConfigured ? 'opacity-100' : 'opacity-40'}`} viewBox="0 0 23 23" fill="none">
            <rect width="11" height="11" fill="#F25022" />
            <rect x="12" width="11" height="11" fill="#7FBA00" />
            <rect y="12" width="11" height="11" fill="#00A4EF" />
            <rect x="12" y="12" width="11" height="11" fill="#FFB900" />
          </svg>
          <span>Sign in with Microsoft 365</span>
          {oauthConfigured ? (
            <Badge className="ml-auto text-xs bg-green-500 hover:bg-green-600">Ready</Badge>
          ) : (
            <Badge variant="secondary" className="ml-auto text-xs">Pending Config</Badge>
          )}
        </button>

        {/* Active Directory/LDAP Login */}
        <button
          type="button"
          onClick={onLdapClick}
          disabled={!ldapConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${
            ldapConfigured
              ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
              : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
          }`}
        >
          <MdStorage className={`w-5 h-5 mr-3 ${ldapConfigured ? 'text-indigo-600' : 'text-gray-400'}`} />
          <span>Sign in with Active Directory</span>
          {ldapConfigured ? (
            <Badge className="ml-auto text-xs bg-green-500 hover:bg-green-600">Ready</Badge>
          ) : (
            <Badge variant="secondary" className="ml-auto text-xs">Pending Config</Badge>
          )}
        </button>
      </div>
    </div>
  );
}
