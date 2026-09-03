import type { SVGProps } from 'react';

// Bhasebook original icon set — single-path line icons, 24×24 grid.
export type IconProps = SVGProps<SVGSVGElement>;

function Svg({ children, ...props }: IconProps & { children: React.ReactNode }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.8}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            {children}
        </svg>
    );
}

export const HomeIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M3 10.5 12 3l9 7.5" />
        <path d="M5 9.5V21h5v-6h4v6h5V9.5" />
    </Svg>
);

export const VideoIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="3" y="5" width="13" height="14" rx="3" />
        <path d="m16 10 5-3v10l-5-3" />
    </Svg>
);

export const StoreIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 8 6 4h12l2 4" />
        <path d="M4 8c0 1.4 1.2 2.5 2.6 2.5S9.3 9.4 9.3 8c0 1.4 1.2 2.5 2.7 2.5s2.7-1.1 2.7-2.5c0 1.4 1.2 2.5 2.7 2.5S20 9.4 20 8" />
        <path d="M5.5 10.7V20h13v-9.3" />
        <path d="M9.5 20v-5h5v5" />
    </Svg>
);

export const GroupIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="9" cy="8.5" r="3.2" />
        <path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5" />
        <path d="M15.5 6.2a3 3 0 0 1 0 5.6" />
        <path d="M16.8 15.4c2.2.5 3.7 2.2 3.7 4.6" />
    </Svg>
);

export const GameIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="3" y="7" width="18" height="11" rx="4" />
        <path d="M7 10.5v3M5.5 12h3" />
        <circle cx="16" cy="11" r=".6" fill="currentColor" />
        <circle cx="18" cy="13" r=".6" fill="currentColor" />
    </Svg>
);

export const MenuIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 6h16M4 12h16M4 18h16" />
    </Svg>
);

export const BellIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6Z" />
        <path d="M10 19a2 2 0 0 0 4 0" />
    </Svg>
);

export const MessengerIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 3C7 3 3 6.9 3 11.8c0 2.6 1.2 4.9 3.2 6.5V21l3.2-1.7c.8.2 1.7.3 2.6.3 5 0 9-3.9 9-8.8S17 3 12 3Z" />
        <path d="m7.5 13 3-3.5 3 2 3-3" />
    </Svg>
);

export const SearchIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="11" cy="11" r="6.5" />
        <path d="m16 16 4.5 4.5" />
    </Svg>
);

export const GlobeIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="8.5" />
        <path d="M3.5 12h17M12 3.5c2.6 2.4 4 5.3 4 8.5s-1.4 6.1-4 8.5c-2.6-2.4-4-5.3-4-8.5s1.4-6.1 4-8.5Z" />
    </Svg>
);

export const LockIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="5" y="10" width="14" height="10" rx="2.5" />
        <path d="M8 10V7.5a4 4 0 0 1 8 0V10" />
    </Svg>
);

export const UsersIcon = GroupIcon;

export const CameraIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 8h3l2-2.5h6L17 8h3v11H4z" />
        <circle cx="12" cy="13" r="3.5" />
    </Svg>
);

export const PhotoStackIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="7" y="3" width="14" height="11" rx="2" />
        <path d="M17 17.5v.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h.5" />
        <path d="m9.5 9.5 4.5 5.5 2.5-3" />
    </Svg>
);

export const PollIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M5 20V10M12 20V4M19 20v-7" />
    </Svg>
);

export const SmileyIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="8.5" />
        <path d="M8.5 14.5c.9 1.2 2 1.8 3.5 1.8s2.6-.6 3.5-1.8" />
        <circle cx="9" cy="10" r=".8" fill="currentColor" stroke="none" />
        <circle cx="15" cy="10" r=".8" fill="currentColor" stroke="none" />
    </Svg>
);

export const MapPinIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 21c-4-4.5-6-7.6-6-10.4A6 6 0 1 1 18 10.6C18 13.4 16 16.5 12 21Z" />
        <circle cx="12" cy="10.5" r="2.3" />
    </Svg>
);

export const TagUserIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="10" cy="8.5" r="3.2" />
        <path d="M4.5 19.5c0-2.8 2.4-4.7 5.5-4.7 1 0 2 .2 2.8.6" />
        <path d="m15.5 20.5 5-5m0 5-5-5" />
    </Svg>
);

export const SendIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 11.5 20 4l-7 16-2.2-6.4z" />
        <path d="m10.8 13.6 3.4-4.3" />
    </Svg>
);

export const MoreIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="5.5" cy="12" r="1.1" fill="currentColor" stroke="none" />
        <circle cx="12" cy="12" r="1.1" fill="currentColor" stroke="none" />
        <circle cx="18.5" cy="12" r="1.1" fill="currentColor" stroke="none" />
    </Svg>
);

