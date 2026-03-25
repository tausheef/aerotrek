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
            ['name' => 'Shipping Tips',      'slug' => 'shipping-tips'],
            ['name' => 'Carrier Guides',     'slug' => 'carrier-guides'],
            ['name' => 'Customs & Duties',   'slug' => 'customs-duties'],
            ['name' => 'Company News',       'slug' => 'company-news'],
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
                'title'            => 'How to Ship Internationally: A Complete Guide',
                'slug'             => 'how-to-ship-internationally',
                'excerpt'          => 'Everything you need to know about international shipping — from packaging to customs.',
                'content'          => '<h2>Getting Started</h2><p>International shipping can seem complex, but with the right knowledge it\'s straightforward. Here\'s everything you need to know...</p>',
                'featured_image'   => null,
                'category_id'      => $category?->_id,
                'author_id'        => null,
                'meta_title'       => 'How to Ship Internationally - AeroTrek Guide',
                'meta_description' => 'Complete guide to international shipping. Learn about packaging, customs, carriers and more.',
                'is_published'     => true,
                'published_at'     => now(),
            ],
            [
                'title'            => 'DHL vs FedEx vs Aramex: Which Carrier is Best?',
                'slug'             => 'dhl-vs-fedex-vs-aramex',
                'excerpt'          => 'A detailed comparison of the top international carriers to help you choose the right one.',
                'content'          => '<h2>Overview</h2><p>Choosing the right carrier depends on your destination, weight, and budget. Let\'s compare the top three...</p>',
                'featured_image'   => null,
                'category_id'      => $category?->_id,
                'author_id'        => null,
                'meta_title'       => 'DHL vs FedEx vs Aramex Comparison',
                'meta_description' => 'Compare DHL, FedEx and Aramex for international shipping rates, speed and reliability.',
                'is_published'     => true,
                'published_at'     => now(),
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
            [
                'question'     => 'Which carriers does AeroTrek support?',
                'answer'       => 'AeroTrek supports DHL, FedEx, Aramex, UPS, and our own SELF/UK service.',
                'category'     => 'shipping',
                'order'        => 1,
                'is_published' => true,
            ],
            [
                'question'     => 'How long does international shipping take?',
                'answer'       => 'Delivery times vary by carrier and destination. Typically 3-7 business days for express and 7-14 days for standard.',
                'category'     => 'shipping',
                'order'        => 2,
                'is_published' => true,
            ],
            [
                'question'     => 'What items are prohibited from shipping?',
                'answer'       => 'Prohibited items include dangerous goods, flammable materials, weapons, and counterfeit products. Full list available in our terms.',
                'category'     => 'shipping',
                'order'        => 3,
                'is_published' => true,
            ],
            // Tracking
            [
                'question'     => 'How do I track my shipment?',
                'answer'       => 'Log in to your account and go to "My Shipments". Click on any shipment to see real-time tracking updates.',
                'category'     => 'tracking',
                'order'        => 1,
                'is_published' => true,
            ],
            [
                'question'     => 'How often is tracking updated?',
                'answer'       => 'Tracking information is updated in real-time as your package moves through the carrier network.',
                'category'     => 'tracking',
                'order'        => 2,
                'is_published' => true,
            ],
            // Payment
            [
                'question'     => 'How does the wallet system work?',
                'answer'       => 'Recharge your AeroTrek wallet using PayU. Your wallet balance is then used to pay for shipments at checkout.',
                'category'     => 'payment',
                'order'        => 1,
                'is_published' => true,
            ],
            [
                'question'     => 'What payment methods are accepted?',
                'answer'       => 'We accept credit cards, debit cards, UPI, net banking, and wallets via PayU payment gateway.',
                'category'     => 'payment',
                'order'        => 2,
                'is_published' => true,
            ],
            // General
            [
                'question'     => 'Is KYC verification mandatory?',
                'answer'       => 'Yes, KYC verification is required before booking your first shipment. It ensures compliance with international shipping regulations.',
                'category'     => 'general',
                'order'        => 1,
                'is_published' => true,
            ],
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
            ['key' => 'site_name',          'value' => 'AeroTrek Courier',                  'type' => 'text'],
            ['key' => 'site_email',         'value' => 'support@aerotrekcourier.com',        'type' => 'text'],
            ['key' => 'site_phone',         'value' => '+91 99999 99999',                    'type' => 'text'],
            ['key' => 'site_address',       'value' => 'Mumbai, Maharashtra, India',         'type' => 'text'],
            ['key' => 'site_logo',          'value' => null,                                 'type' => 'image'],
            ['key' => 'site_favicon',       'value' => null,                                 'type' => 'image'],
            ['key' => 'maintenance_mode',   'value' => 'false',                              'type' => 'boolean'],
            ['key' => 'social_facebook',    'value' => 'https://facebook.com/aerotrek',      'type' => 'text'],
            ['key' => 'social_instagram',   'value' => 'https://instagram.com/aerotrek',     'type' => 'text'],
            ['key' => 'social_twitter',     'value' => 'https://twitter.com/aerotrek',       'type' => 'text'],
            ['key' => 'meta_title',         'value' => 'AeroTrek Courier - Ship Globally',   'type' => 'text'],
            ['key' => 'meta_description',   'value' => 'Fast and reliable international courier booking platform.', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::create($setting);
        }

        $this->command->info('  → Site settings seeded (' . count($settings) . ')');
    }
}