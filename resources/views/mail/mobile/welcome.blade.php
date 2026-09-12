<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>Bienvenue chez SalaTime</title>
</head>
<body style="margin:0;padding:0;width:100%;background-color:{{ config('brand.canvas', '#F3F5EE') }};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;line-height:1px;color:{{ config('brand.canvas', '#F3F5EE') }};max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        Bienvenue dans SalaTime. Vos horaires de prière, vos préférences, votre quotidien.
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:{{ config('brand.canvas', '#F3F5EE') }};border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <!--[if mso]>
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"><tr><td>
                <![endif]-->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;table-layout:fixed;background-color:#FFFFFF;border:1px solid #DFE6D9;border-spacing:0;border-radius:18px;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                    <tr>
                        <td align="center" bgcolor="{{ config('brand.primary', '#2F5233') }}" style="padding:32px 24px 28px;background-color:{{ config('brand.primary', '#2F5233') }};border-radius:17px 17px 0 0;border-bottom:3px solid #D6A83B;">
                            <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:20px;font-weight:700;letter-spacing:3px;color:#E2C77F;">BIENVENUE</p>
                            <p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:38px;line-height:46px;font-weight:700;color:#FFFFFF;">SalaTime</p>
                            <p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:23px;color:{{ config('brand.soft', '#EFF3EA') }};">Un repère pour chaque prière.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 24px 12px;font-family:Arial,Helvetica,sans-serif;color:#213725;overflow-wrap:anywhere;word-wrap:break-word;">
                            <h1 style="margin:0 0 18px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:32px;font-weight:700;color:{{ config('brand.primary', '#2F5233') }};">Bonjour {{ $name }},</h1>
                            <p style="margin:0;font-size:16px;line-height:26px;">Heureux de vous accueillir&nbsp;! Avec SalaTime, gardez vos horaires de prière à portée de main et personnalisez votre expérience à votre rythme.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:{{ config('brand.soft', '#EFF3EA') }};border-radius:12px;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                                <tr>
                                    <td style="padding:20px;font-family:Arial,Helvetica,sans-serif;">
                                        <h2 style="margin:0 0 8px;font-size:18px;line-height:26px;color:{{ config('brand.primary', '#2F5233') }};">Vos préférences vous suivent</h2>
                                        <p style="margin:0;font-size:15px;line-height:25px;color:#364A39;">Votre compte sauvegarde vos préférences&nbsp;: thème, langue et réglages de prière. Connectez-vous sur un autre appareil pour les retrouver.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:16px 24px 24px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:separate;mso-table-lspace:0pt;mso-table-rspace:0pt;">
                                <tr>
                                    <td align="center" bgcolor="{{ config('brand.primary', '#2F5233') }}" style="background-color:{{ config('brand.primary', '#2F5233') }};border-radius:10px;mso-padding-alt:15px 26px;">
                                        <a href="{{ $siteUrl }}" style="display:inline-block;padding:15px 26px;border:1px solid {{ config('brand.primary', '#2F5233') }};border-radius:10px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:22px;font-weight:700;text-align:center;text-decoration:none;color:#FFFFFF;">Découvrir SalaTime</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 30px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:25px;color:#364A39;">
                            <p style="margin:0;">À très bientôt,<br><strong style="color:{{ config('brand.primary', '#2F5233') }};">L’équipe SalaTime</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 24px;border-top:1px solid #DFE6D9;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:20px;color:#667164;">
                            <p style="margin:0 0 8px;">Vous recevez cet e-mail à la suite de la création de votre compte SalaTime.</p>
                            <a href="{{ rtrim($siteUrl, '/') }}/privacy-policy" style="color:{{ config('brand.primary', '#2F5233') }};text-decoration:underline;">Politique de confidentialité</a>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
