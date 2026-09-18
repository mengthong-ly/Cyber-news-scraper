<?php

namespace Database\Seeders;

use App\Models\WatchlistTerm;
use Illuminate\Database\Seeder;

/**
 * Starter watchlist. Khmer terms and the product list must be reviewed by the ministry.
 */
class WatchlistSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            'cambodia' => [
                'Cambodia', 'Cambodian', 'Phnom Penh', 'Siem Reap', 'Sihanoukville', 'Battambang',
                'Royal Cambodian Armed Forces', 'Ministry of National Defence', 'National Bank of Cambodia',
                'កម្ពុជា', 'ភ្នំពេញ', 'ក្រសួងការពារជាតិ', 'កងយោធពលខេមរភូមិន្ទ',
                'សន្តិសុខអ៊ីនធឺណិត', 'ការវាយប្រហារតាមអ៊ីនធឺណិត', 'ហេគឃ័រ', 'ឧក្រិដ្ឋកម្មបច្ចេកវិទ្យា', 'ការឆបោកតាមអនឡាញ',
            ],
            'domain' => ['gov.kh', 'mod.gov.kh', 'mptc.gov.kh', 'camcert.gov.kh', 'nbc.gov.kh'],
            // Edge devices that are frequently exploited; replace with the ministry's real inventory.
            'product' => ['fortios', 'adaptive security appliance', 'exchange server', 'vmware esxi', 'ivanti connect secure', 'pan-os'],
            'actor' => ['Mustang Panda', 'APT41', 'Lazarus', 'LockBit', 'Qilin', 'Akira', 'RansomHub'],
        ];

        foreach ($terms as $kind => $list) {
            foreach ($list as $term) {
                WatchlistTerm::firstOrCreate(['kind' => $kind, 'term' => $term], ['notes' => 'starter list']);
            }
        }
    }
}
