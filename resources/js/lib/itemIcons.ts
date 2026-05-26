import {
    Archive,
    Baby,
    Bath,
    Bed,
    BookOpen,
    Boxes,
    Briefcase,
    Car,
    DoorOpen,
    Dumbbell,
    type LucideIcon,
    Monitor,
    Package,
    Shirt,
    Sofa,
    Trees,
    Utensils,
    Warehouse,
    WashingMachine,
    Wrench,
} from 'lucide-vue-next';

export interface ItemIcon {
    value: string;
    label: string;
    icon: LucideIcon;
}

// Curated icons offered for rooms and containers (which rarely have a photo).
export const itemIcons: ItemIcon[] = [
    { value: 'bed', label: 'Bedroom', icon: Bed },
    { value: 'sofa', label: 'Living room', icon: Sofa },
    { value: 'utensils', label: 'Kitchen', icon: Utensils },
    { value: 'bath', label: 'Bathroom', icon: Bath },
    { value: 'briefcase', label: 'Office', icon: Briefcase },
    { value: 'car', label: 'Garage', icon: Car },
    { value: 'trees', label: 'Garden', icon: Trees },
    { value: 'baby', label: 'Nursery', icon: Baby },
    { value: 'washing-machine', label: 'Laundry', icon: WashingMachine },
    { value: 'dumbbell', label: 'Gym', icon: Dumbbell },
    { value: 'book-open', label: 'Study', icon: BookOpen },
    { value: 'monitor', label: 'Media', icon: Monitor },
    { value: 'shirt', label: 'Wardrobe', icon: Shirt },
    { value: 'door-open', label: 'Hallway', icon: DoorOpen },
    { value: 'wrench', label: 'Workshop', icon: Wrench },
    { value: 'warehouse', label: 'Basement', icon: Warehouse },
    { value: 'boxes', label: 'Shelf', icon: Boxes },
    { value: 'package', label: 'Box', icon: Package },
    { value: 'archive', label: 'Drawer', icon: Archive },
];

export const itemIconMap: Record<string, LucideIcon> = Object.fromEntries(itemIcons.map((i) => [i.value, i.icon]));
