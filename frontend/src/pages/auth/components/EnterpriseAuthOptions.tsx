import { Shield, Server } from 'lucide-react';
import { MicrosoftIcon } from './MicrosoftIcon';
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
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics ${samlConfigured
            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:shadow-md'
            : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
            }`}
        >
          <Shield className={`w-5 h-5 mr-3 ${samlConfigured ? 'text-purple-600' : 'text-gray-400'}`} />
          <span>Sign in with SSO</span>
        </button>

        {/* Microsoft 365 Login (via Keycloak) */}
        <button
          type="button"
          onClick={onOAuthClick}
          disabled={!oauthConfigured}
          className={`w-full inline-flex items-center justify-center py-3 px-4 border rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out font-athletics ${oauthConfigured
            ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:shadow-md'
            : 'border-gray-300 bg-gray-50 text-gray-400 cursor-not-allowed'
            }`}
        >
          <MicrosoftIcon className={`w-5 h-5 mr-3 ${!oauthConfigured ? 'opacity-40 grayscale' : ''}`} />
          <span>Sign in with Microsoft 365</span>
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
          <Server className={`w-5 h-5 mr-3 ${ldapConfigured ? 'text-blue-600' : 'text-gray-400'}`} />
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
