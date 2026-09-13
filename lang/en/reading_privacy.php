<?php

return [
    'title' => 'Athkar and Quran reading progress and statistics',
    'updated' => 'This clarification was added on 13 September 2026.',
    'records' => 'When you deliberately check a Quran verse or change an Athkar repetition counter, SalaTime records the item identifier, your chosen local calendar day and its checked state or repetition count. While you are signed in, these records are sent to SalaTime’s account service and stored in its database under your account, with the technical identifiers, revisions and timestamps needed to synchronize changes and prevent duplicate updates. These records can reveal information about your religious practice.',
    'purpose' => 'This history lets you recover your progress on another signed-in device. The app calculates your reading statistics from it, including daily and weekly totals, completed Athkar, Quran verses and chapters, overall progress and consecutive active days. Opening a reading screen alone does not mark an item as read. These records are separate from your reading display preferences and are not sent to Google as part of Google sign-in.',
    'guest' => 'You can use reading progress without an account. Guest readings remain on the device and are not automatically merged into an account when you sign in. Changes made offline are kept locally and synchronized when the account connection is available again.',
    'retention' => 'Unchecking an item or resetting a counter synchronizes a zero value so your other devices can apply the same change. The server keeps these zero-value records and synchronization records while the account exists, to preserve resets and avoid replaying old updates.',
    'deletion' => 'Deleting your SalaTime account from the app’s account settings removes its synchronized reading history and synchronization records from the active database, together with the account’s other data. Signing out alone does not delete them. Local reading copies and guest history remain on the device. The general policy below describes backup retention.',
];
