<?php

return [
    'updated' => 'Last updated :date',
    'toc' => 'On this page',

    'privacy' => [
        'meta' => [
            'title' => 'Privacy notice',
            'description' => 'What :name does with your personal data, why, how long we keep it and how to use your rights under UK GDPR.',
        ],
        'eyebrow' => 'Privacy notice',
        'title' => 'What we do with your details',
        'intro' => 'We take bookings, orders and payments, so we hold some of your personal data. This page says what, why, for how long and who else sees it. It is written to be read, not skimmed past.',
        'ico_pending' => 'pending: we will show it here once it is confirmed',
        'sections' => [
            'who' => [
                'title' => 'Who is responsible for your data',
                'paragraphs' => [
                    ':legal, trading as :name (company number :number), is the controller of your personal data. We are at :address.',
                    'Questions, requests and worries about your data go to :email. A person reads it.',
                    'Our ICO registration number: :ico.',
                ],
            ],
            'collect' => [
                'title' => 'What we collect',
                'paragraphs' => [
                    'Only what we need to cook, serve, deliver, get paid and look after you.',
                ],
                'items' => [
                    'Account: your name, email address, phone number, password (stored scrambled, never readable), and passkeys or two-factor settings if you set them up.',
                    'Orders: what you ordered, when, how you want it (collection, delivery or at the table), delivery addresses and any notes you add, including allergy notes.',
                    'Bookings: your name, contact details, party size, date and time, and any request you add, such as a birthday or a high chair.',
                    'Payments: the amount, the method, and a reference from Stripe. Your card details go straight to Stripe. They never touch our servers and we cannot see them.',
                    'Messages: the confirmations, reminders and delivery updates we send you, and a record that they were sent.',
                    'Reviews: what you write and the rating you give.',
                    'Loyalty: points earned and spent.',
                    'Marketing choice: whether you have said yes, and when.',
                    'Technical: the cookies and browser storage described on our cookie page, and the usual server logs (IP address, browser, pages requested) that keep the site secure.',
                    'Staff: for people who work here, contact details, role, rota, hours and the actions taken in the staff screens.',
                ],
            ],
            'why' => [
                'title' => 'Why we use it, and our lawful basis',
                'paragraphs' => [
                    'UK GDPR says we need a lawful basis for each use. These are ours.',
                ],
                'items' => [
                    'Taking, cooking, delivering and refunding your orders, and holding your booking. Basis: contract, because you asked us to.',
                    'Sending confirmations, reminders and delivery updates about something you ordered or booked. Basis: contract.',
                    'Keeping accounting, VAT and tax records. Basis: legal obligation.',
                    'Taking payments and spotting fraud. Basis: contract, and our legitimate interest in not being defrauded.',
                    'Keeping the site and staff screens secure, and an activity log of who changed what. Basis: legitimate interests, which we have weighed against your rights.',
                    'Asking for a review after your meal. Basis: legitimate interests. You can say no at any time.',
                    'Emails or messages that promote offers, including a reminder about an abandoned basket. Basis: your consent, which you can withdraw whenever you like.',
                    'Allergy information you give us. Basis: contract, and the explicit consent you give by telling us, so we can keep you safe.',
                    'Employing and paying staff. Basis: contract, legal obligation and legitimate interests.',
                    'Cookies and similar technologies that are not strictly necessary. Basis: your consent.',
                ],
            ],
            'recipients' => [
                'title' => 'Who else sees it',
                'paragraphs' => [
                    'We do not sell your data. We share it only with people who help us run the restaurant, and they may use it only on our instructions.',
                ],
                'items' => [
                    'Stripe, which takes card, Apple Pay and Google Pay payments and runs fraud checks.',
                    'Our email and text message providers, which deliver the messages we send you.',
                    'Our hosting and database provider, which stores the data on our behalf.',
                    'Reverb, the real-time service we run ourselves, which carries live order and booking updates to your screen and to the kitchen. It does not store your data.',
                    'Delivery drivers on our team see the name, address, phone number and notes for the orders they carry.',
                    'Our accountants and advisers, and HMRC or other authorities if the law requires it.',
                ],
            ],
            'transfers' => [
                'title' => 'Sending data outside the UK',
                'paragraphs' => [
                    'Stripe is a US company and may process payment data outside the UK. It does so under the UK International Data Transfer Addendum to its standard contractual clauses, or another safeguard the law accepts. Any other provider that handles data outside the UK must give us the same level of protection.',
                ],
            ],
            'retention' => [
                'title' => 'How long we keep it',
                'items' => [
                    'Orders, payments, refunds and invoices: six years from the end of the financial year they belong to, because tax law says so.',
                    'Your account and saved addresses: until you delete your account. Order and payment records we must keep stay for the six years above.',
                    'Bookings: twelve months after the booking date, or six years if you paid a deposit, as that is a payment record.',
                    'Reviews: until you ask us to take them down.',
                    'Staff activity log: twelve months, then it is deleted automatically.',
                    'Marketing consent and marketing messages: until you withdraw. After that we keep only a note that you said no, so that we do not ask again.',
                    'Staff records: while you work here, and six years after you leave.',
                    'Sign-in sessions: two hours of inactivity by default.',
                ],
            ],
            'rights' => [
                'title' => 'Your rights',
                'paragraphs' => [
                    'Under UK GDPR you can:',
                ],
                'items' => [
                    'Ask for a copy of the personal data we hold about you (access).',
                    'Ask us to correct anything that is wrong or incomplete (rectification).',
                    'Ask us to delete your data (erasure). We cannot delete records the law makes us keep, such as tax records, but we will tell you which and why.',
                    'Ask us to pause using your data while a dispute is settled (restriction).',
                    'Object to us using your data on the basis of our legitimate interests, and to any direct marketing, which we will always stop.',
                    'Ask for your data in a form you can move elsewhere (portability).',
                    'Withdraw consent at any time, without it affecting what we did before.',
                    'Complain to the Information Commissioner’s Office.',
                ],
            ],
            'exercise' => [
                'title' => 'How to use your rights',
                'paragraphs' => [
                    'Email :email and tell us what you would like. We may ask you to confirm who you are, and we reply within one month. It is free unless a request is clearly baseless or excessive.',
                    'If you have an account, you can do two of these yourself: download a copy of your data, and delete your account, both from your profile settings. Marketing choices are in the same place.',
                ],
            ],
            'cookies' => [
                'title' => 'Cookies',
                'paragraphs' => [
                    'We use cookies that are strictly necessary to keep you signed in, your basket safe and your payment secure. Cookies that remember your choices, such as light or dark theme, are used only if you say yes. We use no advertising or analytics cookies. The cookie page lists every one.',
                ],
            ],
            'complaints' => [
                'title' => 'If you are not happy',
                'paragraphs' => [
                    'Tell us first, at :email, and we will try to put it right. You can also complain to the Information Commissioner’s Office, the UK data protection regulator, at any time.',
                    'We may update this notice, for instance when we add a new service. The date at the top shows the latest version. If a change matters to you, we will tell you.',
                ],
            ],
        ],
        'actions' => [
            'email' => 'Email us about your data',
            'account' => 'Manage or delete your account',
            'ico' => 'Complain to the ICO',
            'cookies' => 'Read the cookie page',
        ],
    ],

    'cookies' => [
        'meta' => [
            'title' => 'Cookie policy',
            'description' => 'Every cookie and piece of browser storage :name uses, what it is for and how long it lasts.',
        ],
        'eyebrow' => 'Cookie policy',
        'title' => 'What our cookies do',
        'intro' => 'We only use cookies to keep the site working and, if you say yes, to remember your choices. We do not use advertising or analytics cookies, and nothing here follows you to other sites.',
        'settings_title' => 'Your choice',
        'settings_body' => 'Necessary cookies are always on. Preferences are off unless you turn them on. You can change your mind whenever you like.',
        'status_preferences_on' => 'Preferences are on.',
        'status_preferences_off' => 'Preferences are off.',
        'change' => 'Change cookie settings',
        'table_caption' => 'Cookies and browser storage used by this site',
        'columns' => [
            'name' => 'Name',
            'purpose' => 'What it does',
            'duration' => 'Lasts',
            'type' => 'Type',
        ],
        'categories' => [
            'necessary' => 'Strictly necessary',
            'preferences' => 'Preferences',
        ],
        'category_intro' => [
            'necessary' => 'These keep you signed in, protect forms from forgery, remember your cookie choice and secure payments. The site cannot work without them, so they are set without asking.',
            'preferences' => 'These remember things like your theme, whether the staff side menu is open and which kitchen station a tablet shows. They are set only if you accept preferences.',
        ],
        'storage' => [
            'cookie' => 'Cookie',
            'local' => 'Local storage',
            'session' => 'Session storage',
        ],
        'durations' => [
            'minutes' => ':count minute|:count minutes',
            'hours' => ':count hour|:count hours',
            'days' => ':count day|:count days',
            'years' => ':count year|:count years',
            'browser_session' => 'Until you close the tab',
            'until_cleared' => 'Until you clear it',
        ],
        'items' => [
            'session' => ['purpose' => 'Keeps you signed in and your basket together as you move around the site.'],
            'xsrf' => ['purpose' => 'Protects forms and payments from being sent by another site pretending to be you.'],
            'remember' => ['purpose' => 'Keeps you signed in on this device. Only set if you tick “Remember me”.'],
            'consent' => ['purpose' => 'Remembers the cookie choice you made on this site.'],
            'menu_search' => ['purpose' => 'Holds your menu search for this tab, so Back to the menu returns to what you were looking at.'],
            'stripe_mid' => ['purpose' => 'Set by Stripe while you pay. Helps it spot fraud and keep card payments secure.'],
            'stripe_sid' => ['purpose' => 'Set by Stripe while you pay. Ties one payment visit together for fraud checks.'],
            'appearance' => ['purpose' => 'Remembers light, dark or match-my-device, so the page opens the way you like it.'],
            'appearance_local' => ['purpose' => 'The same theme choice, kept in your browser so the page switches before it finishes loading.'],
            'sidebar' => ['purpose' => 'Remembers whether you left the side menu open or closed in the staff screens.'],
            'staff_device' => ['purpose' => 'Staff devices only: the kitchen screen theme, the station a tablet shows and whether the new-order chime is on.'],
        ],
    ],

    'consent' => [
        'title' => 'A quick word about cookies',
        'body' => 'We only use cookies to keep you signed in and your basket safe. With your say-so we also remember your theme and screen choices.',
        'more' => 'Cookie page',
        'accept' => 'Accept all',
        'reject' => 'Reject non-essential',
        'choose' => 'Choose',
        'choose_title' => 'Choose your cookies',
        'necessary_title' => 'Strictly necessary',
        'necessary_body' => 'Sign-in, basket, payment security and this choice. Always on.',
        'necessary_state' => 'Always on',
        'preferences_title' => 'Preferences',
        'preferences_body' => 'Remembers light or dark theme and screen layout choices between visits.',
        'save' => 'Save my choice',
        'back' => 'Back',
        'saved' => 'Cookie settings saved.',
    ],
];
