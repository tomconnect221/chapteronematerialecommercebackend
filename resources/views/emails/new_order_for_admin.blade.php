<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Order - #{{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6fa;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="padding:24px 32px;background:#1f2937;color:#ffffff;">
                            <h1 style="margin:0;font-size:20px;">New Order Placed</h1>
                            <p style="margin:4px 0 0;font-size:13px;opacity:.85;">Order #{{ $order->order_number }} &middot; {{ $order->created_at?->format('Y-m-d H:i') }} UTC</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;">
                            <h2 style="margin:0 0 12px;font-size:16px;">Customer</h2>
                            <p style="margin:0 0 4px;">{{ $order->customer->name ?? '—' }}</p>
                            <p style="margin:0 0 4px;">{{ $order->customer->email ?? '—' }}</p>
                            @if($order->delivery_phone)
                                <p style="margin:0;">Phone: {{ $order->delivery_phone }}</p>
                            @endif

                            <h2 style="margin:24px 0 12px;font-size:16px;">Delivery</h2>
                            <p style="margin:0 0 4px;">{{ $order->delivery_address }}</p>
                            <p style="margin:0 0 4px;">{{ $order->delivery_city }}, {{ $order->delivery_state }}</p>
                            <p style="margin:0;font-size:12px;color:#6b7280;">Lat: {{ $order->delivery_latitude }} &middot; Lng: {{ $order->delivery_longitude }}</p>

                            <h2 style="margin:24px 0 12px;font-size:16px;">Items</h2>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                                <thead>
                                    <tr style="background:#f3f4f6;">
                                        <th align="left" style="padding:8px;font-size:13px;border-bottom:1px solid #e5e7eb;">Product</th>
                                        <th align="right" style="padding:8px;font-size:13px;border-bottom:1px solid #e5e7eb;">Qty</th>
                                        <th align="right" style="padding:8px;font-size:13px;border-bottom:1px solid #e5e7eb;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->orderItems as $item)
                                        <tr>
                                            <td style="padding:8px;font-size:13px;border-bottom:1px solid #f3f4f6;">
                                                {{ $item->product->name ?? 'Product #' . $item->product_id }}
                                                @if($item->variant_options)<br><small>{{ collect($item->variant_options)->filter()->map(fn ($value, $key) => ucfirst($key) . ': ' . $value)->implode(' · ') }}</small>@endif
                                            </td>
                                            <td align="right" style="padding:8px;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ $item->quantity }}</td>
                                            <td align="right" style="padding:8px;font-size:13px;border-bottom:1px solid #f3f4f6;">{{ number_format((float) $item->total_price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                                <tr>
                                    <td align="right" style="padding:4px 0;font-size:13px;color:#6b7280;">Subtotal</td>
                                    <td align="right" width="120" style="padding:4px 0;font-size:13px;">{{ number_format((float) $order->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding:4px 0;font-size:13px;color:#6b7280;">Tax</td>
                                    <td align="right" style="padding:4px 0;font-size:13px;">{{ number_format((float) $order->tax_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding:4px 0;font-size:13px;color:#6b7280;">Delivery</td>
                                    <td align="right" style="padding:4px 0;font-size:13px;">{{ number_format((float) $order->delivery_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding:8px 0;font-size:14px;font-weight:bold;border-top:1px solid #e5e7eb;">Total</td>
                                    <td align="right" style="padding:8px 0;font-size:14px;font-weight:bold;border-top:1px solid #e5e7eb;">{{ number_format((float) $order->total_amount, 2) }}</td>
                                </tr>
                            </table>

                            @if($order->notes)
                                <h2 style="margin:24px 0 12px;font-size:16px;">Notes</h2>
                                <p style="margin:0;font-size:13px;color:#374151;">{{ $order->notes }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px;background:#f9fafb;font-size:12px;color:#6b7280;">
                            This is an automated notification. Log into the admin dashboard to manage this order.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
