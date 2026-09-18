@extends('mail.layouts.account')
@section('title', 'Réinitialisez votre mot de passe')
@section('eyebrow', 'MOT DE PASSE')
@section('content')
<tr><td style="padding:30px 24px;font-family:Arial,Helvetica,sans-serif;color:#213725;overflow-wrap:anywhere;word-break:break-word;">
<h1 style="margin:0 0 18px;font-size:24px;line-height:32px;color:{{ config('brand.primary', '#2F5233') }};">Bonjour {{ $user->full_name }},</h1>
<p style="margin:0 0 24px;font-size:16px;line-height:26px;">Vous avez demandé à réinitialiser votre mot de passe. Utilisez le bouton ci-dessous pour en choisir un nouveau.</p>
<p style="margin:20px 0;font-size:15px;line-height:25px;">Ce lien expire dans <strong>60 minutes</strong>.</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="{{ config('brand.primary', '#2F5233') }}" style="border-radius:10px;">
<a href="{{ $resetLink }}" style="display:inline-block;padding:15px 22px;font-size:16px;line-height:24px;font-weight:700;text-decoration:none;color:#FFFFFF;">Réinitialiser mon mot de passe</a>
</td></tr></table>
<p style="margin:24px 0 8px;font-size:14px;line-height:23px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
<p style="margin:0;font-size:13px;line-height:22px;word-break:break-all;"><a href="{{ $resetLink }}" style="color:{{ config('brand.primary', '#2F5233') }};">{{ $resetLink }}</a></p>
<p style="margin:24px 0;font-size:15px;line-height:25px;">Si vous n’attendiez pas cet e-mail, vous pouvez l’ignorer.</p>
<p style="margin:0;font-size:15px;line-height:25px;">À très bientôt,<br><strong style="color:{{ config('brand.primary', '#2F5233') }};">L’équipe SalaTime</strong></p>
</td></tr>
@endsection