export const HeartIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 20.5S4 15.5 4 9.7C4 7 6.1 5 8.6 5c1.5 0 2.7.7 3.4 1.9C12.7 5.7 14 5 15.4 5 17.9 5 20 7 20 9.7c0 5.8-8 10.8-8 10.8Z" />
    </Svg>
);

export const CommentIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M21 11.5c0 4.3-4 7.7-9 7.7-1 0-2-.1-2.9-.4L4 20.5l1.2-3.6C3.8 15.4 3 13.5 3 11.5 3 7.2 7 3.8 12 3.8s9 3.4 9 7.7Z" />
    </Svg>
);

export const ShareIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M13 4v4.5c-5.5.5-9 3.6-9.5 9C5.6 14.9 8.6 13.5 13 13.5V18l7.5-7z" />
    </Svg>
);

export const BookmarkIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M6 3.5h12V21l-6-4.5L6 21z" />
    </Svg>
);

export const PlusIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 5v14M5 12h14" />
    </Svg>
);

export const XIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m6 6 12 12M18 6 6 18" />
    </Svg>
);

export const CheckIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m5 12.5 4.5 4.5L19 7.5" />
    </Svg>
);

export const ChevronLeftIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m14.5 5.5-7 6.5 7 6.5" />
    </Svg>
);

export const ChevronRightIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="m9.5 5.5 7 6.5-7 6.5" />
    </Svg>
);

export const VideoCamIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="3" y="6" width="12" height="12" rx="2.5" />
        <path d="m15 11 6-3.5v9L15 13" />
    </Svg>
);

export const FlagIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M6 21V4" />
        <path d="M6 5h11l-2 3.5L17 12H6" />
    </Svg>
);

export const TrashIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4.5 7h15M9 7V4.5h6V7M6.5 7l1 13h9l1-13" />
    </Svg>
);

export const EditIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17z" />
        <path d="m14.5 7 3 3" />
    </Svg>
);

export const SettingsIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="3" />
        <path d="M19 12c0-.5 0-1-.1-1.4l2-1.5-2-3.4-2.3 1a7 7 0 0 0-2.4-1.4L13.7 2h-3.4L10 5.3a7 7 0 0 0-2.4 1.4l-2.3-1-2 3.4 2 1.5a7.6 7.6 0 0 0 0 2.8l-2 1.5 2 3.4 2.3-1a7 7 0 0 0 2.4 1.4l.3 3.3h3.4l.3-3.3a7 7 0 0 0 2.4-1.4l2.3 1 2-3.4-2-1.5c.1-.4.1-.9.1-1.4Z" />
    </Svg>
);

export const SunIcon = (p: IconProps) => (
    <Svg {...p}>
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2.5V5M12 19v2.5M2.5 12H5M19 12h2.5M5 5l1.7 1.7M17.3 17.3 19 19M19 5l-1.7 1.7M6.7 17.3 5 19" />
    </Svg>
);

export const MoonIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z" />
    </Svg>
);

export const LogoutIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M14 8V5.5a1.5 1.5 0 0 0-1.5-1.5h-7A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20h7a1.5 1.5 0 0 0 1.5-1.5V16" />
        <path d="M9.5 12H21m0 0-3-3m3 3-3 3" />
    </Svg>
);

export const SparkIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 3.5c.6 4 2.4 6.3 6 7-3.6.7-5.4 3-6 7-.6-4-2.4-6.3-6-7 3.6-.7 5.4-3 6-7Z" />
        <path d="M19 15.5c.3 1.5 1 2.3 2.4 2.6-1.4.3-2.1 1-2.4 2.4-.3-1.4-1-2.1-2.4-2.4 1.4-.3 2.1-1.1 2.4-2.6Z" />
    </Svg>
);

export const ReplayIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 5.5V11h5.5" />
        <path d="M4.6 8A8 8 0 1 1 4 13" />
    </Svg>
);

export const PlayIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M7 4.5v15l12-7.5z" fill="currentColor" />
    </Svg>
);

export const VolumeIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M4 9.5v5h3.5L12 19V5L7.5 9.5z" />
        <path d="M15.5 9a4.5 4.5 0 0 1 0 6M18 6.5a8 8 0 0 1 0 11" />
    </Svg>
);

export const PinIcon = (p: IconProps) => (
    <Svg {...p}>
        <path d="M12 21v-6" />
        <path d="M8.5 3h7l-1 6.5 2.5 2v1.5H7V11.5l2.5-2z" />
    </Svg>
);

export const GridIcon = (p: IconProps) => (
    <Svg {...p}>
        <rect x="4" y="4" width="7" height="7" rx="1.5" />
        <rect x="13" y="4" width="7" height="7" rx="1.5" />
        <rect x="4" y="13" width="7" height="7" rx="1.5" />
        <rect x="13" y="13" width="7" height="7" rx="1.5" />
    </Svg>
);
