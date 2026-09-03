export default function ApplicationLogo(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 190 40" fill="none" aria-label="Bhasebook" role="img" {...props}>
            <path
                d="M14.5 4.5c6.4 0 10 3.2 10 8.2 0 2.8-1.3 4.8-3.4 6 2.8 1 4.5 3.4 4.5 6.8 0 5.3-3.9 8.5-10.4 8.5H4.5a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h10Zm-5.3 11h4.9c2.2 0 3.5-1.1 3.5-2.9S16.3 9.7 14.1 9.7H9.2v5.8Zm0 5.4v6.2h5.5c2.3 0 3.7-1.2 3.7-3.1s-1.4-3.1-3.7-3.1H9.2Z"
                fill="currentColor"
            />
            <path
                d="M29 8.5a2.9 2.9 0 1 1 0 5.8 2.9 2.9 0 0 1 0-5.8Zm.9 8.4v16a1.5 1.5 0 0 1-1.5 1.5h-.9a1.5 1.5 0 0 1-1.5-1.5v-16a1.5 1.5 0 0 1 1.5-1.5h.9a1.5 1.5 0 0 1 1.5 1.5Z"
                fill="currentColor"
            />
            <path
                d="M32 9.8a2 2 0 0 1 2.8-1.9c2.9 1.2 4.8 4 4.8 7.8v7.6a2 2 0 0 1-2 2h-1.6a2 2 0 0 1-2-2v-7.4c0-1.7-.6-2.8-1.6-3.3A2 2 0 0 1 32 13.5V9.8Z"
                fill="currentColor"
            />
            <path
                d="M17.9 35.2a3.2 3.2 0 1 1-6.4 0 3.2 3.2 0 0 1 6.4 0Z"
                fill="#3387fb"
                stroke="#1e6be8"
                strokeWidth=".8"
            />
            <text
                x="46"
                y="26"
                fill="currentColor"
                fontFamily="Inter, sans-serif"
                fontSize="20"
                fontWeight="800"
                letterSpacing=".5"
            >
                hasebook
            </text>
        </svg>
    );
}
