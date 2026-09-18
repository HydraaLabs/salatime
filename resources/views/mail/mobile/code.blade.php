@extends('mail.layouts.account')
@section('title', $verify ? 'Vérifiez votre adresse e-mail' : 'Réinitialisez votre mot de passe')
@section('eyebrow', $verify ? 'VÉRIFICATION' : 'MOT DE PASSE')
@section('preheader', 'Votre code SalaTime est valable pendant 15 minutes.')
@section('content')
<tr><td style="padding:30px 24px;font-family:Arial,Helvetica,sans-serif;color:#213725;overflow-wrap:anywhere;">
    <h1 style="margin:0 0 18px;font-size:24px;line-height:32px;color:{{ config('brand.primary', '#2F5233') }};">Bonjour{{ $name !== '' ? ' '.$name : '' }},</h1>
    <p style="margin:0 0 24px;font-size:16px;line-height:26px;">{{ $verify ? 'Saisissez ce code dans SalaTime pour vérifier votre adresse e-mail.' : 'Saisissez ce code dans SalaTime pour réinitialiser votre mot de passe.' }}</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" style="padding:22px 12px;background-color:{{ config('brand.soft', '#EFF3EA') }};border:1px solid #DFE6D9;border-radius:12px;">
        <p style="margin:0 0 10px;font-size:13px;line-height:20px;color:#364A39;">VOTRE CODE DE CONFIRMATION</p>
        <p dir="ltr" style="margin:0;font-family:Consolas,'Courier New',monospace;font-size:32px;line-height:42px;font-weight:700;letter-spacing:4px;color:{{ config('brand.primary', '#2F5233') }};">{{ $code }}</p>
    </td></tr></table>
    <p style="margin:24px 0 12px;font-size:15px;line-height:25px;">Ce code expire dans <strong>15 minutes</strong>. Ne le communiquez à personne.</p>
    <p style="margin:0 0 24px;font-size:15px;line-height:25px;color:#364A39;">Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.</p>
    <p style="margin:0;font-size:15px;line-height:25px;">À très bientôt,<br><strong style="color:{{ config('brand.primary', '#2F5233') }};">L’équipe SalaTime</strong></p>
</td></tr>
@endsection
