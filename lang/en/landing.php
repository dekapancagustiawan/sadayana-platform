<?php

/**
 * Landing page copy (English).
 *
 * `:app` is replaced with the application name from General Settings. Section
 * structure and icons live in config/landing.php.
 */

return [

    'meta' => [
        'title' => ':app — Digital Platform for Neighbourhood Communities',
        'description' => ':app helps housing communities manage residents, water bills, waste fees, community dues, payments, and the community cash book transparently.',
    ],

    'tagline' => 'Silih Asih, Silih Asah, Silih Asuh',

    'nav' => [
        'features' => 'Features',
        'workflow' => 'How It Works',
        'about' => 'About',
        'roles' => 'Who It\'s For',
        'testimonials' => 'Testimonials',
        'faq' => 'FAQ',
        'contact' => 'Contact',
        'cta' => 'Contact the Board',
        'open_menu' => 'Open menu',
    ],

    'hero' => [
        'eyebrow' => 'Silih Asih, Silih Asah, Silih Asuh',
        'title' => 'Run your neighbourhood in a tidier, more transparent, more connected way',
        'subtitle' => ':app brings resident records, water meter readings, waste fees, community dues, payments, and the community cash book into one app — usable from anyone\'s phone.',
        'cta_primary' => 'Contact the Board',
        'cta_secondary' => 'See Features',
        'highlights' => [
            'No app to install',
            'Payment proofs kept in order',
            'Cash book updated automatically',
        ],
    ],

    'features' => [
        'eyebrow' => 'Features',
        'title' => 'Every community matter, in one place',
        'subtitle' => 'No more notes scattered across spreadsheets, WhatsApp groups, and piles of transfer receipts.',
        'items' => [
            'residents' => ['title' => 'Resident Administration', 'desc' => 'Blocks, houses, and residents with full occupancy history — kept intact when residents move or the board changes.'],
            'water' => ['title' => 'Water Meters & Bills', 'desc' => 'Officers record meter readings with a photo. Usage is calculated automatically and unusual readings are flagged for review.'],
            'waste' => ['title' => 'Waste Fees', 'desc' => 'A fixed monthly amount per house. Rate changes only apply going forward — issued bills never change.'],
            'contributions' => ['title' => 'Community Dues & Cash', 'desc' => 'Neighbourhood dues, community cash, and event contributions are configured by the board — recurring or one-off.'],
            'payments' => ['title' => 'Payments & Verification', 'desc' => 'Residents upload transfer proofs and the treasurer verifies them. One transfer can settle several bills at once.'],
            'transparency' => ['title' => 'Financial Transparency', 'desc' => 'Income, expenses, and cash balances are recorded with an audit trail. Every expense is approved by the neighbourhood head.'],
        ],
    ],

    'workflow' => [
        'eyebrow' => 'How It Works',
        'title' => 'From water meter to cash book, without doing the work twice',
        'subtitle' => 'Every step is recorded, so the history stays traceable whoever is on the board.',
        'steps' => [
            'record' => ['title' => 'Officers record', 'desc' => 'Water meter readings and their photos are entered on-site from a phone.'],
            'bill' => ['title' => 'Bills are issued', 'desc' => 'The system calculates usage and issues water, waste, and dues bills for every house.'],
            'pay' => ['title' => 'Residents pay', 'desc' => 'Residents see their bills, make a transfer, and upload the payment proof.'],
            'verify' => ['title' => 'Treasurer verifies', 'desc' => 'Once approved, the bills are settled and the income goes straight into the cash book.'],
        ],
    ],

    'about' => [
        'eyebrow' => 'About :app',
        'title' => 'For sadayana — for everyone',
        'body' => 'In Sundanese, "sadayana" means everyone. :app was built so that neighbourhood affairs belong to all: the board works more easily, the treasurer stays organised, and every resident can see their bills and how community money is spent.',
        'values' => [
            'asih' => ['title' => 'Silih Asih', 'desc' => 'Caring for one another — residents and the board connected in one place.'],
            'asah' => ['title' => 'Silih Asah', 'desc' => 'Keeping each other informed — bills and announcements communicated clearly.'],
            'asuh' => ['title' => 'Silih Asuh', 'desc' => 'Looking after one another — community data and finances protected and accountable.'],
        ],
        'stats' => [
            'modules' => 'Integrated modules',
            'roles' => 'User roles',
            'access' => 'Access from your phone',
        ],
    ],

    'roles' => [
        'eyebrow' => 'Who It\'s For',
        'title' => 'Everyone sees exactly what is theirs to see',
        'subtitle' => 'Access follows responsibility — residents can never see another household\'s data.',
        'items' => [
            'board' => ['title' => 'Neighbourhood Head & Board', 'desc' => 'Monitor the community dashboard, arrears, and reports; manage announcements and complaints; approve cash expenses.'],
            'treasurer' => ['title' => 'Treasurer', 'desc' => 'Set rates and dues, issue bills, verify payments, and record income and expenses.'],
            'officer' => ['title' => 'Field Officers', 'desc' => 'Record water meters and waste collection for assigned houses, straight from a phone.'],
            'resident' => ['title' => 'Residents', 'desc' => 'View bills, water usage with meter photos, pay and upload proofs, and submit complaints.'],
        ],
    ],

    'testimonials' => [
        'eyebrow' => 'Testimonials',
        'title' => 'What people say',
        'items' => [],
    ],

    'faq' => [
        'eyebrow' => 'FAQ',
        'title' => 'Frequently asked questions',
        'items' => [
            ['q' => 'Do residents need to install an app?', 'a' => 'No. :app runs in the browser on phones, tablets, and computers.'],
            ['q' => 'How do residents get an account?', 'a' => 'The board creates the account. Residents sign in with their email or phone number and must change the temporary password on first sign-in.'],
            ['q' => 'Does uploading a transfer proof settle the bill immediately?', 'a' => 'Not yet. A bill is marked paid once the treasurer verifies the payment, and the income is recorded in the cash book at the same time.'],
            ['q' => 'Can I pay several bills at once?', 'a' => 'Yes. Select several bills, make one transfer for the total, and upload the proof.'],
            ['q' => 'If the water rate or dues change, do old bills change too?', 'a' => 'No. New rates only apply to later periods; issued bills keep the amounts they were issued with.'],
            ['q' => 'Is residents\' data safe?', 'a' => 'Access is limited by role, meter photos and transfer proofs are stored privately, and every important change is recorded in an audit trail.'],
        ],
    ],

    'contact' => [
        'eyebrow' => 'Contact',
        'title' => 'Questions? Contact the board.',
        'subtitle' => 'Questions about bills, dues, or a resident account — send a message and the board will reply as soon as possible.',
        'email' => 'Email',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'address' => 'Address',
        'whatsapp_cta' => 'Chat on WhatsApp',
        'form' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone (optional)',
            'message' => 'Message',
            'submit' => 'Send Message',
            'sent' => 'Thank you! The board has received your message.',
            'failed' => 'Sorry, your message was not sent. Please try again or contact the board directly.',
        ],
    ],

    'footer' => [
        'tagline' => 'A digital platform for neighbourhood communities — Silih Asih, Silih Asah, Silih Asuh.',
        'copyright' => '© :year :app. All rights reserved.',
    ],

    'switcher' => [
        'id' => 'ID',
        'en' => 'EN',
    ],

];
