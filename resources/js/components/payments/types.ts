export type PaymentPanelProps = {
    clientSecret: string | null;
    driver: 'fake' | 'stripe' | string;
    publishableKey: string | null;
    /** Pence, from the server. */
    amount: number;
    reference: string;
    confirmUrl: string;
    returnUrl: string;
};
