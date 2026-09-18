SalaTime — {{ $verify ? 'Vérifiez votre adresse e-mail' : 'Réinitialisez votre mot de passe' }}

Bonjour{!! $name !== '' ? ' '.$name : '' !!},

{{ $verify ? 'Saisissez ce code dans SalaTime pour vérifier votre adresse e-mail.' : 'Saisissez ce code dans SalaTime pour réinitialiser votre mot de passe.' }}

Votre code de confirmation : {!! $code !!}

Ce code expire dans 15 minutes. Ne le communiquez à personne.
Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.

À très bientôt,
L’équipe SalaTime

Politique de confidentialité : {{ $siteUrl }}/privacy-policy
