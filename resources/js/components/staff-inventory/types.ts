export type Unit = 'g' | 'ml' | 'pc';

export type MovementType = 'in' | 'sale' | 'waste' | 'adjust';

export type PurchaseOrderStatus = 'draft' | 'sent' | 'received' | 'cancelled';

export type Abilities = { manage: boolean };

export type IngredientRow = {
    id: number;
    name: string;
    unit: Unit;
    stock: number;
    low_threshold: number;
    par_level: number;
    cost_per_thousand: number;
    supplier: { id: number; name: string } | null;
    is_low: boolean;
    value: number;
    last_movement_at: string | null;
    last_movement_type: MovementType | null;
};

export type IngredientOption = {
    id: number;
    name: string;
    unit: Unit;
    stock: number;
    par_level: number;
    cost_per_thousand: number;
    supplier_id: number | null;
};

export type SupplierRow = {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    lead_days: number;
    ingredients: number;
    open_orders: number;
};

export type MovementRow = {
    id: number;
    type: MovementType;
    quantity: number;
    reason: string | null;
    order_number: string | null;
    user: string | null;
    at: string;
    ingredient: { id: number; name: string; unit: Unit };
    cost: number;
};

export type RecipeLineRow = {
    ingredient_id: number;
    name: string;
    unit: Unit;
    quantity: number;
    cost: number;
};

export type DishRow = {
    id: number;
    name: string;
    category: string;
    price: number;
    net_price: number;
    cost: number | null;
    margin: number | null;
    flagged: boolean;
    lines: RecipeLineRow[];
};

export type PurchaseOrderLineRow = {
    ingredient_id: number;
    name: string;
    unit: Unit;
    quantity: number;
    unit_cost: number;
    total: number;
    stock: number;
    par_level: number;
};

export type PurchaseOrderRow = {
    id: number;
    reference: string;
    supplier: string;
    status: PurchaseOrderStatus;
    lines: number;
    total: number;
    sent_at: string | null;
    received_at: string | null;
    created_at: string | null;
};

export const poStatusTone = {
    draft: 'outline',
    sent: 'turmeric',
    received: 'leaf',
    cancelled: 'destructive',
} as const;
