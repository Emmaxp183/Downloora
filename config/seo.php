<?php

return [
    'title' => 'Downloora - Private Torrent Cloud Storage',
    'description' => 'Downloora saves magnet links, torrent files, and media downloads to private cloud storage so you can stream, download, and manage files from any browser.',
    'image' => '/image1.jpg',
    'robots' => 'index, follow',

    'sitemap' => [
        [
            'route' => 'home',
            'changefreq' => 'weekly',
            'priority' => '1.0',
        ],
        [
            'route' => 'seo.cloud-torrent-storage',
            'changefreq' => 'monthly',
            'priority' => '0.9',
        ],
        [
            'route' => 'seo.torrent-to-cloud',
            'changefreq' => 'monthly',
            'priority' => '0.9',
        ],
        [
            'route' => 'seo.seedr-alternative',
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ],
        [
            'route' => 'seo.private-torrent-cloud',
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ],
    ],

    'pages' => [
        'cloud-torrent-storage' => [
            'route' => 'seo.cloud-torrent-storage',
            'path' => '/cloud-torrent-storage',
            'title' => 'Cloud Torrent Storage',
            'meta_title' => 'Cloud Torrent Storage for Private Browser Downloads',
            'description' => 'Use Downloora as cloud torrent storage for magnet links, torrent files, browser streaming, folder downloads, and private user libraries.',
            'eyebrow' => 'Cloud Torrent Storage',
            'heading' => 'Cloud torrent storage for private browser downloads',
            'intro' => 'Downloora moves torrent and media downloads into private cloud storage, so users can start a transfer in the browser and come back to organized files, streams, and zip-ready folders.',
            'image' => '/image1.jpg',
            'features' => [
                [
                    'title' => 'Magnet and torrent uploads',
                    'copy' => 'Paste a magnet link or upload a .torrent file. Downloora keeps the transfer flow inside the web app and tracks progress from the dashboard.',
                ],
                [
                    'title' => 'Private cloud libraries',
                    'copy' => 'Every account has a separate library and quota, which keeps files organized by user instead of mixing everyone into one shared bucket.',
                ],
                [
                    'title' => 'Stream or download',
                    'copy' => 'Open supported files in the browser, download individual files, or package whole folders as zip archives when you need everything at once.',
                ],
            ],
            'questions' => [
                [
                    'question' => 'What is cloud torrent storage?',
                    'answer' => 'Cloud torrent storage downloads torrent content on a remote server and stores the completed files in a web-accessible private library.',
                ],
                [
                    'question' => 'Does Downloora require desktop software?',
                    'answer' => 'No. Downloora is designed around a browser dashboard for adding links, checking progress, and accessing completed files.',
                ],
            ],
        ],
        'torrent-to-cloud' => [
            'route' => 'seo.torrent-to-cloud',
            'path' => '/torrent-to-cloud',
            'title' => 'Torrent to Cloud',
            'meta_title' => 'Torrent to Cloud Downloader with Private Libraries',
            'description' => 'Send torrents to cloud storage with Downloora. Add magnet links, monitor transfers, stream completed files, and download folders from any device.',
            'eyebrow' => 'Torrent to Cloud',
            'heading' => 'Send torrents to cloud storage from your browser',
            'intro' => 'Downloora gives each user a clean torrent-to-cloud workflow: add a link, let the server fetch it, then open the completed files from a private browser library.',
            'image' => '/image2.jpg',
            'features' => [
                [
                    'title' => 'Remote transfers',
                    'copy' => 'Downloads run on the server, so users do not need to leave a local torrent client running on a laptop or phone.',
                ],
                [
                    'title' => 'Progress visibility',
                    'copy' => 'The dashboard shows active transfer state, progress, and completed files so the workflow stays understandable.',
                ],
                [
                    'title' => 'Device-friendly access',
                    'copy' => 'Use the same browser flow from desktop or mobile to start downloads and retrieve files later.',
                ],
            ],
            'questions' => [
                [
                    'question' => 'Can I add magnet links?',
                    'answer' => 'Yes. Downloora supports magnet links and torrent file uploads from the browser.',
                ],
                [
                    'question' => 'Can I download a whole folder?',
                    'answer' => 'Yes. Completed folders can be downloaded as zip archives when you want the full bundle.',
                ],
            ],
        ],
        'seedr-alternative' => [
            'route' => 'seo.seedr-alternative',
            'path' => '/seedr-alternative',
            'title' => 'Seedr Alternative',
            'meta_title' => 'Seedr Alternative for Private Torrent Cloud Storage',
            'description' => 'Downloora is a Seedr alternative for private cloud torrent downloads, user quotas, browser streaming, and folder zip downloads.',
            'eyebrow' => 'Seedr Alternative',
            'heading' => 'A private Seedr alternative built around user libraries',
            'intro' => 'Downloora focuses on the same practical need as cloud torrent tools: move downloads off the local device and into a private web library with simple access controls.',
            'image' => '/Image3.jpg',
            'features' => [
                [
                    'title' => 'Quota-aware accounts',
                    'copy' => 'Each user gets a storage quota, making the app easier to run for multiple accounts without manual file cleanup.',
                ],
                [
                    'title' => 'Browser-first library',
                    'copy' => 'Users can stream, download, preview, and delete files from the web interface after transfers complete.',
                ],
                [
                    'title' => 'Built for self-hosting',
                    'copy' => 'The Laravel, Docker, qBittorrent, and RustFS stack gives you a clear deployment path for your own cloud torrent service.',
                ],
            ],
            'questions' => [
                [
                    'question' => 'Is Downloora the same as Seedr?',
                    'answer' => 'No. Downloora is a separate project with a similar cloud torrent storage use case and its own Laravel-based implementation.',
                ],
                [
                    'question' => 'Who is it for?',
                    'answer' => 'It is for people who want a private browser-based torrent cloud library with user accounts and quotas.',
                ],
            ],
        ],
        'private-torrent-cloud' => [
            'route' => 'seo.private-torrent-cloud',
            'path' => '/private-torrent-cloud',
            'title' => 'Private Torrent Cloud',
            'meta_title' => 'Private Torrent Cloud for Secure Browser Access',
            'description' => 'Downloora gives users a private torrent cloud with account-based libraries, quota tracking, browser streaming, and direct downloads.',
            'eyebrow' => 'Private Torrent Cloud',
            'heading' => 'Private torrent cloud access for every user',
            'intro' => 'Downloora keeps the torrent workflow account-based: users add links, monitor progress, and access completed files from their own private storage area.',
            'image' => '/image1.jpg',
            'features' => [
                [
                    'title' => 'Separate user storage',
                    'copy' => 'Files are scoped to each account, so private libraries remain separate across users.',
                ],
                [
                    'title' => 'Clear quota tracking',
                    'copy' => 'The app tracks storage use so accounts can grow from small starter quotas to larger paid plans.',
                ],
                [
                    'title' => 'Web access everywhere',
                    'copy' => 'Completed files are available from the browser for streaming, direct download, or folder archive downloads.',
                ],
            ],
            'questions' => [
                [
                    'question' => 'Is Downloora private by account?',
                    'answer' => 'Yes. The app is designed around authenticated users with separate libraries and storage quotas.',
                ],
                [
                    'question' => 'Can users access files on mobile?',
                    'answer' => 'Yes. The browser interface is designed for desktop and mobile access.',
                ],
            ],
        ],
    ],
];
