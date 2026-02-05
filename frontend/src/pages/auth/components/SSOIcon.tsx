interface SSOIconProps {
    className?: string;
}

export function SSOIcon({ className = "w-5 h-5" }: SSOIconProps) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            {/* Cloud Shape */}
            <path
                d="M17.5 19c2.5 0 4.5-2 4.5-4.5 0-2.3-1.7-4.2-3.9-4.5-.3-2.6-2.5-4.5-5.1-4.5-2 0-3.8 1.1-4.7 2.8C7.4 8.1 6.3 8 5.3 8 3.5 8 2 9.5 2 11.3c0 .8.3 1.5.8 2.1"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />

            {/* Key Shape positioned within the cloud */}
            <g transform="translate(8, 11)">
                {/* Key Head (Circle) */}
                <circle
                    cx="2.5"
                    cy="2.5"
                    r="2"
                    stroke="currentColor"
                    strokeWidth="1.5"
                />
                {/* Key Shaft */}
                <path
                    d="M4.5 2.5H9"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                />
                {/* Key Teeth */}
                <path
                    d="M7 2.5V4M8.5 2.5V4"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                />
            </g>
        </svg>
    );
}
