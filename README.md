# Veterinary Clinic Management System - Backend

A Laravel 10 REST API backend for a comprehensive veterinary clinic management system. Handles patient records, appointments, medicines, owners, and veterinarians.

## Overview

This backend provides RESTful API endpoints for managing all aspects of a veterinary clinic:
- **Patients**: Register and manage pet records with auto-generated passbook numbers
- **Owners**: Maintain owner/client information
- **Veterinarians**: Manage veterinary staff records
- **Medicines**: Track medicine inventory and brands
- **Appointments**: Schedule and manage appointments with medicine prescriptions

## Tech Stack

- **Framework**: Laravel 10
- **Database**: MySQL
- **ORM**: Eloquent
- **API**: RESTful with JSON responses

## Requirements

- PHP 8.1+
- Composer
- MySQL 5.7+

## Quick Start

1. **Clone & install**
   ```bash
   git clone <your-repo-url>
   cd backend-laravel
   composer install
   cp .env.example .env
   ```

2. **Configure environment**
   ```bash
   # Update .env with your database credentials
   DB_DATABASE=vet_clinic
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Setup**
   ```bash
   php artisan key:generate
   php artisan migrate
   php artisan serve
   ```

Server runs on `http://localhost:8000`

## Database Schema

Key tables:
- **owners** - Pet owners with contact info
- **patients** - Pet records with auto-generated passbook numbers (PB2025-XXXXXX)
- **veterinarians** - Staff records
- **medicines** - Medicine catalog with brands and pricing
- **appointments** - Appointment bookings with prescriptions

## API Endpoints

All responses use camelCase field names.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/patients` | List patients |
| POST | `/api/patients` | Create patient |
| GET | `/api/patients/{id}` | Get patient |
| PUT | `/api/patients/{id}` | Update patient |
| DELETE | `/api/patients/{id}` | Delete patient |
| GET | `/api/owners` | List owners |
| POST | `/api/owners` | Create owner |
| GET | `/api/veterinarians` | List vets |
| POST | `/api/veterinarians` | Create vet |
| GET | `/api/medicines` | List medicines |
| POST | `/api/medicines` | Create medicine |
| GET | `/api/appointments` | List appointments |
| POST | `/api/appointments` | Create appointment |
| PUT | `/api/appointments/{id}` | Update appointment |

## Features

### Auto-Generated Passbook Numbers
Each patient receives a unique passbook number: `PB{YEAR}-{RANDOM_6_DIGITS}`

### API Response Format
```json
{
  "id": 1,
  "name": "Buddy",
  "species": "Canine",
  "breed": "Labrador",
  "passbookNumber": "PB2025-789456",
  "ownerId": 5,
  "owner": { ... },
  "createdAt": "2025-12-05T10:30:00Z",
  "updatedAt": "2025-12-05T10:30:00Z"
}
```

## Frontend Integration

Configure frontend proxy to: `http://localhost:8000/api`

## Project Structure

```
app/
├── Http/Controllers/Api/
│   ├── PatientController.php
│   ├── OwnerController.php
│   ├── VeterinarianController.php
│   ├── MedicineController.php
│   └── AppointmentController.php
└── Models/
    ├── Patient.php
    ├── Owner.php
    ├── Veterinarian.php
    ├── Medicine.php
    ├── Appointment.php
    └── AppointmentMedicine.php
```

## Development

```bash
# Run tests
php artisan test

# Database seeding
php artisan db:seed

# Interactive console
php artisan tinker
```

## Troubleshooting

**Database connection error**: Verify MySQL is running and .env credentials are correct

**Port 8000 in use**: `php artisan serve --port=8001`

## License

MIT License
