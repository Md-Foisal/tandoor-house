export type SavedAddress = {
    id: number;
    label: string;
    line1: string;
    line2: string | null;
    city: string;
    postcode: string;
    instructions: string | null;
    is_default: boolean;
    zone: { name: string; fee: number } | null;
};

export type AddressesProps = {
    max: number;
    addresses?: SavedAddress[];
};
