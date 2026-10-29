<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLine;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class OrderReceiptController extends Controller
{
    public function show(Order $order, Settings $settings): View
    {
        return view('pdf.receipt', $this->data($order, $settings) + ['printable' => true]);
    }

    public function download(Order $order, Settings $settings): Response
    {
        $data = $this->data($order, $settings) + ['printable' => false];
        $height = 520 + 34 * count($data['lines']);

        return Pdf::loadView('pdf.receipt', $data)
            ->setPaper([0, 0, 226.77, $height])
            ->download('receipt-'.$order->number.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Order $order, Settings $settings): array
    {
        $order->load(['lines', 'diningTable:id,label', 'payments']);
        ['paid' => $paid, 'refunded' => $refunded] = OrderData::paid($order);

        return [
            'order' => $order,
            'lines' => $order->lines->all(),
            'restaurant' => config('restaurant'),
            'vat' => $this->vatByRate($order),
            'paid' => $paid,
            'refunded' => $refunded,
            'footer' => $settings->get('receipt_footer'),
            'table' => $order->diningTable?->label,
        ];
    }

    /**
     * VAT inside the food and drink lines, grouped by rate, adjusted so the
     * rows add up to the order's stored VAT.
     *
     * @return list<array{rate: int, gross: int, vat: int}>
     */
    private function vatByRate(Order $order): array
    {
        $subtotal = $order->subtotal;
        $groups = [];

        foreach ($order->lines as $line) {
            /** @var OrderLine $line */
            $gross = $line->unit_price * $line->quantity * ($subtotal > 0 ? ($subtotal - $order->discount) / $subtotal : 1);
            $groups[$line->vat_rate] ??= ['rate' => $line->vat_rate, 'gross' => 0.0, 'vat' => 0.0];
            $groups[$line->vat_rate]['gross'] += $gross;
            $groups[$line->vat_rate]['vat'] += $gross * $line->vat_rate / (10000 + $line->vat_rate);
        }

        $rows = array_values(array_map(fn (array $g) => [
            'rate' => $g['rate'],
            'gross' => (int) round($g['gross']),
            'vat' => (int) round($g['vat']),
        ], $groups));

        if ($rows !== []) {
            $difference = $order->vat_total - array_sum(array_column($rows, 'vat'));
            $rows[0]['vat'] += $difference;
        }

        return $rows;
    }
}
