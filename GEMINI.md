# Project Overview
This is a Laravel 12 backend API designed specifically to serve a React Native mobile frontend for a library management system. The project follows a strictly decoupled architecture, separating business logic into a dedicated Service layer.

## Core Technologies
- **Framework:** Laravel 12.x
- **Authentication:** Laravel Passport (OAuth2)
- **Database:** MySQL (Database name: `libsys-mobile`)
- **Logging:** Custom `AuditTrailService` for system-wide activity tracking (`audit_logs` table)
- **Storage:** Laravel Storage system (`storage/app/public`) linked via `php artisan storage:link`

## Architecture & Conventions
The project adheres to a **Controller -> Service -> Model** architectural pattern:
- **Controllers:** Handle HTTP requests, input validation, and return JSON responses.
- **Services:** Contain all business logic, database transactions, and file handling.
- **Models:** Define database schemas, relationships, and custom authentication lookups (e.g., `findForPassport`).

### Key Conventions
- **Authentication:** Users log in via `identifier` (which can be a `username` or `student_number`).
- **Profile Management:** Role-specific profile services (`Student`, `Faculty`, `Staff`) manage data and file uploads.
- **Profile Locking:** Student profiles are locked (`profile_updated = 1`) after the first successful update.
- **File Uploads:** Managed via the `public` disk. Paths are stored in the database as `uploads/profile_images/...` or `uploads/reg_forms/...`.
- **Audit Logs:** High-stakes actions like Login, Logout, and Password changes are automatically logged via `AuditTrailService`.

## Building and Running
### Installation
```bash
composer install
php artisan key:generate
php artisan migrate
php artisan passport:install
php artisan storage:link
```

### Running Locally
For proper handling of internal Passport requests during development, the server should be run with multiple workers:
```bash
php -S 127.0.0.1:8000 -t public -d PHP_CLI_SERVER_WORKERS=4
```

## API Endpoints
### Authentication
- `POST /api/login`: Returns access and refresh tokens.
- `POST /api/refresh`: Issues new tokens using a valid refresh token.
- `POST /api/logout`: Revokes the current access token.
- `POST /api/changePassword`: Updates user credentials.
- `GET /api/me`: Returns authenticated user summary.

### Profile & Dashboard
- `GET /api/profile`: Retrieves role-specific profile data.
- `POST /api/profile/update`: Partial or full profile update (use `multipart/form-data`).
- `GET /api/dashboard`: Summary of borrowed books, overdue counts, and attendance.

### Library Features
- `GET /api/books`: Paginated catalog with search, status, and sort filters.
- `GET /api/cart`: Current items in the centralized cart.
- `POST /api/cart/add`: Adds a book to the cart.
- `DELETE /api/cart/{id}`: Removes an item from the cart.
- `POST /api/cart/checkout`: Generates a pending borrow transaction and clears the cart.
- `GET /api/borrowingHistory`: Complete history of the user's borrow transactions.
- `GET /api/attendance/history`: History of user library visits.
