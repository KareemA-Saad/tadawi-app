# 🏥 Tadawi - Healthcare Medicine Management Platform

<div align="center">

![Tadawi Logo](https://img.shields.io/badge/Tadawi-Healthcare-blue?style=for-the-badge&logo=medical-bag)
![Laravel](https://img.shields.io/badge/Laravel-12.x-red?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-blue?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange?style=for-the-badge&logo=mysql)

[![Backend API](https://img.shields.io/badge/Backend%20API-View%20Now-green?style=for-the-badge)](https://tadawi-app-deploy-main-zwrtj5.laravel.cloud/)
[![Frontend App](https://img.shields.io/badge/Frontend%20App-View%20Now-blue?style=for-the-badge)](https://tadawi.vercel.app/)
[![Frontend Repo](https://img.shields.io/badge/Frontend%20Repo-GitHub-orange?style=for-the-badge)](https://github.com/mostafaomar7/Tadawi)
[![API Documentation](https://img.shields.io/badge/API-Documentation-purple?style=for-the-badge)](#api-endpoints)
[![License](https://img.shields.io/badge/License-MIT-yellow?style=for-the-badge)](LICENSE)

*A comprehensive healthcare platform connecting patients, pharmacies, and doctors for seamless medicine management and ordering.*

</div>

---

## 📋 Table of Contents

- [🌟 Features](#-features)
- [🏗️ Architecture](#️-architecture)
- [🚀 Quick Start](#-quick-start)
- [📦 Installation](#-installation)
- [⚙️ Configuration](#️-configuration)
- [🔧 Development](#-development)
- [📚 API Documentation](#-api-documentation)
- [🌐 Live Demo](#-live-demo)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

---

## 🌟 Features

### 🔐 **Authentication & User Management**
- **Multi-role System**: Patient, Pharmacy, Doctor, Admin roles
- **Email Verification**: OTP-based email verification
- **Google OAuth**: Social login integration
- **Password Reset**: Secure OTP-based password recovery
- **Profile Management**: Role-specific profile creation

### 💊 **Medicine Management**
- **Comprehensive Medicine Database**: Brand names, generic names, active ingredients
- **Therapeutic Classification**: Organized by therapeutic classes
- **Medicine Search**: Advanced search with alternatives and suggestions
- **Medicine Correction**: AI-powered medicine name correction and validation
- **Stock Management**: Batch tracking with expiry dates and quantities

### 🏪 **Pharmacy Management**
- **Pharmacy Profiles**: Location-based pharmacy registration
- **Stock Management**: Real-time inventory tracking
- **Medicine Addition**: Easy medicine addition to pharmacy stock
- **Verification System**: Pharmacy verification and status management
- **Rating System**: Customer reviews and ratings

### 🛒 **E-commerce Features**
- **Shopping Cart**: Multi-pharmacy cart management
- **Order Processing**: Complete order lifecycle management
- **Payment Integration**: PayPal payment gateway
- **Order History**: Comprehensive order tracking
- **Prescription Upload**: Digital prescription management

### 🎁 **Donation System**
- **Medicine Donations**: Community medicine donation platform
- **Donation Tracking**: Status tracking (proposed, under review, approved, collected)
- **Photo Documentation**: Donation photo management
- **Verification Process**: Admin verification system

### 🔍 **Advanced Search & Discovery**
- **Medicine Search**: Intelligent medicine search with alternatives
- **Location-based Search**: Find nearby pharmacies
- **Drug Interaction Check**: Safety checks for medicine combinations
- **Alternative Medicine Suggestions**: Smart recommendations

### 📊 **Analytics & Dashboard**
- **Admin Dashboard**: Comprehensive system overview
- **User Statistics**: Role-based user analytics
- **Order Analytics**: Sales and order tracking
- **Medicine Shortage Alerts**: Inventory management insights
- **Donation Analytics**: Community contribution tracking

### 🔒 **Security & Compliance**
- **API Authentication**: Laravel Sanctum token-based authentication
- **Data Validation**: Comprehensive input validation
- **Soft Deletes**: Data integrity with soft deletion
- **CORS Configuration**: Cross-origin resource sharing setup

---

## 🏗️ Architecture

### **Backend Stack**
- **Framework**: Laravel 12.x
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Sanctum
- **API**: RESTful API with JSON responses

### **Frontend Stack**
- **Build Tool**: Vite
- **CSS Framework**: Tailwind CSS 4.0
- **JavaScript**: ES6+ modules
- **Package Manager**: NPM

### **External Services**
- **Payment Gateway**: PayPal SDK
- **Email Service**: Laravel Mail
- **Drug Database**: RxNav API
- **Maps Integration**: Google Maps API
- **OCR Service**: Prescription scanning

### **Database Schema**
```
Users (Multi-role)
├── Patient Profiles
├── Pharmacy Profiles
├── Doctor Profiles
└── Admin Access

Medicines
├── Active Ingredients
├── Therapeutic Classes
├── Stock Batches
└── Order Items

Orders
├── Cart Management
├── Payment Processing
├── Prescription Uploads
└── Order History

Donations
├── Medicine Donations
├── Photo Documentation
└── Verification Status
```

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js 18+ and NPM
- MySQL 8.0+
- Git

### One-Command Setup
```bash
# Clone the repository
git clone https://github.com/KareemA-Saad/tadawi-app.git
cd tadawi-app

# Install dependencies
composer install && npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database and run migrations
php artisan migrate --seed

# Start development servers
composer run dev
```

---

## 📦 Installation

### 1. **Clone Repository**
```bash
git clone https://github.com/KareemA-Saad/tadawi-app.git
cd tadawi-app
```

### 2. **Install Dependencies**
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. **Environment Configuration**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. **Database Setup**
```bash
# Create database (MySQL)
mysql -u root -p
CREATE DATABASE tadawi_app;

# Run migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed
```

### 5. **Build Assets**
```bash
# Build frontend assets
npm run build

# Or for development
npm run dev
```

### 6. **Start Application**
```bash
# Start Laravel development server
php artisan serve

# Start queue worker (for background jobs)
php artisan queue:work

# Start frontend development server
npm run dev
```

---

## ⚙️ Configuration

### **Environment Variables**
```env
# Application
APP_NAME="Tadawi"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tadawi_app
DB_USERNAME=root
DB_PASSWORD=

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback

# PayPal Configuration
PAYPAL_CLIENT_ID=your-paypal-client-id
PAYPAL_CLIENT_SECRET=your-paypal-client-secret
PAYPAL_MODE=sandbox

# External APIs
GOOGLE_MAPS_API_KEY=your-google-maps-key
OCR_API_KEY=your-ocr-api-key
```

### **Database Seeding**
```bash
# Seed with test data
php artisan db:seed --class=TestDataSeeder

# Seed specific data
php artisan db:seed --class=MedicineSeeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=PharmacyProfileSeeder
```

---

## 🔧 Development

### **Development Commands**
```bash
# Start all development services
composer run dev

# Individual services
php artisan serve          # Laravel server
php artisan queue:work     # Queue worker
npm run dev               # Frontend development
```

### **Testing**
```bash
# Run tests
php artisan test

# Run specific test
php artisan test --filter=AuthTest
```

### **Code Quality**
```bash
# Laravel Pint (Code formatting)
./vendor/bin/pint

# PHPUnit tests
./vendor/bin/phpunit
```

### **Database Management**
```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name

# Create new model with migration
php artisan make:model ModelName -m
```

---

## 📚 API Documentation

### **Base URLs**
```
Backend API: https://tadawi-app-deploy-main-zwrtj5.laravel.cloud/api/v1
Frontend App: https://tadawi.vercel.app/
Frontend Repo: https://github.com/mostafaomar7/Tadawi
```

### **Authentication Endpoints**
```http
POST /auth/register              # User registration
POST /auth/login                 # User login
POST /auth/logout                # User logout
GET  /auth/me                    # Get current user
POST /auth/verify-otp            # Verify OTP
POST /auth/resend-otp            # Resend OTP
POST /auth/send-password-reset-otp # Send password reset OTP
POST /auth/reset-password        # Reset password
GET  /auth/google/redirect       # Google OAuth redirect
GET  /auth/google/callback       # Google OAuth callback
POST /auth/update-role           # Update user role
```

### **Medicine Endpoints**
```http
GET  /medicines/search           # Search medicines
GET  /medicines                  # Get all medicines
POST /search/with-alternatives  # Search with alternatives
POST /medicine-correction/correct # Correct medicine name
GET  /medicine-correction/autocomplete # Medicine autocomplete
```

### **Pharmacy Endpoints**
```http
GET  /pharmacies                # Get all pharmacies
GET  /pharmacies/nearby         # Get nearby pharmacies
GET  /pharmacies/{id}           # Get specific pharmacy
POST /pharmacies                # Create pharmacy profile
PUT  /pharmacies/{id}           # Update pharmacy profile
DELETE /pharmacies/{id}         # Delete pharmacy profile
GET  /pharmacies/my             # Get user's pharmacy
```

### **Order Endpoints**
```http
GET  /orders                    # Get user orders
GET  /orders/{id}               # Get specific order
GET  /orders/stats               # Get order statistics
```

### **Cart Endpoints**
```http
GET  /cart                      # Get user cart
POST /cart                      # Add item to cart
DELETE /cart/clear              # Clear cart
DELETE /cart/{item}            # Remove item from cart
GET  /cart/recommendations      # Get cart recommendations
```

### **Checkout Endpoints**
```http
GET  /checkout/validate/{pharmacy_id}     # Validate cart for checkout
GET  /checkout/summary/{pharmacy_id}      # Get checkout summary
POST /checkout/initiate/{pharmacy_id}     # Initiate checkout
POST /checkout/paypal/{pharmacy_id}       # Process PayPal payment
GET  /checkout/payment-status/{order_id}  # Get payment status
```

### **Donation Endpoints**
```http
GET  /donations                 # Get user donations
POST /donations                 # Create donation
GET  /donations/{id}            # Get specific donation
PUT  /donations/{id}            # Update donation
DELETE /donations/{id}          # Delete donation
GET  /donations-available       # Get available donations
GET  /donations-all             # Get all donations (public)
```

### **Stock Management Endpoints**
```http
GET  /stock-batches             # Get stock batches
GET  /stock-batches/summary     # Get stock summary
GET  /stock-batches/expired     # Get expired batches
GET  /stock-batches/expiring-soon # Get expiring batches
GET  /stock-batches/low-stock   # Get low stock batches
POST /stock-batches             # Create stock batch
PUT  /stock-batches/{id}        # Update stock batch
DELETE /stock-batches/{id}     # Delete stock batch
```

### **Drug Interaction Endpoints**
```http
POST /interactions/check        # Check drug interactions
```

### **Response Format**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data
  },
  "meta": {
    // Pagination or additional metadata
  }
}
```

---

## 🌐 Live Demo

### **Production URLs**
- 🔗 **Backend API**: [https://tadawi-app-deploy-main-zwrtj5.laravel.cloud/](https://tadawi-app-deploy-main-zwrtj5.laravel.cloud/)
- 🎨 **Frontend App**: [https://tadawi.vercel.app/](https://tadawi.vercel.app/)
- 📁 **Frontend Repository**: [https://github.com/mostafaomar7/Tadawi](https://github.com/mostafaomar7/Tadawi)

### **Demo Credentials**
```
Email: cairo.central@tadawi.com
Password: password
```

### **Features Available in Demo**
- ✅ User Registration & Authentication
- ✅ Medicine Search & Discovery
- ✅ Pharmacy Management
- ✅ Order Processing
- ✅ Donation System
- ✅ Drug Interaction Checking
- ✅ Admin Dashboard
- ✅ Responsive Frontend Interface

---

## 🛠️ Technology Stack

### **Backend**
- **Laravel 12.x** - PHP Framework
- **Laravel Sanctum** - API Authentication
- **Laravel Socialite** - OAuth Integration
- **PayPal SDK** - Payment Processing
- **Guzzle HTTP** - HTTP Client

### **Frontend**
- **Vite** - Build Tool
- **Tailwind CSS 4.0** - CSS Framework
- **Axios** - HTTP Client

### **Database**
- **MySQL 8.0+** - Primary Database
- **Eloquent ORM** - Database Abstraction

### **External Services**
- **Google OAuth** - Social Authentication
- **Google Maps API** - Location Services
- **RxNav API** - Drug Database
- **PayPal API** - Payment Gateway
- **OCR Services** - Prescription Scanning

---

## 📁 Project Structure

```
tadawi-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── MedicineController.php
│   │   │   │   ├── PharmacyController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── CartController.php
│   │   │   │   ├── CheckoutController.php
│   │   │   │   ├── DonationController.php
│   │   │   │   └── Dashboard/
│   │   │   └── InteractionController.php
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Models/
│   │   ├── User.php
│   │   ├── Medicine.php
│   │   ├── Order.php
│   │   ├── PharmacyProfile.php
│   │   ├── Donation.php
│   │   └── ...
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── CartService.php
│   │   ├── CheckoutService.php
│   │   ├── OrderService.php
│   │   ├── PaymentService.php
│   │   └── RxNavService.php
│   └── Traits/
│       ├── ApiResponse.php
│       └── ImageHandling.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── dashboard.php
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
└── public/
```

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

### **1. Fork the Repository**
```bash
# Backend Repository
git clone https://github.com/KareemA-Saad/tadawi-app.git
cd tadawi-app

# Frontend Repository
git clone https://github.com/mostafaomar7/Tadawi.git
cd Tadawi
```

### **2. Create Feature Branch**
```bash
git checkout -b feature/amazing-feature
```

### **3. Make Changes**
- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation

### **4. Commit Changes**
```bash
git commit -m "Add amazing feature"
```

### **5. Push to Branch**
```bash
git push origin feature/amazing-feature
```

### **6. Create Pull Request**
- Describe your changes
- Link any related issues
- Request review from maintainers

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📞 Support

For support and questions:
- 📧 Email: support@tadawi.com
- 🐛 Issues: [GitHub Issues](https://github.com/KareemA-Saad/tadawi-app/issues)
- 📖 Documentation: [Wiki](https://github.com/KareemA-Saad/tadawi-app/wiki)

---

<div align="center">

**Made with ❤️ for Healthcare**

[![GitHub stars](https://img.shields.io/github/stars/KareemA-Saad/tadawi-app?style=social)](https://github.com/KareemA-Saad/tadawi-app)
[![GitHub forks](https://img.shields.io/github/forks/KareemA-Saad/tadawi-app?style=social)](https://github.com/KareemA-Saad/tadawi-app)
[![Frontend stars](https://img.shields.io/github/stars/mostafaomar7/Tadawi?style=social)](https://github.com/mostafaomar7/Tadawi)
[![Frontend forks](https://img.shields.io/github/forks/mostafaomar7/Tadawi?style=social)](https://github.com/mostafaomar7/Tadawi)

</div>
