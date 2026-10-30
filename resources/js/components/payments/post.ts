export type ConfirmResponse = {
    status: 'paid' | 'failed' | 'pending';
    message: string | null;
    redirect: string | null;
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function confirmPayment(
    url: string,
    body: { reference: string; token?: string },
): Promise<ConfirmResponse> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as ConfirmResponse;
}
