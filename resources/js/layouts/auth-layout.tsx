import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import type { AuthLayoutProps } from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout(props: AuthLayoutProps) {
    return <AuthLayoutTemplate {...props} />;
}
