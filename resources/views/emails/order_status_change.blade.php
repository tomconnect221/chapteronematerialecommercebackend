<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order Status Update - #{{ $order->order_number }}</title>

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

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #d1ecf1; color: #0c5460; }
        .status-preparing { background-color: #d4edda; color: #155724; }
        .status-ready_for_delivery { background-color: #cce5ff; color: #004085; }
        .status-out_for_delivery { background-color: #ffeaa7; color: #d63031; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
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
                                    Chapter One Stationary And Art Materials - Order Status Update
                                </p>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;max-width:620px;margin:0 auto;background-color:#ffffff;border-radius:8px;">
                        <tr>
                            <td style="text-align:center;padding: 30px 30px 15px 30px;">
                                <h2 style="font-size: 18px; color: #6576ff; font-weight: 600; margin: 0;">
                                    Order Status Updated
                                </h2>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding: 0 30px 20px">
                                <p style="margin-bottom: 10px;">Hi {{ $order->customer->name }},</p>
                                <p style="margin-bottom: 25px;">
                                    Your order status has been updated. Here are the details:
                                </p>
                                
                                <div style="background-color: #f5f6fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                                    <p style="margin: 0 0 10px 0;"><strong>Order Number:</strong> #{{ $order->order_number }}</p>
                                    <p style="margin: 0 0 15px 0;"><strong>Previous Status:</strong> {{ ucfirst(str_replace('_', ' ', $oldStatus)) }}</p>
                                    <p style="margin: 0 0 15px 0;"><strong>New Status:</strong></p>
                                    <span class="status-badge status-{{ $newStatus }}">
                                        {{ ucfirst(str_replace('_', ' ', $newStatus)) }}
                                    </span>
                                </div>

                                @php
                                    $statusMessages = [
                                        'pending' => 'Your order is being processed and will be confirmed shortly.',
                                        'confirmed' => 'Your order has been confirmed and is being prepared.',
                                        'preparing' => 'Your order is being prepared and will be ready soon.',
                                        'ready_for_delivery' => 'Your order is ready and will be assigned to a delivery rider shortly.',
                                        'out_for_delivery' => 'Your order is on its way! A delivery rider has been assigned and is heading to your address.',
                                        'delivered' => 'Your order has been successfully delivered. Thank you for shopping with us!',
                                        'cancelled' => 'Your order has been cancelled. If you have any questions, please contact our support team.',
                                    ];
                                    $message = $statusMessages[$newStatus] ?? 'Your order status has been updated.';
                                @endphp

                                <div style="margin: 20px 0; padding: 15px; background-color: #e8f4f8; border-left: 4px solid #6576ff; border-radius: 5px;">
                                    <p style="margin: 0; color: #0c5460;">{{ $message }}</p>
                                </div>

                                @if($order->rider && in_array($newStatus, ['out_for_delivery', 'delivered']))
                                <div style="margin-top: 20px; padding: 15px; background-color: #f5f6fa; border-radius: 5px;">
                                    <p style="margin: 0 0 5px 0;"><strong>Delivery Rider:</strong> {{ $order->rider->name }}</p>
                                    @if($order->rider->phone)
                                    <p style="margin: 5px 0 0 0;"><strong>Rider Phone:</strong> {{ $order->rider->phone }}</p>
                                    @endif
                                </div>
                                @endif

                                <div style="text-align: center; margin: 25px 0;">
                                    <a href="{{ config('app.url') }}/orders/{{ $order->id }}" class="btn">View Order Details</a>
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

