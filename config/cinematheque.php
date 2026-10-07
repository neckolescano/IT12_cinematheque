<?php

/*
 * Public-site details for Cinematheque Centre Davao. Social links are shown in the footer's "Follow" column;
 * leave a value empty to hide that link (the column hides when all are empty). Set them in .env.
 */
return [
    'fdcp_url' => 'https://fdcp.ph',

    'social' => [
        'Facebook' => env('CCD_FACEBOOK_URL'),
        'Instagram' => env('CCD_INSTAGRAM_URL'),
        'TikTok' => env('CCD_TIKTOK_URL'),
        'YouTube' => env('CCD_YOUTUBE_URL'),
    ],
];
