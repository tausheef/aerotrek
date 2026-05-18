<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Faq;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->seedPages();
        $this->seedBlogCategories();
        $this->seedBlogPosts();
        $this->seedFaqs();
        $this->seedSiteSettings();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

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

        $tips     = BlogCategory::where('slug', 'shipping-tips')->first();
        $carriers = BlogCategory::where('slug', 'carrier-guides')->first();
        $customs  = BlogCategory::where('slug', 'customs-duties')->first();
        $news     = BlogCategory::where('slug', 'company-news')->first();

        $posts = [

            // ── Shipping Tips ──────────────────────────────────────────
            [
                'title'       => 'How to Pack a Parcel for International Shipping',
                'slug'        => 'how-to-pack-parcel-international-shipping',
                'excerpt'     => 'Bad packaging is the number-one cause of damage claims. Follow these steps to make sure your parcel survives a 10,000 km journey.',
                'content'     => '
<h2>Why Packaging Matters More Than You Think</h2>
<p>International shipments are handled by multiple parties — pickup agents, sorting centres, customs warehouses, airline cargo holds, and delivery crews. A parcel that seems secure on your dining table will be stacked, vibrated, and occasionally dropped before it reaches its destination. Good packaging is not about being overly cautious; it is about ensuring your item actually arrives.</p>

<h2>Step 1: Choose the Right Box</h2>
<p>Always use a new, double-walled corrugated box. Reused boxes have weakened corners and may not support the weight stacked on top. The box should be large enough to allow at least 5 cm of cushioning material on all six sides around your item.</p>
<ul>
  <li><strong>Under 2 kg:</strong> Single-wall box is acceptable for non-fragile items.</li>
  <li><strong>2–10 kg:</strong> Double-wall corrugated is strongly recommended.</li>
  <li><strong>Over 10 kg:</strong> Use a heavy-duty double-wall box or wooden crate for fragile goods.</li>
</ul>

<h2>Step 2: Cushion Everything</h2>
<p>Bubble wrap, foam peanuts, or air pillows should fill every gap inside the box. The item must not shift when you shake the sealed box. For fragile items, double-box them: wrap in bubble wrap, place in a small inner box, then cushion that inner box inside the outer box.</p>

<h2>Step 3: Seal Properly</h2>
<p>Use strong, pressure-sensitive packaging tape (at least 48 mm wide) and apply the H-tape method: three strips across all seams on both the top and bottom of the box. Never use twine, rope, or masking tape — they are not accepted by most carriers.</p>

<h2>Step 4: Label Clearly</h2>
<p>Print your shipping label on plain white paper and attach it to the flattest face of the box using clear tape. Cover the entire label with tape to prevent moisture damage. Include a duplicate label inside the box in case the outer one is damaged.</p>

<h2>Items That Need Special Packaging</h2>
<ul>
  <li><strong>Electronics:</strong> Anti-static bags + foam padding. Remove batteries if possible.</li>
  <li><strong>Liquids:</strong> Sealed in waterproof bags inside a leak-proof container. Max 1 litre per item for most carriers.</li>
  <li><strong>Documents:</strong> Rigid cardboard envelope or a tube. Mark "Do Not Bend".</li>
  <li><strong>Clothing/Textiles:</strong> Polybag to prevent moisture, then box or polybag outer.</li>
</ul>

<p>Following these steps takes an extra ten minutes but dramatically reduces the chance of a damage claim — and keeps your AeroTrek account in good standing.</p>',
                'featured_image'   => null,
                'category_id'      => $tips?->id,
                'author_id'        => null,
                'meta_title'       => 'How to Pack a Parcel for International Shipping',
                'meta_description' => 'Step-by-step guide to packaging your parcel for international courier shipments. Avoid damage claims with correct boxes, cushioning, and taping.',
                'is_published'     => true,
                'published_at'     => now()->subDays(2),
            ],

            [
                'title'       => '5 Common Mistakes That Delay International Shipments',
                'slug'        => '5-common-mistakes-delay-international-shipments',
                'excerpt'     => 'Most customs delays and carrier holds are caused by a handful of avoidable errors. Here is what to check before you book.',
                'content'     => '
<h2>The Good News: Most Delays Are Preventable</h2>
<p>Customs holds and carrier exceptions are frustrating, but the vast majority are triggered by the same small set of errors. Fix these before booking and your shipment will clear smoothly.</p>

<h2>1. Incorrect or Vague Commodity Description</h2>
<p>Writing "gifts" or "samples" on your commercial invoice is a red flag for customs officers in most countries. Be specific: "Cotton T-shirts, men\'s, machine-made, value INR 1,200 each" clears faster than "clothing item". Use the correct HS (Harmonised System) code for your goods — AeroTrek shows the required fields at booking.</p>

<h2>2. Under-Declaring the Value</h2>
<p>It is illegal to declare a lower value to reduce import duties for the recipient. Carriers audit declared values against market prices. If caught, the shipment is seized and you risk being blacklisted by that carrier. Declare the actual commercial value always.</p>

<h2>3. Wrong Receiver Address or Phone Number</h2>
<p>Carriers attempt delivery twice or three times before returning the parcel. A missing apartment number or an unreachable phone number means failed deliveries and return shipping costs charged to you. Double-check the full address, including postcode, and confirm the receiver\'s mobile number before booking.</p>

<h2>4. Missing Commercial Invoice for Non-Document Shipments</h2>
<p>Any shipment containing physical goods — even a gift — requires a commercial invoice. Three copies: one inside the box, one attached outside, one uploaded digitally at booking. AeroTrek generates this automatically if you fill in the commodity fields correctly.</p>

<h2>5. Prohibited or Restricted Items Hidden in the Package</h2>
<p>Lithium batteries, perfumes, aerosols, food products, and certain medicines require special handling or are restricted to specific carriers and routes. Always check the carrier\'s prohibited items list before packing. If in doubt, use AeroTrek\'s live chat support before booking.</p>

<h2>Quick Pre-Booking Checklist</h2>
<ul>
  <li>Accurate commodity description with quantity, material, and unit value</li>
  <li>Correct declared value in INR (or local currency)</li>
  <li>Full delivery address with postcode and working mobile number</li>
  <li>Commercial invoice ready (3 copies for goods shipments)</li>
  <li>No prohibited items in the package</li>
</ul>',
                'featured_image'   => null,
                'category_id'      => $tips?->id,
                'author_id'        => null,
                'meta_title'       => '5 Mistakes That Delay International Shipments',
                'meta_description' => 'Avoid customs holds and carrier exceptions by fixing these five common mistakes before booking your international shipment.',
                'is_published'     => true,
                'published_at'     => now()->subDays(9),
            ],

            // ── Carrier Guides ─────────────────────────────────────────
            [
                'title'       => 'DHL Express vs FedEx International Priority: Which is Faster?',
                'slug'        => 'dhl-express-vs-fedex-international-priority',
                'excerpt'     => 'Both DHL Express and FedEx International Priority claim next-day delivery to major cities. We break down when each one actually wins.',
                'content'     => '
<h2>Two Giants, One Question</h2>
<p>DHL Express and FedEx International Priority are the two most popular premium international services available on AeroTrek. Both offer day-definite delivery, proactive tracking, and extensive worldwide networks. The right choice depends on your specific origin–destination pair, weight, and sensitivity to cost.</p>

<h2>Network Strength</h2>
<p><strong>DHL Express</strong> operates the largest dedicated air cargo network in the world, with hubs in Leipzig, Dubai, Hong Kong, and Cincinnati. It has a particularly strong advantage in the Middle East, Africa, and South Asia — shipping from India to UAE with DHL is typically 1–2 days.</p>
<p><strong>FedEx</strong> dominates the US market with unmatched domestic ground integration, which benefits India-to-USA shipments. Once a parcel clears US customs in Memphis, final delivery is usually same-day or next-day regardless of the US destination address.</p>

<h2>Transit Times from India</h2>
<table>
  <thead><tr><th>Destination</th><th>DHL Express</th><th>FedEx Intl. Priority</th></tr></thead>
  <tbody>
    <tr><td>USA (major cities)</td><td>3–5 business days</td><td>2–4 business days</td></tr>
    <tr><td>UK</td><td>2–4 business days</td><td>3–5 business days</td></tr>
    <tr><td>UAE</td><td>1–2 business days</td><td>2–3 business days</td></tr>
    <tr><td>Australia</td><td>3–5 business days</td><td>4–6 business days</td></tr>
    <tr><td>Germany</td><td>2–4 business days</td><td>3–5 business days</td></tr>
  </tbody>
</table>

<h2>Price</h2>
<p>For shipments under 5 kg, DHL Express is typically 5–12% cheaper than FedEx on AeroTrek because of our negotiated DHL rates. For heavier shipments (10 kg+) going to the United States, FedEx is often more competitive.</p>

<h2>Which Should You Choose?</h2>
<ul>
  <li><strong>Shipping to UAE, Saudi Arabia, or Africa?</strong> → DHL Express. No contest.</li>
  <li><strong>Shipping to the USA for a business customer?</strong> → FedEx International Priority for reliability.</li>
  <li><strong>Shipping to Europe under 5 kg?</strong> → Compare rates on AeroTrek — DHL is often cheaper with comparable transit.</li>
  <li><strong>Sending to a remote address anywhere?</strong> → DHL has slightly better last-mile coverage in non-urban zones.</li>
</ul>

<p>The best approach is to let AeroTrek show you live rates for both carriers for your exact shipment and pick based on the combination of transit time and price that works for you.</p>',
                'featured_image'   => null,
                'category_id'      => $carriers?->id,
                'author_id'        => null,
                'meta_title'       => 'DHL Express vs FedEx International Priority from India',
                'meta_description' => 'Detailed comparison of DHL Express and FedEx International Priority for India outbound shipments — transit times, pricing, and when to choose each.',
                'is_published'     => true,
                'published_at'     => now()->subDays(14),
            ],

            [
                'title'       => 'Aramex vs UPS for India to UK Shipments',
                'slug'        => 'aramex-vs-ups-india-to-uk',
                'excerpt'     => 'Aramex has dominated the India-to-Gulf corridor for years, but how does it compare to UPS for shipments to the United Kingdom?',
                'content'     => '
<h2>Overview</h2>
<p>When shipping from India to the UK, shippers often overlook Aramex and UPS in favour of the more visible DHL and FedEx. That is a mistake. Both offer competitive rates and strong UK delivery networks that can save you meaningful money on regular shipments.</p>

<h2>Aramex</h2>
<p>Aramex was founded in Jordan and has built the most comprehensive network across the Middle East, South Asia, and Africa. For India-to-UK routes, Aramex typically transits through its Dubai hub before onward connection to the UK. This adds a few hours to DHL-direct but rarely more than a full day.</p>
<p><strong>Strengths:</strong> Very competitive pricing for 0.5–3 kg parcels. Excellent tracking transparency. Strong API integrations for e-commerce businesses. Aramex on AeroTrek often comes in 10–18% cheaper than DHL for this weight band.</p>
<p><strong>Weaknesses:</strong> Less brand recognition at the UK recipient end. Occasional delays during peak Middle East holiday periods (Eid, etc.).</p>

<h2>UPS Worldwide Express</h2>
<p>UPS operates its own dedicated air freighters and has a massive ground network in the UK through its acquisition of Parcelforce-connected routes. Shipments enter the UK through Cologne hub and are typically cleared and out for delivery the same day.</p>
<p><strong>Strengths:</strong> Outstanding UK delivery reliability. Time-definite guarantees with money-back options. Excellent for B2B shipments where a specific delivery date is critical.</p>
<p><strong>Weaknesses:</strong> Generally 15–25% more expensive than Aramex for the same India-to-UK route under 5 kg.</p>

<h2>Our Recommendation</h2>
<p>For regular e-commerce shipments to UK consumers, Aramex offers the best value on AeroTrek. For time-sensitive business documents or high-value goods where delivery date certainty is non-negotiable, the UPS premium is worth paying. Always run both quotes in the AeroTrek rate calculator to see the real difference for your parcel.</p>',
                'featured_image'   => null,
                'category_id'      => $carriers?->id,
                'author_id'        => null,
                'meta_title'       => 'Aramex vs UPS — India to UK Shipping Comparison',
                'meta_description' => 'Compare Aramex and UPS for India to UK international courier shipments. See transit times, pricing, and which carrier suits your needs.',
                'is_published'     => true,
                'published_at'     => now()->subDays(21),
            ],

            // ── Customs & Duties ───────────────────────────────────────
            [
                'title'       => 'Understanding Import Duties: A Guide for Indian Shippers',
                'slug'        => 'understanding-import-duties-guide-indian-shippers',
                'excerpt'     => 'Import duties are paid by your recipient, not you — but a wrong invoice can get your parcel stuck at customs for weeks. Here is what you need to know.',
                'content'     => '
<h2>Who Pays Import Duties?</h2>
<p>This is the most common point of confusion. When you send a parcel internationally, <strong>import duties and taxes are the responsibility of the recipient</strong> in their country — not the sender. However, as the shipper, you play a critical role in making sure the customs process goes smoothly by providing accurate documentation.</p>

<h2>What Triggers Import Duties?</h2>
<p>Virtually every country has a <em>de minimis</em> threshold — a minimum shipment value below which no duty is charged. Common thresholds:</p>
<ul>
  <li><strong>USA:</strong> USD 800 per shipment (very high — most Indian exports to the US are duty-free for the recipient)</li>
  <li><strong>UK:</strong> GBP 135 (above this, UK import VAT of 20% applies)</li>
  <li><strong>EU:</strong> EUR 150 for customs duty; EUR 0 for VAT (VAT applies to all commercial imports)</li>
  <li><strong>Australia:</strong> AUD 1,000</li>
  <li><strong>UAE:</strong> AED 300 (very low — most shipments above INR 6,000 value will attract UAE VAT)</li>
</ul>

<h2>How Is Duty Calculated?</h2>
<p>Import duty = (Declared value of goods + Freight cost) × Applicable duty rate</p>
<p>The duty rate depends on the commodity type, governed by the HS (Harmonised System) code. Clothing typically attracts 12% in the EU. Electronics may be 0% if covered by trade agreements. Luxury goods and alcohol attract the highest rates.</p>

<h2>Your Responsibilities as the Sender</h2>
<ol>
  <li><strong>Commercial Invoice:</strong> Must include item description, quantity, unit value, total value, HS code, country of origin, and your details. AeroTrek generates this automatically from your booking details.</li>
  <li><strong>Accurate Declared Value:</strong> The customs-declared value must match what the recipient would pay in a commercial transaction. Do not round down.</li>
  <li><strong>Country of Origin:</strong> Mark "Made in India" for Indian-manufactured goods. This affects which trade agreement rates apply.</li>
</ol>

<h2>Gift Shipments</h2>
<p>Many countries have a separate, lower threshold for gifts (e.g., UK GBP 39). To qualify, the package must be clearly marked "GIFT" and the declared value must be genuine. Commercially-purchased items being sent as gifts to a family member still qualify if marked correctly.</p>

<h2>When Customs Holds Your Parcel</h2>
<p>If customs holds your shipment, the carrier will typically contact the recipient to provide additional documents or pay the duties. As the sender, you may be contacted if the invoice is deemed incomplete. Respond quickly — most holds are resolved within 48 hours with the right paperwork.</p>',
                'featured_image'   => null,
                'category_id'      => $customs?->id,
                'author_id'        => null,
                'meta_title'       => 'Understanding Import Duties for Indian Shippers',
                'meta_description' => 'Learn how import duties work for international shipments from India — who pays, thresholds by country, and how to avoid customs delays.',
                'is_published'     => true,
                'published_at'     => now()->subDays(18),
            ],

            [
                'title'       => 'HS Codes Explained: How to Find the Right Code for Your Shipment',
                'slug'        => 'hs-codes-explained-find-right-code-shipment',
                'excerpt'     => 'The Harmonised System code is a 6–10 digit number that customs agencies worldwide use to classify goods. Using the wrong one can result in delays or overpaid duties.',
                'content'     => '
<h2>What Is an HS Code?</h2>
<p>The Harmonised System (HS) is an international nomenclature developed by the World Customs Organization (WCO) to classify traded products. Every physical good traded internationally has an HS code. Customs agencies use it to determine the applicable duty rate and to flag restricted goods.</p>
<p>The first six digits are universal worldwide. Countries can extend this to 8 or 10 digits for more granular classification. India uses an 8-digit ITC-HS code; the USA uses a 10-digit HTS code.</p>

<h2>Structure of an HS Code</h2>
<pre>
Chapter (2 digits) → 62  = Articles of apparel, not knitted
Heading (4 digits) → 6201 = Men\'s overcoats, car-coats, capes
Subheading (6 digits) → 620111 = Of wool or fine animal hair
</pre>

<h2>How to Find the Right Code</h2>
<ol>
  <li><strong>Use the WCO or government trade portals.</strong> India\'s DGFT website has a searchable ITC-HS database at <em>dgft.gov.in</em>. Type the product name and browse the results.</li>
  <li><strong>Be specific.</strong> Search for "cotton T-shirt men\'s" not "clothing". The more specific your search, the more accurate the result.</li>
  <li><strong>When in doubt, call your carrier.</strong> DHL and FedEx both have customs advisory helplines. AeroTrek support can also assist for common commodity types.</li>
</ol>

<h2>Common HS Codes for Indian Exports</h2>
<table>
  <thead><tr><th>Item</th><th>HS Code</th></tr></thead>
  <tbody>
    <tr><td>Cotton T-shirts (men\'s)</td><td>6109 10 00</td></tr>
    <tr><td>Handmade jewellery (gold)</td><td>7113 19 10</td></tr>
    <tr><td>Mobile phone</td><td>8517 12 10</td></tr>
    <tr><td>Laptop computer</td><td>8471 30 10</td></tr>
    <tr><td>Spices (mixed)</td><td>0910 91 00</td></tr>
    <tr><td>Printed books</td><td>4901 99 00</td></tr>
    <tr><td>Leather handbag</td><td>4202 21 10</td></tr>
    <tr><td>Ayurvedic medicines</td><td>3004 90 00</td></tr>
  </tbody>
</table>

<h2>Consequences of Using the Wrong HS Code</h2>
<ul>
  <li>Customs hold while they reclassify the goods — can take 3–10 days</li>
  <li>Overpayment of duties if a higher-rate code is applied by customs</li>
  <li>In serious cases, confiscation of goods if the code corresponds to a restricted category</li>
</ul>
<p>Take five minutes to find the correct code before booking. It is one of the highest-leverage actions you can take to ensure a smooth delivery.</p>',
                'featured_image'   => null,
                'category_id'      => $customs?->id,
                'author_id'        => null,
                'meta_title'       => 'HS Codes Explained for Indian Shippers',
                'meta_description' => 'What are HS codes, how to find the right one for your goods, and why getting it wrong causes costly customs delays.',
                'is_published'     => true,
                'published_at'     => now()->subDays(30),
            ],

            // ── Company News ───────────────────────────────────────────
            [
                'title'       => 'AeroTrek Launches India Post International Shipping',
                'slug'        => 'aerotrek-launches-india-post-international-shipping',
                'excerpt'     => 'You can now book India Post international shipments directly through AeroTrek — same wallet, same tracking, same simple booking flow.',
                'content'     => '
<h2>India Post Now Available on AeroTrek</h2>
<p>We are excited to announce that India Post international shipping services are now available on the AeroTrek platform. Starting today, you can compare India Post rates alongside DHL, FedEx, Aramex, and UPS — and book everything in one place.</p>

<h2>Why India Post?</h2>
<p>India Post serves over 150 countries and is one of the most cost-effective options for non-urgent international parcels, especially for:</p>
<ul>
  <li>Small and lightweight shipments (under 2 kg)</li>
  <li>B2C e-commerce orders where the recipient expects economy delivery</li>
  <li>Shipments to regions where premium carriers charge significantly higher rates</li>
</ul>

<h2>Same Experience, More Choice</h2>
<p>Nothing changes about how you book on AeroTrek. Enter your shipment details, compare live rates, and select the service that fits your budget and timeline. India Post options will appear in your rate results alongside premium carriers whenever they are available for your destination.</p>
<p>Your AeroTrek wallet balance works for India Post bookings just like any other carrier. Tracking updates appear in the same shipment timeline in your dashboard.</p>

<h2>Getting Started</h2>
<p>Log in to your AeroTrek account, head to Book Shipment, and you will see India Post rates in the results for eligible routes. KYC verification is required as usual before your first booking.</p>',
                'featured_image'   => null,
                'category_id'      => $news?->id,
                'author_id'        => null,
                'meta_title'       => 'AeroTrek Now Supports India Post International Shipping',
                'meta_description' => 'Book India Post international shipments through AeroTrek. Compare rates with DHL, FedEx, Aramex and UPS in one platform.',
                'is_published'     => true,
                'published_at'     => now()->subDays(4),
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
            if (is_array($setting['value'])) {
                $setting['value'] = json_encode($setting['value']);
            }
            SiteSetting::create($setting);
        }

        $this->command->info('  → Site settings seeded (' . count($settings) . ')');
    }
}