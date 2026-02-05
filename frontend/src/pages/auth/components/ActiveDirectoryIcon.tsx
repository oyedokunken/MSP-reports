interface ActiveDirectoryIconProps {
    className?: string;
}

export function ActiveDirectoryIcon({ className = "w-6 h-6" }: ActiveDirectoryIconProps) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            {/* Flowchart/Directory Hierarchical Structure */}

            {/* Top Root Node */}
            <circle cx="12" cy="5" r="2" fill="currentColor" />

            {/* Horizontal Branch Line */}
            <path
                d="M6 10H18"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />

            {/* Vertical connection from Root down to the branch */}
            <path
                d="M12 7V10"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />

            {/* Vertical connections down to children */}
            <path
                d="M6 10V14M18 10V14M12 10V14"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
            />

            {/* Child Nodes */}
            <circle cx="6" cy="16" r="2" fill="currentColor" />
            <circle cx="12" cy="16" r="2" fill="currentColor" />
            <circle cx="18" cy="16" r="2" fill="currentColor" />

            {/* Optional labels/dots further down to show depth */}
            <path
                d="M6 18V20M12 18V20M18 18V20"
                stroke="currentColor"
                strokeWidth="1"
                strokeLinecap="round"
                strokeDasharray="1 2"
            />
        </svg>
    );
}
