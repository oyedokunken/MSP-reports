interface AuthDividerProps {
  showAuthOptions: boolean;
  onToggle: () => void;
}

export function AuthDivider({ showAuthOptions, onToggle }: AuthDividerProps) {
  return (
    <div className="mt-6">
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-gray-300"></div>
        </div>
        <div className="relative flex justify-center">
          <button
            type="button"
            onClick={onToggle}
            className="px-4 py-1.5 bg-white border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:bg-gray-50 transition duration-150 ease-in-out flex items-center gap-2 font-athletics"
          >
            <span>Others</span>
            <svg
              className={`w-4 h-4 transition-transform duration-200 ${showAuthOptions ? 'rotate-180' : ''}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
            </svg>
          </button>
        </div>
      </div>
    </div>
  );
}
