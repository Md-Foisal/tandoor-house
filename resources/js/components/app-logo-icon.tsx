import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M5 29V15.5C5 9.15 9.92 4 16 4s11 5.15 11 11.5V29M3 29h26"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <path
                d="M16 27c-3.2 0-5-2.1-5-4.6 0-2.9 2.6-4.1 3.4-7.4.9 1.7 1 2.9.6 4.2 1.6-.6 2.9-2.2 3.2-4 1.8 1.8 2.8 4.3 2.8 6.6 0 3-2 5.2-5 5.2Z"
                className="fill-ember"
            />
        </svg>
    );
}
