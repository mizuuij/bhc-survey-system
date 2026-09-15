<?php
declare(strict_types=1);


return [

    // ---- Identity -----------------------------------------------------
    'name'        => 'Barangay Longos',
    'city'        => 'Malolos City, Bulacan',
    'eyebrow'     => 'Community Health Services in Malolos City',
    'hero_badge'  => ['Official', 'Health Survey Management Portal'],
    'hero_subtitle' => 'Resident Profiling & Community Health Surveys',
    'tagline'     => 'Keep your household information current, answer official health surveys, and help the barangay plan services around real community needs.',
    'seal_letter' => 'L', // Fallback initial if no logo image is set.
    'logo_image'  => 'assets/img/barangay-longos-logo.jpg',
    'hero_image'  => 'assets/img/barangay-longos-hero.jpg',

    // ---- How resident surveys work (steps section) -----------------------
    'how_it_works' => [
        ['step' => '1', 'title' => 'Log In', 'desc' => 'Sign in with your Resident Number and password to open your secure resident portal.'],
        ['step' => '2', 'title' => 'Review Your Profile', 'desc' => 'Check your household and personal information, then keep your resident record updated.'],
        ['step' => '3', 'title' => 'Answer a Survey', 'desc' => 'View health surveys open to your household and answer them at your own pace.'],
        ['step' => '4', 'title' => 'Submit & Track', 'desc' => 'Review your answers, submit your response, and see your completed surveys anytime.'],
    ],

    // ---- Hero side stat cards -------------------------------------------
    'hero_cards' => [
        ['label' => 'Barangay',      'title' => 'Longos',                    'sub' => 'Malolos City, Bulacan'],
        ['label' => 'Office Hours',  'title' => '8:00 AM – 5:00 PM',         'sub' => 'Monday to Friday'],
        ['label' => 'Records',       'title' => 'Resident Health Records',   'sub' => 'For health center staff and residents'],
    ],

    // ---- About section --------------------------------------------------
    'about' => [
        'mission' => 'The Barangay Longos Super Health Center exists to bring essential health services within reach of every resident — from newborns to senior citizens — through free consultations, immunization, maternal care, and community health monitoring.',
        'stats' => [
            ['value' => '2,800+', 'label' => 'Residents Served'],
            ['value' => '9',      'label' => 'Puroks Covered'],
            ['value' => '6',      'label' => 'Core Health Services'],
            ['value' => '24/7',   'label' => 'Emergency Hotline'],
        ],
    ],

    // ---- Office hours -----------------------------------------------------
    'office_hours' => [
        ['days' => 'Monday – Friday', 'hours' => '8:00 AM – 5:00 PM'],
        ['days' => 'Saturday',        'hours' => '8:00 AM – 12:00 NN (Immunization & Consultation only)'],
        ['days' => 'Sunday & Holidays', 'hours' => 'Closed (Emergency hotline remains active)'],
    ],

    'address' => 'Longos Super Health Center, Brgy. Longos, Malolos City, Bulacan',

    // ---- Services offered -----------------------------------------------
    // icon options used by the page: heart, syringe, baby, elder, tooth, clipboard
    'services' => [
        [
            'icon'  => 'clipboard',
            'title' => 'General Consultation',
            'desc'  => 'Free walk-in check-ups and referrals with our barangay health worker and visiting physician.',
        ],
        [
            'icon'  => 'syringe',
            'title' => 'Immunization',
            'desc'  => 'Routine vaccines for infants and children, plus flu and COVID-19 booster schedules for all ages.',
        ],
        [
            'icon'  => 'baby',
            'title' => 'Maternal & Prenatal Care',
            'desc'  => 'Prenatal check-ups, birthing plan guidance, postpartum visits, and newborn monitoring.',
        ],
        [
            'icon'  => 'elder',
            'title' => 'Senior Citizen Care',
            'desc'  => 'Free blood pressure and glucose monitoring, maintenance medicine assistance, and home visits.',
        ],
        [
            'icon'  => 'heart',
            'title' => 'Family Planning',
            'desc'  => 'Confidential counseling and access to family planning methods for couples and individuals.',
        ],
        [
            'icon'  => 'tooth',
            'title' => 'Nutrition & Dental Missions',
            'desc'  => 'Monthly feeding programs for undernourished children and scheduled dental mission days.',
        ],
    ],

    // ---- Barangay officials ----------------------------------------------
    'officials' => [
        ['name' => 'Hon. Barangay Captain',   'position' => 'Punong Barangay (Barangay Captain)', 'contact' => '0917 000 0001'],
        ['name' => 'Barangay Health Worker',  'position' => 'Barangay Health Worker / Midwife',    'contact' => '0917 000 0002'],
        ['name' => 'Hon. Barangay Kagawad',   'position' => 'Barangay Kagawad, Health Committee',  'contact' => '0917 000 0003'],
        ['name' => 'Barangay Secretary',      'position' => 'Barangay Secretary',                  'contact' => '0917 000 0004'],
    ],

    // ---- Emergency hotlines -------------------------------------------
    'hotlines' => [
        ['label' => 'Longos Super Health Center',   'number' => '(044) 000-0001'],
        ['label' => 'Barangay Emergency Response',  'number' => '0917 000 0000'],
        ['label' => 'Malolos City Health Office',   'number' => '(044) 000-0002'],
        ['label' => 'Philippine National Police',   'number' => '117 / (044) 000-0003'],
        ['label' => 'Bureau of Fire Protection',    'number' => '(044) 000-0004'],
        ['label' => 'National Emergency Hotline',   'number' => '911'],
    ],
];