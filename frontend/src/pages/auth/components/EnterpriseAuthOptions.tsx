import { MdSecurity, MdStorage, MdVpnKey } from 'react-icons/md';
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
      className={`overflow-hidden transition-all duration-300 ease-in-out ${isOpen ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
        }`}
    >
      <div className="space-y-3">
        {/* SAML/SSO Login */}
        <button
          type="button"
          onClick={onSamlClick}
          disabled={!samlConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${samlConfigured
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

        {/* Keycloak SSO Login */}
        <button
          type="button"
          onClick={onOAuthClick}
          disabled={!oauthConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${oauthConfigured
            ? 'border-cyan-300 bg-cyan-50 text-cyan-700 hover:bg-cyan-100'
            : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
            }`}
        >
          <MdVpnKey className={`w-5 h-5 mr-3 ${oauthConfigured ? 'text-cyan-600' : 'text-gray-400'}`} />
          <span>Sign in with Keycloak</span>
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
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics relative ${ldapConfigured
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
