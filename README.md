# AFIT Virtual Admission System (VAS)

A cloud-based virtual admission system for the Air Force Institute of Technology (AFIT), Kaduna. This system digitises the entire student admission lifecycle — from applicant registration and document submission to automated eligibility screening and administrative decision-making.

## Features

### Applicant Portal
- **Registration & Authentication** — Secure registration with email verification and JWT-based login
- **4-Step Application Form** — Guided application with programme selection, personal info, O'Level results entry, and review
- **Document Upload** — Upload WAEC/NECO results, JAMB slip, birth certificate, passport photo, and more
- **Automated Eligibility Screening** — Instant O'Level result screening against programme-specific requirements
- **Real-Time Status Tracking** — Timeline view of application progress with notifications
- **Dashboard** — Overview of all applications, quick actions, and recent notifications

### Admin Portal
- **Dashboard & Statistics** — At-a-glance overview of applications by status and programme
- **Application Management** — Search, filter, paginate, and review all applications
- **Application Review** — Detailed view with personal info, O'Level results, eligibility check, documents, and status history
- **Document Verification** — Verify or reject uploaded documents with reasons
- **Status Updates** — Change application status (Under Review, Admitted, Rejected) with admin notes
- **Reports & Analytics** — Date-range reports with programme breakdowns, admission rates, daily submissions
- **CSV Export** — Download all application data as CSV
- **Audit Trail** — Complete log of all administrative actions

### Technical
- **JWT Authentication** — Stateless authentication for horizontal scaling
- **Role-Based Access Control** — Applicant, Admin, Super Admin roles
- **Eligibility Engine** — Automated O'Level screening with subject alias matching
- **Notification System** — In-app notifications for status changes
- **Responsive Design** — Mobile-friendly CSS with breakpoints at 768px and 480px
- **Security** — Bcrypt password hashing, PDO prepared statements, input validation, security headers

## Technology Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Backend | PHP 8.x (Custom MVC, no framework) |
| Database | MySQL 8.0 |
| Authentication | JWT (firebase/php-jwt) |
| Styling | Custom CSS Design System |
| Deployment | Railway / AWS (Cloud-Native) |

## Project Structure

```
afit-vas/
├── backend/
│   ├── public/
│   │   ├── index.php              # API entry point
│   │   └── .htaccess              # URL rewriting
│   ├── src/
│   │   ├── Config/
│   │   │   ├── Database.php       # PDO database connection
│   │   │   └── Router.php         # Custom URL router
│   │   ├── Controllers/
│   │   │   ├── BaseController.php # JSON response helpers, validation, pagination
│   │   │   ├── AuthController.php # Registration, login, profile
│   │   │   ├── ApplicationController.php
│   │   │   ├── DocumentController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── ProgrammeController.php
│   │   │   ├── AdminController.php
│   │   │   └── HealthController.php
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php  # JWT verification
│   │   │   └── AdminMiddleware.php # Role check
│   │   ├── Services/
│   │   │   ├── EligibilityService.php  # O'Level screening engine
│   │   │   ├── NotificationService.php
│   │   │   └── AuditService.php
│   │   └── Routes/
│   │       └── api.php            # All API route definitions
│   ├── composer.json
│   └── .env
├── frontend/
│   ├── index.html                 # Landing page
│   ├── register.html              # Applicant registration
│   ├── login.html                 # Applicant login
│   ├── dashboard.html             # Applicant dashboard
│   ├── application.html           # 4-step application form
│   ├── documents.html             # Document upload
│   ├── status.html                # Status tracking
│   ├── admin/
│   │   ├── login.html             # Admin login
│   │   ├── dashboard.html         # Admin overview
│   │   ├── applications.html      # Applications list with filters
│   │   ├── review.html            # Application review & decision
│   │   └── reports.html           # Reports & analytics
│   ├── css/
│   │   └── style.css              # Complete design system
│   └── js/
│       └── api.js                 # API wrapper & auth state
├── database/
│   └── schema.sql                 # Full database schema with seeds
└── uploads/                       # Document uploads (gitignored)
```

## Quick Start

### Prerequisites
- PHP 8.0+ with PDO, MySQL, cURL, mbstring extensions
- MySQL 8.0+
- Composer

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/afit-vas.git
   cd afit-vas
   ```

2. **Install PHP dependencies**
   ```bash
   cd backend
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Create the database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

5. **Start the backend server**
   ```bash
   cd backend
   php -S localhost:8000 -t public
   ```

6. **Start the frontend server** (in another terminal)
   ```bash
   cd frontend
   php -S localhost:3000
   # or: python3 -m http.server 3000
   ```

7. **Open the application**
   - Applicant portal: http://localhost:3000
   - Admin portal: http://localhost:3000/admin/login.html

### Default Admin Credentials
- **Email:** admin@afit.edu.ng
- **Password:** Admin@2026

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/health` | System health check |
| GET | `/api/programmes` | List all programmes |
| POST | `/api/auth/register` | Register applicant |
| POST | `/api/auth/login` | Login |

### Authenticated (Applicant)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/profile` | Get profile |
| PUT | `/api/profile` | Update profile |
| GET | `/api/applications` | List my applications |
| POST | `/api/applications` | Create application |
| POST | `/api/applications/:id/submit` | Submit & screen |
| GET | `/api/documents?application_id=X` | List documents |
| POST | `/api/documents/upload` | Upload document |
| GET | `/api/notifications` | List notifications |

### Admin
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/stats` | Dashboard statistics |
| GET | `/api/admin/applications` | List all applications (filterable, paginated) |
| GET | `/api/admin/applications/:id` | View application detail |
| PUT | `/api/admin/applications/:id/status` | Update status |
| PUT | `/api/admin/documents/:id/verify` | Verify/reject document |
| GET | `/api/admin/reports` | Generate reports |
| GET | `/api/admin/export` | CSV export |
| GET | `/api/admin/audit-logs` | View audit trail |

## Database Schema

Seven tables: `users`, `programmes`, `applications`, `documents`, `audit_logs`, `notifications`, `status_history`.

See `database/schema.sql` for the full schema.

## Author

**Afolayan Prince-Joseph** (U22CS1012)
Department of Computer Science, Faculty of Computing
Air Force Institute of Technology, Kaduna

## License

This project is developed for academic purposes at AFIT, Kaduna.
