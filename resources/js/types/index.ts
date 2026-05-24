import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type ItemTypeValue = 'room' | 'container' | 'item';

export interface ItemTypeDescriptor {
    value: ItemTypeValue;
    label: string;
    icon: string;
}

export interface TagSummary {
    id: number;
    name: string;
    slug: string;
    color: string | null;
}

export interface ItemSummary {
    id: number;
    name: string;
    description: string | null;
    parent_id: number | null;
    type: ItemTypeDescriptor;
    children_count?: number;
    tags?: TagSummary[];
}

