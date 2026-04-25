<div align="center">

```
 █████╗ ███████╗██████╗  ██████╗ ████████╗██████╗ ███████╗██╗  ██╗
██╔══██╗██╔════╝██╔══██╗██╔═══██╗╚══██╔══╝██╔══██╗██╔════╝██║ ██╔╝
███████║█████╗  ██████╔╝██║   ██║   ██║   ██████╔╝█████╗  █████╔╝ 
██╔══██║██╔══╝  ██╔══██╗██║   ██║   ██║   ██╔══██╗██╔══╝  ██╔═██╗ 
██║  ██║███████╗██║  ██║╚██████╔╝   ██║   ██║  ██║███████╗██║  ██╗
╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝ ╚═════╝   ╚═╝   ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
                         C O U R I E R
```

### White-Label International Courier Booking Platform

[![Laravel](https://img.shields.io/badge/Laravel-13+-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18+-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://reactjs.org)
[![MySQL](https://img.shields.io/badge/MySQL-8+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-Private-red?style=for-the-badge)](/)

**[aerotrekcourier.com](https://aerotrekcourier.com)**

</div>

---

## ✈️ Overview

**AeroTrek Courier** is a production-grade white-label international courier booking platform that enables customers to compare rates, book shipments, and track packages across multiple carriers — all from a single, seamless interface.

The platform integrates with **DHL**, **FedEx**, **Aramex**, **UPS**, and **SELF/UK** through the Overseas Logistics API, offering real-time rates, live tracking, and a wallet-based prepaid payment system powered by PayU.

---

## ⚡ Key Features

| Feature | Description |
|---|---|
| 🏷️ **Multi-Carrier Rates** | Real-time price comparison across DHL, FedEx, Aramex, UPS, SELF/UK |
| 🔐 **KYC/TID Verification** | Mandatory compliance verification via Overseas Logistics API |
| 💳 **Wallet & PayU** | Prepaid wallet system with UPI, cards, and net banking support |
| 📦 **Shipment Booking** | Multi-step booking form with label generation |
| 📍 **Live Tracking** | Real-time shipment tracking with timeline view |
| 🖥️ **Full CMS** | Pages, Blog, FAQs, Media Library, Menu Builder |
| 🛡️ **Admin Panel** | Complete control over users, shipments, rates & content |
| 📱 **Responsive UI** | Mobile-first design for all screen sizes |

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     React.js Frontend                    │
│           (Customer Portal + Admin Panel + CMS)          │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP / REST API
┌────────────────────────▼────────────────────────────────┐
│                   Laravel Backend API                    │
│              (Business Logic + JWT Auth)                 │
└──────┬──────────┬──────────┬──────────┬─────────────────┘
       │          │          │          │
┌──────▼──┐  ┌───▼────┐ ┌───▼───┐ ┌───▼──────────┐
│  MySQL  │  │ PayU   │ │  R2   │ │  Overseas    │
│    8+   │  │Gateway │ │Storage│ │ Logistics API│
└─────────┘  └────────┘ └───────┘ └──────────────┘
```

---

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 13+ (PHP 8.3+)
- **Database:** MySQL 8+ (via Laravel Eloquent)
- **Authentication:** JWT (`tymon/jwt-auth`)
- **Wallet:** bavix/laravel-wallet
- **File Storage:** Cloudflare R2 (S3-compatible)
- **Payment:** PayU Gateway
- **Web Server:** Nginx on Digital Ocean

### Frontend
- **Framework:** React.js 18+
- **Routing:** React Router v6
- **State:** Redux / Context API
- **UI:** Material-UI / Tailwind CSS
- **HTTP:** Axios
- **Editor:** TinyMCE / Quill (CMS)

### External APIs
- **Overseas Logistics API** — KYC, booking, tracking
- **PayU** — Wallet top-up & payments
- **Cloudflare R2** — Document & media storage

---

## 📁 Project Structure

```
aerotrek/
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── API/V1/     # Auth, KYC, Booking, Tracking, Wallet
│   │   │   │   ├── Admin/      # Admin Panel Controllers
│   │   │   │   └── CMS/        # CMS Controllers
│   │   │   ├── Middleware/     # JWT, Admin, KYC guards
│   │   │   └── Requests/       # Form validation
│   │   ├── Models/             # Eloquent Models (MySQL)
│   │   ├── Services/           # Business logic layer
│   │   └── Traits/             # ApiResponse
│   ├── config/
│   │   ├── database.php        # MySQL connection
│   │   ├── jwt.php             # JWT configuration
│   │   ├── wallet.php          # bavix wallet config
│   │   ├── overseas.php        # Overseas API config
│   │   └── payu.php            # PayU config
│   └── routes/
│       └── api.php             # All API routes
│
└── frontend/                   # React Application
    ├── src/
    │   ├── pages/              # Route-level components
    │   ├── components/         # Reusable UI components
    │   ├── store/              # Redux store
    │   ├── services/           # Axios API calls
    │   └── utils/              # Helpers
    └── public/
```

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 8+

### Backend Setup

```bash
# Clone the repository
git clone https://github.com/yourusername/aerotrek-courier.git
cd aerotrek-courier/backend

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Configure your .env
APP_KEY=          # php artisan key:generate
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aerotrek
DB_USERNAME=root
DB_PASSWORD=
JWT_SECRET=       # php artisan jwt:secret

# Generate keys
php artisan key:generate
php artisan jwt:secret

# Run migrations & seed
php artisan migrate
php artisan db:seed

# Start the server
php artisan serve
```

### Frontend Setup

```bash
cd ../frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env.local

# Configure API URL
VITE_API_URL=http://localhost:8000/api

# Start development server
npm run dev
```

---

## 🔐 Environment Variables

### Backend `.env`

```dotenv
APP_NAME=AeroTrek
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aerotrek
DB_USERNAME=root
DB_PASSWORD=

JWT_SECRET=
JWT_TTL=60

OVERSEAS_API_URL=https://apioverseaslogistic.in
OVERSEAS_CLIENT_ID=
OVERSEAS_CLIENT_SECRET=
OVERSEAS_ACCOUNT_CODE=

PAYU_MERCHANT_KEY=
PAYU_MERCHANT_SALT=
PAYU_MODE=test
PAYU_SUCCESS_URL=https://aerotrekcourier.com/payment/success
PAYU_FAILURE_URL=https://aerotrekcourier.com/payment/failure

CLOUDFLARE_R2_KEY=
CLOUDFLARE_R2_SECRET=
CLOUDFLARE_R2_BUCKET=
CLOUDFLARE_R2_URL=
```

---

## 📦 Supported Carriers

| Carrier | Service | Tracking |
|---------|---------|----------|
| 🟡 **DHL** | DHL Express | ✅ |
| 🟣 **FedEx** | FedEx International Priority | ✅ |
| 🔴 **Aramex** | Aramex Priority Express | ✅ |
| 🟤 **UPS** | UPS Worldwide Saver | ✅ |
| 🔵 **SELF UK** | DPD | ✅ |
| 🟢 **SELF Europe** | DPD via Germany | ✅ |
| 🟠 **SELF Dubai** | Direct | ✅ |
| ⚪ **SELF Australia** | Toll Express | ✅ |
| ⚪ **SELF NZ** | NZ Post | ✅ |
| ⚪ **SELF Canada** | UPS Last Mile | ✅ |

---

## 🗃️ Database Tables

| Table | Purpose |
|---|---|
| `users` | User accounts, KYC status |
| `kycs` | KYC documents |
| `shipments` | All booking records |
| `wallets` | bavix wallet accounts |
| `transactions` | Wallet credit/debit logs |
| `wallet_recharges` | PayU recharge records |
| `addresses` | Saved user addresses |
| `rate_zones` | Country → Zone mappings |
| `rate_pricings` | Carrier pricing slabs |
| `australia_postcodes` | Australia zone postcode mapping |
| `atk_counters` | ATK ID sequential counter |
| `pages` | CMS static pages |
| `blog_posts` | Blog articles |
| `blog_categories` | Blog categories |
| `faqs` | FAQ entries |
| `media` | Uploaded files metadata |
| `site_settings` | Key-value site configuration |

---

## 🗓️ Development Roadmap

```
Week 1  ██████████  Foundation & MySQL Setup              ✅ completed
Week 2  ██████████  CMS Development                       ✅ completed
Week 3  ██████████  User Management & Auth                ✅ completed
Week 4  ██████████  KYC & Rate Calculator                 ✅ completed
Week 5  ██████████  Shipment Booking                      ✅ completed
Week 6  ░░░░░░░░░░  Wallet & PayU Integration
Week 7  ░░░░░░░░░░  Tracking & CMS Pages
Week 8  ░░░░░░░░░░  Admin Panel
Week 9  ░░░░░░░░░░  Testing & Refinement
Week 10 ░░░░░░░░░░  Deployment to Digital Ocean
```

---

## 🛡️ Security

- ✅ JWT token authentication on all API endpoints
- ✅ Bcrypt password hashing
- ✅ PayU hash verification on payment callbacks
- ✅ KYC mandatory verification before booking
- ✅ CORS restricted to allowed origins
- ✅ Input validation & SQL injection prevention
- ✅ File upload validation (type, size)
- ✅ HTTPS/SSL enforced on production

---

## 📬 Contact

🌐 **Domain:** [aerotrekcourier.com](https://aerotrekcourier.com)

---

<div align="center">

**Built with ❤️ for seamless international shipping**

*AeroTrek Courier © 2026 — All Rights Reserved*

</div>
