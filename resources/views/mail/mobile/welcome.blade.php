@extends('mail.layouts.account')
@section('title', 'Bienvenue chez SalaTime')
@section('eyebrow', 'BIENVENUE')
@section('preheader', 'Bienvenue dans SalaTime. Vos horaires de prière, vos préférences, votre quotidien.')
@section('reason', 'Vous recevez cet e-mail à la suite de la création de votre compte SalaTime.')
@section('content')
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
@endsection
