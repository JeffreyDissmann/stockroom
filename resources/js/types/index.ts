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
    // Whether acquisition/warranty/sale detail fields apply (false for rooms).
    details?: boolean;
}

export interface TagSummary {
    id: number;
    name: string;
    slug: string;
    color: string | null;
}

export interface ItemImageSummary {
    id: number;
    thumb_url: string;
    large_url: string;
    original_url: string;
    is_primary: boolean;
    sort_order: number;
}

export interface ItemSummary {
    id: number;
    name: string;
    description: string | null;
    parent_id: number | null;
    type: ItemTypeDescriptor;
    thumb_url?: string | null;
    children_count?: number;
    tags?: TagSummary[];
    images?: ItemImageSummary[];
    // Detail fields (present on show/edit payloads via withDetails).
    quantity?: number;
    purchased_from?: string | null;
    purchase_date?: string | null;
    purchase_price?: string | null;
    manufacturer?: string | null;
    model_number?: string | null;
    serial_number?: string | null;
    lifetime_warranty?: boolean;
    warranty_expires?: string | null;
    warranty_details?: string | null;
    sold_to?: string | null;
    sold_price?: string | null;
    sold_date?: string | null;
    sold_notes?: string | null;
}

