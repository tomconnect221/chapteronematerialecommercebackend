<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Welcome to Chapter One Stationery and Art Materials</title>

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
                                    Chapter One Stationary And Art Materials - Welcome
                                </p>
                            </td>
                        </tr>
                    </table>

                    <table style="width:100%;max-width:620px;margin:0 auto;background-color:#ffffff;border-radius:8px;">
                        <tr>
                            <td style="text-align:center;padding: 30px 30px 15px 30px;">
                                <h2 style="font-size: 18px; color: #6576ff; font-weight: 600; margin: 0;">
                                    Welcome to Chapter One!
                                </h2>
                            </td>
                        </tr>

                        <tr>
                            <td style="text-align:center;padding: 0 30px 20px">
                                <p style="margin-bottom: 10px;">Hi {{ $customer->name }},</p>
                                <p style="margin-bottom: 25px;">
                                    Welcome to Chapter One Stationery and Art Materials! We're thrilled to have you join our community of artists, students, and creative minds.
                                </p>
                                <p style="margin-bottom: 25px;">
                                    Your account has been successfully created and you can now start exploring our wide range of stationery and art materials.
                                </p>
                                <a href="{{ config('app.url') }}/login" class="btn">Start Shopping</a>
                                <p style="margin: 15px 0;">
                                    Your account email: <strong>{{ $customer->email }}</strong>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="text-align:center;padding: 20px 30px 40px">
                                <p style="margin-bottom: 15px;">
                                    <strong>What you can do with your account:</strong>
                                </p>
                                <ul style="text-align: left; margin: 0 30px; padding: 0;">
                                    <li style="margin-bottom: 8px;">Browse our extensive catalog of stationery and art supplies</li>
                                    <li style="margin-bottom: 8px;">Place orders and track their delivery status</li>
                                    <li style="margin-bottom: 8px;">Save your favorite products for easy reordering</li>
                                    <li style="margin-bottom: 8px;">Manage your shipping addresses</li>
                                    <li style="margin-bottom: 8px;">View your order history and receipts</li>
                                </ul>
                                <p style="margin: 20px 0;">If you have any questions or need assistance, don't hesitate to reach out to our support team.</p>
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
