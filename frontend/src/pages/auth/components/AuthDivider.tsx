interface AuthDividerProps {
  showAuthOptions: boolean;
  onToggle: () => void;
}

export function AuthDivider({ showAuthOptions, onToggle }: AuthDividerProps) {
  return (
    <div className="mt-6">
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-border/60"></div>
        </div>
        <div className="relative flex justify-center">
          <button
            type="button"
            onClick={onToggle}
            className="px-6 py-2 bg-[hsl(var(--auth-card))] border border-border/80 rounded-full text-xs font-bold text-muted-foreground hover:text-foreground hover:border-border transition duration-200 ease-in-out flex items-center gap-2 uppercase tracking-wide shadow-sm"
          >
            <span>Sign in with</span>
            <svg
              className={`w-3.5 h-3.5 transition-transform duration-300 ${showAuthOptions ? 'rotate-180' : ''}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  );
}
