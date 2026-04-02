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

[![Laravel](https://img.shields.io/badge/Laravel-11+-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18+-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://reactjs.org)
[![MongoDB](https://img.shields.io/badge/MongoDB-6+-47A248?style=for-the-badge&logo=mongodb&logoColor=white)](https://mongodb.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-Private-red?style=for-the-badge)](/)

**[aerotrekcourier.com](https://aerotrekcourier.com)** · Built by Tausheef & Sahil

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
│ MongoDB │  │ PayU   │ │  R2   │ │  Overseas    │
│ Atlas   │  │Gateway │ │Storage│ │ Logistics API│
└─────────┘  └────────┘ └───────┘ └──────────────┘
```

---

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 11+ (PHP 8.1+)
- **Database:** MongoDB 6+ (via `mongodb/laravel-mongodb`)
- **Authentication:** JWT (`tymon/jwt-auth`)
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
│   │   │   ├── Middleware/     # JWT, Admin, KYC, Wallet guards
│   │   │   └── Requests/       # Form validation
│   │   ├── Models/             # MongoDB Eloquent Models
│   │   ├── Services/           # Business logic layer
│   │   ├── Repositories/       # Data access layer
│   │   ├── Enums/              # ShipmentStatus, KycStatus, etc.
│   │   └── Traits/             # ApiResponse, HasUuid, Loggable
│   ├── config/
│   │   ├── database.php        # MongoDB connection
│   │   ├── jwt.php             # JWT configuration
│   │   ├── overseas.php        # Overseas API config
│   │   └── payu.php            # PayU config
│   └── routes/
│       ├── api.php             # Public + user API routes
│       ├── admin.php           # Admin routes
│       └── cms.php             # CMS routes
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
- PHP 8.1+
- Composer
- Node.js 18+
- MongoDB PHP Extension
- MongoDB Atlas account

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
MONGO_URI=        # Your MongoDB Atlas URI
MONGO_DB=aerotrek
JWT_SECRET=       # php artisan jwt:secret

# Generate keys
php artisan key:generate
php artisan jwt:secret

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

DB_CONNECTION=mongodb
MONGO_URI=mongodb+srv://<user>:<password>@cluster0.xxx.mongodb.net/
MONGO_DB=aerotrek

JWT_SECRET=
JWT_TTL=60

OVERSEAS_API_URL=https://apioverseaslogistic.in
OVERSEAS_API_KEY=

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

| Carrier | Booking Endpoint | Tracking |
|---------|-----------------|----------|
| 🟡 **DHL** | `/AddDHLShipment` | ✅ |
| 🟣 **FedEx** | `/AddFedExShipment` | ✅ |
| 🔴 **Aramex** | `/AddShipment` | ✅ |
| 🟤 **UPS** | `/AddUPSShipment` | ✅ |
| 🔵 **SELF/UK** | `/AddShipment` | ✅ |

---

## 🗃️ Database Collections

| Collection | Purpose |
|---|---|
| `users` | User accounts, wallet balance, KYC status |
| `admin_users` | Admin accounts (separate from users) |
| `kyc_records` | KYC documents and TID records |
| `shipments` | All booking records |
| `tracking_events` | Shipment tracking history |
| `wallet_transactions` | Credit/debit transaction logs |
| `rate_zones` | Country → Zone mappings |
| `rate_pricing` | Carrier pricing slabs |
| `pages` | CMS static pages |
| `blog_posts` | Blog articles |
| `faqs` | FAQ entries |
| `media` | Uploaded files metadata |
| `site_settings` | Key-value site configuration |

---

## 🗓️ Development Roadmap

```
Week 1  ████████░░  Foundation & MongoDB Setup         ✅ In Progress
Week 2  ░░░░░░░░░░  CMS Development
Week 3  ░░░░░░░░░░  User Management
Week 4  ░░░░░░░░░░  KYC & Rate Calculator
Week 5  ░░░░░░░░░░  Shipment Booking
Week 6  ░░░░░░░░░░  Wallet & PayU Integration
Week 7  ░░░░░░░░░░  Tracking & CMS Pages
Week 8  ░░░░░░░░░░  Admin Panel
Week 9  ░░░░░░░░░░  Testing & Refinement
Week 10 ░░░░░░░░░░  Deployment to Digital Ocean
```

---

---

## 🛡️ Security

- ✅ JWT token authentication on all API endpoints
- ✅ Bcrypt password hashing
- ✅ PayU hash verification on payment callbacks
- ✅ KYC/TID mandatory verification before booking
- ✅ CORS restricted to allowed origins
- ✅ Rate limiting & request throttling
- ✅ Input validation & NoSQL injection prevention
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