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

export interface CurrencyConfig {
    code: string;
    locale: string;
}

export interface BackupResult {
    tags: number;
    items: number;
    images: number;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    currency: CurrencyConfig;
    features: { imageSearch: boolean; ai: boolean; paperless: boolean };
    flash: { backup: BackupResult | null; box_created_for: string | null; invitation_mail: 'sent' | 'failed' | null };
    locale: string;
    translations: Record<string, string>;
    version: { tag: string | null; sha: string | null };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export type ItemTypeValue = 'room' | 'container' | 'item';

export type ItemViewMode = 'list' | 'grid';

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

export type CustomFieldTypeValue = 'text' | 'number' | 'date' | 'boolean' | 'url';

export interface CustomFieldDefinition {
    id: number;
    key: string;
    name: string;
    type: CustomFieldTypeValue;
    is_searchable?: boolean;
    is_system?: boolean;
}

export interface ItemCustomFieldValue {
    custom_field_id: number;
    key: string;
    name: string;
    type: CustomFieldTypeValue;
    value: string | number | boolean | null;
}

export interface ItemImageSummary {
    id: number;
    thumb_url: string;
    large_url: string;
    original_url: string;
    is_primary: boolean;
    sort_order: number;
}

export interface ImageSearchResult {
    title: string;
    thumb_url: string;
    image_url: string;
    source_url: string;
}

export interface ItemSummary {
    id: number;
    name: string;
    description: string | null;
    parent_id: number | null;
    type: ItemTypeDescriptor;
    thumb_url?: string | null;
    icon?: string | null;
    image_thumbs?: string[];
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
    // Filled custom field values (present on show/edit payloads).
    custom_fields?: ItemCustomFieldValue[];
}

export interface ActivityChange {
    field: string;
    from: string | null;
    to: string | null;
}

export interface ActivityRow {
    id: number;
    event: 'created' | 'updated' | 'deleted' | 'image_added' | 'link_added' | 'link_removed' | string;
    subject_type: string;
    subject_label: string | null;
    subject_url: string | null;
    causer: string | null;
    changes: ActivityChange[];
    count: number;
    // For link_added / link_removed: the partner item's name + url.
    related_label: string | null;
    related_url: string | null;
    at: string | null;
}
