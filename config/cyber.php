<?php

use App\Sources\BlueskyAdapter;
use App\Sources\CisaKevAdapter;
use App\Sources\GlobalNewsAdapter;
use App\Sources\GoogleNewsAdapter;
use App\Sources\HtmlXPathAdapter;
use App\Sources\NvdAdapter;
use App\Sources\OtxAdapter;
use App\Sources\RansomwareLiveAdapter;
use App\Sources\RssAdapter;
use App\Sources\TelegramBotAdapter;
use App\Sources\UrlhausAdapter;
use App\Sources\YoutubeAdapter;

return [

    // Search terms used for every country.
    'keywords' => ['cybersecurity', 'cyber attack', 'cybercrime', 'ransomware', 'data breach', 'hacker', 'malware', 'phishing'],

    // Sources with "cyber_only": true keep only items mentioning one of these (lowercase substring match).
    // The Khmer terms are a starter list for review by Khmer-speaking analysts.
    'topic_terms' => [
        'cyber', 'hack', 'ransomware', 'malware', 'phishing', 'breach', 'data leak', 'leaked', 'ddos', 'scam', 'spyware',
        'botnet', 'deepfake', 'disinformation', 'misinformation', 'fake news', 'vulnerab', 'cve-', 'exploit', 'infosec',
        'personal data', 'data protection', 'online fraud', 'impersonat', 'fake account', 'camcert', 'encryption', 'password',
        'អ៊ីនធឺណិត', 'ហេគ', 'ស៊ីប័រ', 'មេរោគកុំព្យូទ័រ', 'ឆបោក', 'ទិន្នន័យ', 'ព័ត៌មានក្លែងក្លាយ', 'គណនីក្លែងក្លាយ', 'អនឡាញ', 'ឌីជីថល', 'បច្ចេកវិទ្យា',
    ],

    // First match wins (checked against the lowercase title); anything else is "general".
    'categories' => [
        'ransomware' => ['ransomware', 'ransom', 'lockbit', 'extortion'],
        'data_breach' => ['breach', 'leak', 'exposed data', 'stolen data', 'personal data'],
        'vulnerability' => ['vulnerab', 'cve-', 'zero-day', 'zero day', 'patch', 'exploit'],
        'ddos' => ['ddos', 'denial of service', 'denial-of-service'],
        'phishing' => ['phishing', 'smishing', 'credential theft'],
        'malware' => ['malware', 'spyware', 'trojan', 'botnet', 'infostealer', 'stealer', 'backdoor'],
        'scam' => ['scam', 'fraud', 'scam compound', 'pig butchering', 'investment scam'],
        'attack' => ['hacked', 'hacker', 'cyberattack', 'cyber attack', 'defaced', 'intrusion', 'compromised'],
        'crime' => ['cybercrime', 'arrest', 'dark web', 'police', 'charged', 'sentenced', 'extradit'],
        'policy' => [' law', 'regulation', ' bill ', 'strategy', 'camcert', 'agency', 'minister', 'government', 'sanction', 'policy'],
        'disinformation' => ['disinformation', 'misinformation', 'fake news', 'deepfake', 'fake account', 'impersonat'],
        'innovation' => ['startup', 'launch', 'funding', 'raises', 'ai ', 'quantum', 'innovation', 'partnership', 'unveil', 'new tool'],
        'general' => [],
    ],

    // ISO 3166-1 alpha-2 codes; English names come from PHP intl.
    'countries' => [
        'AF', 'AL', 'DZ', 'AD', 'AO', 'AG', 'AR', 'AM', 'AU', 'AT', 'AZ', 'BS', 'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ', 'BT',
        'BO', 'BA', 'BW', 'BR', 'BN', 'BG', 'BF', 'BI', 'CV', 'KH', 'CM', 'CA', 'CF', 'TD', 'CL', 'CN', 'CO', 'KM', 'CG', 'CD',
        'CR', 'CI', 'HR', 'CU', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG', 'SV', 'GQ', 'ER', 'EE', 'SZ', 'ET', 'FJ', 'FI',
        'FR', 'GA', 'GM', 'GE', 'DE', 'GH', 'GR', 'GD', 'GT', 'GN', 'GW', 'GY', 'HT', 'HN', 'HK', 'HU', 'IS', 'IN', 'ID', 'IR',
        'IQ', 'IE', 'IL', 'IT', 'JM', 'JP', 'JO', 'KZ', 'KE', 'KI', 'KP', 'KR', 'XK', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR',
        'LY', 'LI', 'LT', 'LU', 'MO', 'MG', 'MW', 'MY', 'MV', 'ML', 'MT', 'MH', 'MR', 'MU', 'MX', 'FM', 'MD', 'MC', 'MN', 'ME',
        'MA', 'MZ', 'MM', 'NA', 'NR', 'NP', 'NL', 'NZ', 'NI', 'NE', 'NG', 'MK', 'NO', 'OM', 'PK', 'PW', 'PS', 'PA', 'PG', 'PY',
        'PE', 'PH', 'PL', 'PT', 'PR', 'QA', 'RO', 'RU', 'RW', 'KN', 'LC', 'VC', 'WS', 'SM', 'ST', 'SA', 'SN', 'RS', 'SC', 'SL',
        'SG', 'SK', 'SI', 'SB', 'SO', 'ZA', 'SS', 'ES', 'LK', 'SD', 'SR', 'SE', 'CH', 'SY', 'TW', 'TJ', 'TZ', 'TH', 'TL', 'TG',
        'TO', 'TT', 'TN', 'TR', 'TM', 'TV', 'UG', 'UA', 'AE', 'GB', 'US', 'UY', 'UZ', 'VU', 'VA', 'VE', 'VN', 'YE', 'ZM', 'ZW',
    ],

    // Adapter class for each source type.
    'adapters' => [
        'rss' => RssAdapter::class,
        'google_news' => GoogleNewsAdapter::class,
        'global_news' => GlobalNewsAdapter::class,
        'html_xpath' => HtmlXPathAdapter::class,
        'ransomware_live' => RansomwareLiveAdapter::class,
        'cisa_kev' => CisaKevAdapter::class,
        'nvd' => NvdAdapter::class,
        'urlhaus' => UrlhausAdapter::class,
        'otx' => OtxAdapter::class,
        'bluesky' => BlueskyAdapter::class,
        'youtube' => YoutubeAdapter::class,
        'telegram_bot' => TelegramBotAdapter::class,
    ],

    // Access control.
    'require_two_factor' => (bool) env('CYBER_REQUIRE_2FA', true),

    // Months to keep items that are not linked to an incident.
    'retention_months' => (int) env('CYBER_RETENTION_MONTHS', 12),

    // AI enrichment and briefings (Claude API).
    'ai' => [
        'enabled' => (bool) env('CYBER_AI_ENABLED', false),
        'model' => env('CYBER_AI_MODEL', 'claude-opus-5'),
        'batch_size' => (int) env('CYBER_AI_BATCH_SIZE', 200),
        'briefing_time' => env('CYBER_BRIEFING_TIME', '07:00'),
        'timezone' => env('CYBER_TIMEZONE', 'Asia/Phnom_Penh'),
    ],

    // Where alerts go besides the in-app list.
    'alerts' => [
        'mail' => (bool) env('CYBER_ALERT_MAIL', true),
        'telegram_chat_id' => env('CYBER_ALERT_TELEGRAM_CHAT_ID'),
    ],

    // Seconds between GDELT calls (GDELT allows one request every 5 seconds).
    'gdelt_delay' => (int) env('CYBER_GDELT_DELAY', 6),

];
