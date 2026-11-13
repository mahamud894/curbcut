import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface WorkspaceRef {
    id: number;
    name: string;
    role?: 'owner' | 'admin' | 'member';
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    canRegister: boolean;
    locale: string;
    locales: Record<string, string>;
    workspace: { current: WorkspaceRef; all: WorkspaceRef[] } | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
