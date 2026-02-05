import { ActiveDirectoryIcon } from './ActiveDirectoryIcon';
import { MicrosoftIcon } from './MicrosoftIcon';
import { SSOIcon } from './SSOIcon';

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
      <div className="space-y-4">
        <button
          type="button"
          onClick={onSamlClick}
          className="w-full inline-flex items-center justify-center py-3.5 px-4 rounded-xl shadow-lg shadow-primary/10 text-xs font-bold transition duration-200 ease-in-out bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-xl hover:shadow-primary/20 uppercase tracking-wider"
        >
          <SSOIcon className="w-6 h-6 mr-3" />
          <span>SSO</span>
        </button>

        <button
          type="button"
          onClick={onOAuthClick}
          className="w-full inline-flex items-center justify-center py-3.5 px-4 rounded-xl shadow-lg shadow-primary/10 text-xs font-bold transition duration-200 ease-in-out bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-xl hover:shadow-primary/20 uppercase tracking-wider"
        >
          <MicrosoftIcon className="w-6 h-6 mr-3" />
          <span>Microsoft 365</span>
        </button>

        <button
          type="button"
          onClick={onLdapClick}
          className="w-full inline-flex items-center justify-center py-3.5 px-4 rounded-xl shadow-lg shadow-primary/10 text-xs font-bold transition duration-200 ease-in-out relative bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-xl hover:shadow-primary/20 uppercase tracking-wider"
        >
          <ActiveDirectoryIcon className="w-6 h-6 mr-3" />
          <span>Active Directory</span>
        </button>
      </div>
    </div>
  );
}
