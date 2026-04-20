<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedBlogCategories();
        $this->seedBlogPosts();
        $this->seedFaqs();
        $this->seedSiteSettings();

        $this->command->info('✅ CMS collections seeded successfully.');
    }

    // ── Pages ──────────────────────────────────────────────────────────
    private function seedPages(): void
    {
        Page::truncate();

        $pages = [
            [
                'title'            => 'Home',
                'slug'             => 'home',
                'content'          => '<h1>Welcome to AeroTrek Courier</h1><p>Fast, reliable international shipping across the globe. Book shipments with DHL, FedEx, Aramex, UPS and more — all in one place.</p>',
                'meta_title'       => 'AeroTrek Courier - International Shipping Made Easy',
                'meta_description' => 'Book international courier shipments with DHL, FedEx, Aramex, UPS. Get real-time rates and track your packages.',
                'is_published'     => true,
            ],
            [
                'title'            => 'About Us',
                'slug'             => 'about-us',
                'content'          => '<h1>About AeroTrek</h1><p>AeroTrek Courier is a white-label international courier booking platform that connects you with the world\'s leading carriers.</p>',
                'meta_title'       => 'About AeroTrek Courier',
                'meta_description' => 'Learn about AeroTrek Courier and our mission to simplify international shipping.',
                'is_published'     => true,
            ],
            [
                'title'            => 'Terms & Conditions',
                'slug'             => 'terms-and-conditions',
                'content'          => '<h1>Terms & Conditions</h1><p>By using AeroTrek Courier, you agree to the following terms and conditions...</p>',
                'meta_title'       => 'Terms & Conditions - AeroTrek Courier',
                'meta_description' => 'Read the terms and conditions for using AeroTrek Courier services.',
                'is_published'     => true,
            ],
            [
                'title'            => 'Privacy Policy',
                'slug'             => 'privacy-policy',
                'content'          => '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy explains how we collect, use and protect your data...</p>',
                'meta_title'       => 'Privacy Policy - AeroTrek Courier',
                'meta_description' => 'Read our privacy policy to understand how AeroTrek handles your personal data.',
                'is_published'     => true,
            ],
            [
                'title'            => 'Contact Us',
                'slug'             => 'contact',
                'content'          => '<h1>Contact Us</h1><p>Have questions? Get in touch with our support team. Email: support@aerotrekcourier.com</p>',
                'meta_title'       => 'Contact AeroTrek Courier',
                'meta_description' => 'Contact AeroTrek Courier support team for help with your shipments.',
                'is_published'     => true,
            ],
        ];

        foreach ($pages as $page) {
            Page::create($page);
        }

        $this->command->info('  → Pages seeded (' . count($pages) . ')');
    }

    // ── Blog Categories ────────────────────────────────────────────────
    private function seedBlogCategories(): void
    {
        BlogCategory::truncate();

        $categories = [
            ['name' => 'Shipping Tips',    'slug' => 'shipping-tips'],
            ['name' => 'Carrier Guides',   'slug' => 'carrier-guides'],
            ['name' => 'Customs & Duties', 'slug' => 'customs-duties'],
            ['name' => 'Company News',     'slug' => 'company-news'],
        ];

        foreach ($categories as $category) {
            BlogCategory::create($category);
        }

        $this->command->info('  → Blog categories seeded (' . count($categories) . ')');
    }

    // ── Blog Posts ─────────────────────────────────────────────────────
    private function seedBlogPosts(): void
    {
        BlogPost::truncate();

        $category = BlogCategory::where('slug', 'shipping-tips')->first();

        $posts = [
            [
                'title'          => 'How to Ship Internationally: A Complete Guide',
                'slug'           => 'how-to-ship-internationally',
                'excerpt'        => 'Everything you need to know about international shipping — from packaging to customs.',
                'content'        => '<h2>Getting Started</h2><p>International shipping can seem complex, but with the right knowledge it\'s straightforward. Here\'s everything you need to know...</p>',
                'featured_image' => null,
                'category_id'    => $category?->_id,
                'author_id'      => null,
                'meta_title'     => 'How to Ship Internationally - AeroTrek Guide',
                'meta_description' => 'Complete guide to international shipping. Learn about packaging, customs, carriers and more.',
                'is_published'   => true,
                'published_at'   => now(),
            ],
            [
                'title'          => 'DHL vs FedEx vs Aramex: Which Carrier is Best?',
                'slug'           => 'dhl-vs-fedex-vs-aramex',
                'excerpt'        => 'A detailed comparison of the top international carriers to help you choose the right one.',
                'content'        => '<h2>Overview</h2><p>Choosing the right carrier depends on your destination, weight, and budget. Let\'s compare the top three...</p>',
                'featured_image' => null,
                'category_id'    => $category?->_id,
                'author_id'      => null,
                'meta_title'     => 'DHL vs FedEx vs Aramex Comparison',
                'meta_description' => 'Compare DHL, FedEx and Aramex for international shipping rates, speed and reliability.',
                'is_published'   => true,
                'published_at'   => now(),
            ],
            [
                'title'          => 'EU Import Changes for 2026: What Small Shippers Need to Know',
                'slug'           => 'eu-import-changes-2026',
                'excerpt'        => 'New EU customs regulations are coming in 2026. Here is what every small shipper needs to prepare for.',
                'content'        => '<h2>What is Changing</h2><p>The European Union has announced significant changes to import regulations effective 2026...</p>',
                'featured_image' => null,
                'category_id'    => $category?->_id,
                'author_id'      => null,
                'meta_title'     => 'EU Import Changes 2026 - AeroTrek',
                'meta_description' => 'Learn about EU import regulation changes for 2026 and how they affect your international shipments.',
                'is_published'   => true,
                'published_at'   => now()->subDays(6),
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::create($post);
        }

        $this->command->info('  → Blog posts seeded (' . count($posts) . ')');
    }

    // ── FAQs ───────────────────────────────────────────────────────────
    private function seedFaqs(): void
    {
        Faq::truncate();

        $faqs = [
            // Shipping
            ['question' => 'Which carriers does AeroTrek support?',      'answer' => 'AeroTrek supports DHL, FedEx, Aramex, UPS, and our own network.',                                                                                     'category' => 'shipping', 'order' => 1, 'is_published' => true],
            ['question' => 'How long does international shipping take?', 'answer' => 'Typically 3-7 business days for express and 7-14 days for standard, depending on carrier and destination.',                                          'category' => 'shipping', 'order' => 2, 'is_published' => true],
            ['question' => 'What items are prohibited from shipping?',   'answer' => 'Prohibited items include dangerous goods, flammable materials, weapons, and counterfeit products. Full list available in our terms.',               'category' => 'shipping', 'order' => 3, 'is_published' => true],
            ['question' => 'How are shipping rates calculated?',         'answer' => 'Rates are calculated based on destination country, actual weight, volumetric weight, and selected carrier. We always show you the best price.',      'category' => 'shipping', 'order' => 4, 'is_published' => true],
            // Tracking
            ['question' => 'How do I track my shipment?',                'answer' => 'Log in to your account and go to My Shipments. Click on any shipment to see real-time tracking updates. You can also track without login using the AWB number.', 'category' => 'tracking', 'order' => 1, 'is_published' => true],
            ['question' => 'How often is tracking updated?',             'answer' => 'Tracking information is updated in real-time as your package moves through the carrier network.',                                                    'category' => 'tracking', 'order' => 2, 'is_published' => true],
            // Payment
            ['question' => 'How does the wallet system work?',           'answer' => 'Recharge your AeroTrek wallet using PayU. Your wallet balance is then used to pay for shipments at checkout. No hidden charges.',                   'category' => 'payment',  'order' => 1, 'is_published' => true],
            ['question' => 'What payment methods are accepted?',         'answer' => 'We accept credit cards, debit cards, UPI, net banking, and wallets via PayU payment gateway.',                                                      'category' => 'payment',  'order' => 2, 'is_published' => true],
            ['question' => 'Can I get a refund to my wallet?',           'answer' => 'Yes, if a shipment is cancelled before pickup, the amount is refunded to your AeroTrek wallet within 24 hours.',                                    'category' => 'payment',  'order' => 3, 'is_published' => true],
            // General
            ['question' => 'Is KYC verification mandatory?',             'answer' => 'Yes, KYC verification is required before booking your first shipment. It is a one-time process and ensures compliance with international regulations.', 'category' => 'general', 'order' => 1, 'is_published' => true],
            ['question' => 'Can I use AeroTrek for my business?',        'answer' => 'Absolutely. AeroTrek supports both individual and company accounts. Company accounts require GST or Company PAN for KYC.',                          'category' => 'general',  'order' => 2, 'is_published' => true],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }

        $this->command->info('  → FAQs seeded (' . count($faqs) . ')');
    }

    // ── Site Settings ──────────────────────────────────────────────────
    private function seedSiteSettings(): void
    {
        SiteSetting::truncate();

        $settings = [

            // ── General ─────────────────────────────────────────────
            ['key' => 'site_name',        'value' => 'AeroTrek Courier',                                           'type' => 'text'],
            ['key' => 'site_email',       'value' => 'support@aerotrekcourier.com',                                'type' => 'text'],
            ['key' => 'site_phone',       'value' => '+91 99999 99999',                                            'type' => 'text'],
            ['key' => 'site_address',     'value' => 'Mumbai, Maharashtra, India',                                 'type' => 'text'],
            ['key' => 'site_logo',        'value' => null,                                                         'type' => 'image'],
            ['key' => 'site_favicon',     'value' => null,                                                         'type' => 'image'],
            ['key' => 'maintenance_mode', 'value' => 'false',                                                      'type' => 'boolean'],
            ['key' => 'meta_title',       'value' => 'AeroTrek Courier - Ship Globally',                           'type' => 'text'],
            ['key' => 'meta_description', 'value' => 'Fast and reliable international courier booking platform.',  'type' => 'text'],

            // ── Social ───────────────────────────────────────────────
            ['key' => 'social_facebook',  'value' => 'https://facebook.com/aerotrek',                             'type' => 'text'],
            ['key' => 'social_instagram', 'value' => 'https://instagram.com/aerotrek',                            'type' => 'text'],
            ['key' => 'social_twitter',   'value' => 'https://twitter.com/aerotrek',                              'type' => 'text'],
            ['key' => 'social_linkedin',  'value' => 'https://linkedin.com/company/aerotrek',                     'type' => 'text'],

            // ── Landing Hero ─────────────────────────────────────────
            [
                'key'  => 'landing_hero',
                'type' => 'json',
                'value' => [
                    'headline'      => "Delivering your cargo\n⊕ Worldwide",
                    'subtext'       => 'Book once, ship anywhere. Compare live rates from DHL, FedEx, UPS and Aramex — print your label in 30 seconds.',
                    'cta_primary'   => 'Get Started',
                    'cta_secondary' => 'Track Package',
                ],
            ],

            // ── Landing Stats ────────────────────────────────────────
            [
                'key'  => 'landing_stats',
                'type' => 'json',
                'value' => [
                    ['value' => '200+',  'label' => 'Countries served'],
                    ['value' => '12M+',  'label' => 'Parcels delivered'],
                    ['value' => '99.4%', 'label' => 'On-time rate'],
                    ['value' => '4.8/5', 'label' => 'Avg. review score'],
                ],
            ],

            // ── Carriers ─────────────────────────────────────────────
            [
                'key'  => 'landing_carriers',
                'type' => 'json',
                'value' => ['DHL', 'FedEx', 'UPS', 'Aramex', 'BlueDart', 'DTDC'],
            ],

            // ── Features ─────────────────────────────────────────────
            [
                'key'  => 'landing_features',
                'type' => 'json',
                'value' => [
                    ['icon' => 'tag',     'title' => 'Competitive Rates',  'desc' => 'Live rates from 5+ carriers. Always the best price for your shipment.'],
                    ['icon' => 'map-pin', 'title' => 'Real-time Tracking', 'desc' => 'Track every parcel live — pickup, customs, out for delivery.'],
                    ['icon' => 'shield',  'title' => 'KYC-secured',        'desc' => 'One-time document verification for secure, compliant international shipping.'],
                    ['icon' => 'layers',  'title' => 'Multiple Carriers',  'desc' => 'DHL, FedEx, UPS, Aramex, and our own network in one platform.'],
                    ['icon' => 'wallet',  'title' => 'INR Wallet',         'desc' => 'Recharge in rupees. No foreign exchange surprises on your bill.'],
                    ['icon' => 'zap',     'title' => 'Fast Booking',       'desc' => 'Book a shipment and print your label in under 2 minutes.'],
                ],
            ],

            // ── Destinations ─────────────────────────────────────────
            [
                'key'  => 'landing_destinations',
                'type' => 'json',
                'value' => [
                    ['country' => 'United States', 'flag' => '🇺🇸', 'transit' => '5–7 days'],
                    ['country' => 'UK',            'flag' => '🇬🇧', 'transit' => '4–6 days'],
                    ['country' => 'Canada',        'flag' => '🇨🇦', 'transit' => '6–9 days'],
                    ['country' => 'UAE',           'flag' => '🇦🇪', 'transit' => '2–4 days'],
                    ['country' => 'Australia',     'flag' => '🇦🇺', 'transit' => '5–8 days'],
                    ['country' => 'Germany',       'flag' => '🇩🇪', 'transit' => '5–7 days'],
                    ['country' => 'Singapore',     'flag' => '🇸🇬', 'transit' => '3–5 days'],
                    ['country' => 'New Zealand',   'flag' => '🇳🇿', 'transit' => '7–10 days'],
                ],
            ],

            // ── Testimonials ─────────────────────────────────────────
            [
                'key'  => 'landing_testimonials',
                'type' => 'json',
                'value' => [
                    [
                        'name'   => 'Priya Sharma',
                        'role'   => 'Individual Sender',
                        'rating' => 5,
                        'text'   => 'Sent a parcel to my sister in London. The whole process took less than 5 minutes and tracking was perfect throughout.',
                    ],
                    [
                        'name'   => 'Rajan Mehta',
                        'role'   => 'Small Business Owner',
                        'rating' => 5,
                        'text'   => 'We ship handmade products to the US and Canada every week. AeroTrek gives us the best rates and the KYC was a one-time thing.',
                    ],
                    [
                        'name'   => 'Zara Exports Pvt',
                        'role'   => 'E-commerce Seller',
                        'rating' => 4,
                        'text'   => 'Moved from a big aggregator to AeroTrek. Rates are 15-20% cheaper and the wallet system makes billing so much easier.',
                    ],
                ],
            ],

            // ── How It Works ─────────────────────────────────────────
            [
                'key'  => 'landing_how_it_works',
                'type' => 'json',
                'value' => [
                    ['step' => 1, 'icon' => 'user-plus',    'title' => 'Create Account',  'desc' => 'Sign up in seconds. Individual or company — both welcome.'],
                    ['step' => 2, 'icon' => 'shield-check', 'title' => 'Verify KYC',      'desc' => 'One-time document verification. Aadhaar, PAN, or GST.'],
                    ['step' => 3, 'icon' => 'wallet',       'title' => 'Recharge Wallet', 'desc' => 'Add INR balance via UPI, net banking, or card through PayU.'],
                    ['step' => 4, 'icon' => 'send',         'title' => 'Book Shipment',   'desc' => 'Compare rates, fill details, print label. Done.'],
                ],
            ],

            // ── CTA Banner ───────────────────────────────────────────
            [
                'key'  => 'landing_cta_banner',
                'type' => 'json',
                'value' => [
                    'headline'      => 'Ready to ship worldwide?',
                    'subtext'       => 'Create a free account and book your first pickup in under 2 minutes.',
                    'cta_primary'   => 'Create Account',
                    'cta_secondary' => 'Book a Pickup',
                ],
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::create($setting);
        }

        $this->command->info('  → Site settings seeded (' . count($settings) . ')');
    }
}