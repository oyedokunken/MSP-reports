import { Shield, Server } from 'lucide-react';
import { MicrosoftIcon } from './MicrosoftIcon';

interface EnterpriseAuthOptionsProps {
  isOpen: boolean;
  onLdapClick: () => void;
  onOAuthClick: () => void;
  onSamlClick: () => void;
}

export function EnterpriseAuthOptions({ isOpen, onLdapClick, onOAuthClick, onSamlClick }: EnterpriseAuthOptionsProps) {
  return (
    <div
      className={`overflow-hidden transition-all duration-300 ease-in-out ${isOpen ? 'max-h-96 opacity-100 mt-6' : 'max-h-0 opacity-0'
        }`}
    >
      <div className="space-y-3">
        <button
          type="button"
          onClick={onSamlClick}
          className="w-full inline-flex items-center justify-center py-3 px-4 rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md"
        >
          <Shield className="w-5 h-5 mr-3 text-white" />
          <span>Sign in with SSO</span>
        </button>

        <button
          type="button"
          onClick={onOAuthClick}
          className="w-full inline-flex items-center justify-center py-3 px-4 rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md"
        >
          <MicrosoftIcon className="w-5 h-5 mr-3 brightness-0 invert" />
          <span>Sign in with Microsoft 365</span>
        </button>

        <button
          type="button"
          onClick={onLdapClick}
          className="w-full inline-flex items-center justify-center py-3 px-4 rounded-lg shadow-sm text-sm font-medium transition duration-150 ease-in-out relative bg-blue-600 text-white hover:bg-blue-700 hover:shadow-md"
        >
          <Server className="w-5 h-5 mr-3 text-white" />
          <span>Sign in with Active Directory</span>
        </button>
      </div>
    </div>
  );
}
