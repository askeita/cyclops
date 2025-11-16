# 🌀 Cyclops - Economic & Financial Crises API

**A free, comprehensive REST API providing detailed data on historical and contemporary economic and financial crises worldwide.**

## 📋 Overview

Cyclops is a specialized API platform that aggregates and provides structured information about major economic and financial crises throughout history. Built with **Symfony 7.3** and **API Platform**, it offers researchers, economists, students, and developers easy access to crisis data for analysis, research, and educational purposes.

## ✨ Key Features

### 🔍 **Comprehensive Crisis Database**
- **Economic Crises**: Recessions, depressions, sovereign debt crises
- **Financial Crises**: Banking crises, market crashes, currency crises
- **Historical Coverage**: From major historical events to recent crises (COVID-19 recession, 2008 financial crisis, etc.)

### 🎯 **Advanced Search Capabilities**
- Search by **name**, **category**, **origin country**
- Filter by **date ranges** (start/end dates)
- Filter by **duration** (in months)
- Search by **causes**, **consequences**, and **resolutions**
- Filter by **geographical extension** and **aggravating circumstances**
- Search by **price evolution patterns**

### 🔑 **Free API Access**
- **100% Free**: No subscription fees or usage limits
- **Simple Authentication**: Secure API key-based access
- **RESTful Design**: Standard HTTP methods and JSON responses
- **OpenAPI Documentation**: Interactive Swagger UI available

## 🏗️ Technical Architecture

### **Backend Stack**
- **Framework**: Symfony 7.3 (PHP 8.2+)
- **API Layer**: API Platform with GraphQL support
- **Authentication**: Multi-layer security system
  - Session-based authentication for web interface
  - API key authentication for API access
  - CSRF protection for forms

### **Frontend Stack**
- **JavaScript Framework**: Vue.js 3 with Composition API
- **Build Tool**: Webpack Encore
- **UI Components**: Custom responsive design
- **Security**: Rate limiting, XSS protection, input validation

### **Database Architecture**
Cyclops utilizes a **tri-database approach** for optimal performance, security, and scalability:

#### 🗄️ **SQLite Database** (`var/data.db`)
- **User Management**: Secure user authentication and API key storage
- **Encrypted Storage**: Email addresses are hashed using SHA3-512 with encryption keys
- **Password Security**: Argon2ID hashing with individual salts
- **Session Management**: Temporary authentication tokens

#### 🗄️ **Doctrine ORM Database** 
- **Entity Management**: ORM mappings and relationships
- **API Platform Integration**: Seamless integration with API Platform annotations
- **Development Support**: Entity validation and schema management

#### ☁️ **AWS DynamoDB** (Production Data Store)
- **Crisis Data Storage**: All crisis information stored in NoSQL format
- **High Performance**: Fast queries and scalable architecture  
- **Global Distribution**: AWS global infrastructure for low-latency access
- **Automatic Scaling**: Handles varying loads without manual intervention
- **Backup & Recovery**: Automated backup with point-in-time recovery

### **Security Features**
- **Rate Limiting**: Protection against brute force attacks
- **CSRF Protection**: Cross-site request forgery prevention  
- **Input Sanitization**: XSS attack prevention
- **Secure Authentication**: Multi-factor verification system
- **API Key Management**: Secure key generation and validation

## 🚀 Getting Started

### **1. Create Your Account**
1. Visit the Cyclops web interface
2. Sign up with your email address
3. Verify your email (check your inbox)
4. Log into your dashboard

### **2. Generate API Key**
1. Access your user dashboard
2. Click "Generate API Key"
3. Copy and securely store your API key
4. Use the key in your API requests

### **3. Make API Calls**
```bash
# Get all crises
curl -H "X-API-KEY: your_api_key_here" \
     https://your-cyclops-instance.com/api/crises

# Search financial crises
curl -H "X-API-KEY: your_api_key_here" \
     https://your-cyclops-instance.com/api/crises/search/by-category/financial

# Get crisis by specific date
curl -H "X-API-KEY: your_api_key_here" \
     https://your-cyclops-instance.com/api/crises/search/by-start-date/2008-01-01
```

## 📊 API Endpoints

### **Base Endpoints**
- `GET /api/crises` - List all crises
- `GET /api/crises/{id}` - Get specific crisis details

### **Search Endpoints**
- `GET /api/crises/search/by-name/{name}` - Search by crisis name
- `GET /api/crises/search/by-category/{category}` - Filter by category (economic/financial)
- `GET /api/crises/search/by-origin/{origin}` - Filter by country/region of origin
- `GET /api/crises/search/by-causes/{cause}` - Search by crisis causes
- `GET /api/crises/search/by-start-date/{date}` - Filter by start date
- `GET /api/crises/search/by-end-date/{date}` - Filter by end date
- `GET /api/crises/search/by-duration/{months}` - Filter by duration in months
- `GET /api/crises/search/by-consequences/{consequence}` - Search by consequences
- `GET /api/crises/search/by-resolutions/{resolution}` - Filter by resolution methods
- `GET /api/crises/search/by-geographical-extension/{region}` - Filter by affected regions
- `GET /api/crises/search/by-aggravating-circumstances/{circumstance}` - Filter by aggravating factors

## 🛡️ Privacy & Data Protection

### **Zero Personal Data Storage**
- **No Personal Information**: We do not store names, addresses, phone numbers, or personal details
- **Email Hashing**: Email addresses are cryptographically hashed and never stored in plain text
- **Anonymous Usage**: API usage is tracked anonymously for performance optimization only
- **No Tracking**: No user behavior tracking or profiling

### **What We Store**
- **Hashed Email**: One-way encrypted identifier for account management
- **API Keys**: Secure tokens for API access (can be regenerated anytime)
- **Usage Statistics**: Anonymous request counts for system optimization
- **Crisis Data**: Public historical information only

### **Data Security**
- **Encryption**: All sensitive data encrypted at rest and in transit
- **Secure Protocols**: HTTPS/TLS encryption for all communications
- **Regular Security Audits**: Continuous security monitoring and updates
- **GDPR Compliant**: Full compliance with European data protection regulations

## 🤝 Use Cases

- **Academic Research**: Historical crisis analysis and pattern recognition
- **Economic Forecasting**: Model training and validation data
- **Educational Tools**: Teaching materials and case studies
- **Financial Analysis**: Risk assessment and market research
- **Journalism**: Fact-checking and historical context
- **Policy Research**: Government and institutional analysis

## 📚 API Documentation

Interactive API documentation is available through the integrated Swagger UI interface. Access the full documentation at `/api/docs` on your Cyclops instance.

## 📦 Development

### **Requirements**
- PHP 8.2+
- Composer
- Node.js & npm/yarn
- SQLite support

### **Installation**
```bash
git clone https://github.com/your-repo/cyclops.git
cd cyclops
composer install
npm install
npm run build
```

## 📄 License

This project is licensed under a proprietary license. The API is free for use, but the source code has specific usage restrictions. See the LICENSE file for details.

## 🌟 Contributing

We welcome contributions to improve the crisis database and API functionality. Please read our contributing guidelines before submitting pull requests.

## 🔧 Support

For technical support, API questions, or data inquiries, please create an issue in the repository.

---

**Cyclops - Making economic crisis data accessible to everyone, for free.** 🌀
