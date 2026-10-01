<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>New Order Assignment - #{{ $order->order_number }}</title>

    <link href="https://fonts.googleapis.com/css?family=Roboto:400,600" rel="stylesheet" type="text/css">

    <style>
        html,
        body {
            margin: 0 auto !important;
            padding: 0 !important;
            height: 100% !important;
            width: 100% !important;
            font-family: 'Roboto', sans-serif !important;
            font-size: 14px;
            margin-bottom: 10px;
            line-height: 24px;
            color: #8094ae;
            font-weight: 400;
        }

        * {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
            margin: 0;
            padding: 0;
        }

        table,
        td {
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }

        table {
            border-spacing: 0 !important;
            border-collapse: collapse !important;
            table-layout: fixed !important;
            margin: 0 auto !important;
        }

        a {
            text-decoration: none;
        }

        img {
            -ms-interpolation-mode: bicubic;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #6576ff;
            color: #ffffff !important;
            font-weight: 600;
            border-radius: 5px;
            text-decoration: none;
        }
    </style>
</head>

<body style="background-color: #f5f6fa;">
    <center style="width: 100%;">
        <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f5f6fa">
            <tr>
                <td style="padding: 40px 0;">
                    <table style="width:100%;max-width:620px;margin:0 auto;">
                        <tr>
                            <td style="text-align: center; padding-bottom:25px">
                                <a href="{{ config('app.url') }}">
                                    <img style="height: 40px" src="{{ $email_logo_url ?? config('app.url') . '/images/logo.png' }}"
                                        alt="Logo">
                                </a>
                                <p style="font-size: 14px; color: #6576ff; padding-top: 12px;">
                                    Chapter One Stationary And Art Materials - New Order Assignment
                                </p>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;max-width:620px;margin:0 auto;background-color:#ffffff;border-radius:8px;">
                        <tr>
                            <td style="text-align:center;padding: 30px 30px 15px 30px;">
                                <h2 style="font-size: 18px; color: #6576ff; font-weight: 600; margin: 0;">
                                    New Order Assigned to You
                                </h2>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 0 30px 20px">
                                <p style="margin-bottom: 10px;">Hi {{ $rider->name }},</p>
                                <p style="margin-bottom: 25px;">
                                    A new order has been assigned to you for delivery. Please review the details below and proceed with the delivery.
                                </p>
                                
                                <div style="background-color: #f5f6fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                                    <p style="margin: 0 0 10px 0;"><strong>Order Number:</strong> #{{ $order->order_number }}</p>
                                    <p style="margin: 0 0 10px 0;"><strong>Customer:</strong> {{ $order->customer->name }}</p>
                                    <p style="margin: 0 0 10px 0;"><strong>Total Amount:</strong> ₦{{ number_format($order->total_amount, 2) }}</p>
                                    <p style="margin: 0;"><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                                </div>

                                @if($order->delivery_address)
                                <div style="margin-top: 20px; padding: 15px; background-color: #f5f6fa; border-radius: 5px;">
                                    <p style="margin: 0 0 5px 0;"><strong>Delivery Address:</strong></p>
                                    <p style="margin: 0;">{{ $order->delivery_address }}</p>
                                    @if($order->delivery_phone)
                                    <p style="margin: 5px 0 0 0;"><strong>Contact Phone:</strong> {{ $order->delivery_phone }}</p>
                                    @endif
                                </div>
                                @endif

                                <h3 style="font-size: 16px; color: #6576ff; margin: 20px 0 10px 0;">Order Items:</h3>
                                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                                    @foreach($order->orderItems as $item)
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 10px 0;">
                                            <strong>{{ $item->product->name ?? 'Product' }}</strong><br>
                                            @if($item->variant_options)
                                            <span style="color: #8094ae;">{{ collect($item->variant_options)->filter()->map(fn ($value, $key) => ucfirst($key) . ': ' . $value)->implode(' · ') }}</span><br>
                                            @endif
                                            <span style="color: #8094ae;">Quantity: {{ $item->quantity }}</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </table>

                                <div style="text-align: center; margin: 25px 0;">
                                    <a href="{{ config('app.url') }}/rider/orders/{{ $order->id }}" class="btn">View Order Details</a>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="text-align:center;padding: 20px 30px 40px">
                                <p style="margin: 0; font-size: 13px; line-height: 22px; color:#9ea8bb;">
                                    This is an automated email. Do not reply.
                                    Need help? Contact us at
                                    {{ $contact_email ?? 'support@chapteronestationaryandartmaterials.com' }}.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;max-width:620px;margin:0 auto;">
                        <tr>
                            <td style="text-align: center; padding: 25px 20px 0;">
                                <p style="font-size: 13px; line-height: 22px; color:#9ea8bb;">
                                    © {{ date('Y') }} Chapter One Stationery and Art Materials. All rights reserved.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>

</html>

