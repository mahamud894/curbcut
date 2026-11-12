import { SVGAttributes } from 'react';

/** Curbcut mark: a kerb with a ramp cut into it. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M3 25h7.5L20 13h9v-4H18.2L8.7 21H3z" />
            <path d="M3 28h26v-2H3z" opacity=".55" />
        </svg>
    );
}
