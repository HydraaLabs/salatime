<section id="account-and-cloud-data" aria-labelledby="account-data-heading">
    <h2 id="account-data-heading">SalaTime app accounts and cloud preferences</h2>
    <p>The following details describe SalaTime's optional account features. You can use the app without creating an account.</p>

    <h3>Account information and Google sign-in</h3>
    <p>When you register by email, SalaTime stores your name, email address, email verification status and an account identifier. Your password is stored as a hash. Verification and password recovery use codes sent to your email address when these features are available.</p>
    <p>If you choose Sign in with Google, SalaTime receives your name, verified email address and Google account identifier in order to create your SalaTime account or link Google to an existing account. An identity token is sent to SalaTime's server to verify the sign-in. SalaTime does not receive your Google password and does not request access to Gmail, contacts or Google Drive for this sign-in. Google's handling of the sign-in is described in its <a href="https://policies.google.com/privacy" rel="noopener noreferrer">Privacy Policy</a>.</p>
    <h3>Sign in with Apple</h3>
    <p>If you choose Sign in with Apple, SalaTime receives your Apple sign-in identifier, your verified email address or Apple's private relay address, and the name you share on your first authorization. SalaTime uses this information to create or link your account. The app sends Apple's identity token, authorization code and a one-time challenge to SalaTime's server to verify the sign-in. SalaTime does not receive your Apple password or access to your iCloud content.</p>
    <p>Apple does not normally provide your name again on subsequent sign-ins, so SalaTime keeps the name already saved to your account. The server stores an encrypted Apple refresh token so it can revoke the authorization when you delete your SalaTime account. Deleting SalaTime's account removes its linked Apple identifier and account data; it does not delete your Apple Account. Apple's processing and private email relay are described in <a href="https://www.apple.com/legal/privacy/data/en/sign-in-with-apple/" rel="noopener noreferrer">Sign in with Apple &amp; Privacy</a>.</p>
    <p>SalaTime also keeps sign-in sessions so you can remain signed in. The app stores its session token in the device's secure storage; session records on the server include a hashed token, a device label and expiration information.</p>

    <h3>Preferences saved to your account</h3>
    <p>While you are signed in, the app synchronizes supported settings with your SalaTime account so they can be restored on another device. These include language and appearance, prayer calculation and time adjustments, prayer notifications and reminder choices, widget settings, automatic silence settings, Hijri date adjustment and reading preferences. These preferences are sent to SalaTime's account service separately from Google sign-in.</p>
    <p>This synchronization does not upload personal audio files, local file paths, precise GPS positions, device permission grants or passwords. Personal notification sounds remain on the device and must be imported again on another device. Settings remain usable locally while offline.</p>

    @include('support.partials.reading-privacy')

    @include('support.partials.account-deletion')

    <h3>Deleting your account</h3>
    <p>To delete your SalaTime account, open the app's settings, open your account and choose <strong>Delete account</strong>. You may be asked to confirm your password or sign in again. Deletion removes the account, linked sign-in identifiers, saved cloud preferences, synchronized reading history and its synchronization records, verification records and sign-in sessions from the active account database. Backup retention is described in the general policy below.</p>
    <p>Deleting a SalaTime account does not delete your Google account. App settings and personal sound files kept only on your device remain local. Signing out alone does not delete your SalaTime account or its cloud preferences and synchronized reading history.</p>
</section>
