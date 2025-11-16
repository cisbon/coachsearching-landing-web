# CoachSearching.com

Professional coaching platform connecting clients with certified coaches worldwide.

## Architecture Overview

### Frontend
- **Hosting**: GitHub Pages (static HTML/CSS/JavaScript)
- **URL**: https://coachsearching.com
- **Technology**: Pure HTML5, CSS3, JavaScript (ES6+)
- **SEO Optimized**: Comprehensive meta tags, schema.org markup, semantic HTML

### Backend
- **Hosting**: https://clouedo.com/coachsearching/api
- **Technology**: PHP 7.4+ with MySQL
- **Architecture**: REST API with JWT authentication
- **Database**: MySQL 5.7+

## Quick Start

### 1. Database Setup

Import the MySQL schema:
```bash
mysql -u your_user -p your_database < mysql-schema.sql
```

### 2. Backend Configuration

1. Navigate to API directory and copy environment file:
```bash
cd api
cp .env.example .env
```

2. Edit `.env` with your credentials:
   - Database settings
   - JWT secret key
   - SMTP configuration
   - Stripe API keys
   - CORS origins

3. Set proper permissions:
```bash
chmod 755 api/
chmod 644 api/*.php
chmod 600 api/.env
```

### 3. Deploy

- **Frontend**: Push to GitHub Pages
- **Backend**: Upload `api/` directory to https://clouedo.com/coachsearching/api
- **Database**: Import `mysql-schema.sql` to your MySQL server

## User Roles

1. **Guest** - Browse coaches, take questionnaire
2. **User** - Book sessions, write reviews, follow coaches
3. **Business** - Team management, bulk bookings
4. **Coach** - Profile, sessions, articles, messaging
5. **Admin** - Full control, assign delegates
6. **Admin Delegate** - Dashboard access (no delegate management)

## API Documentation

### Base URL
`https://clouedo.com/coachsearching/api`

### Authentication
All authenticated endpoints require JWT token in Authorization header:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Key Endpoints

**Auth**
- `POST /auth/register` - Register user
- `POST /auth/login` - Login
- `GET /auth/me` - Get current user

**Coaches**
- `GET /coaches` - List/search coaches
- `GET /coaches/profile?id={id}` - Get coach profile

**Bookings**
- `POST /bookings/create` - Create booking
- `POST /bookings/confirm` - Confirm booking (coach)
- `POST /bookings/cancel` - Cancel booking

**Questionnaire**
- `GET /questionnaire` - Get questions
- `POST /questionnaire/submit` - Submit and get matches

See full API documentation in the code comments.

## Default Admin Account

**Email**: admin@coachsearching.com  
**Password**: password

⚠️ **CHANGE THIS IMMEDIATELY IN PRODUCTION!**

## Security

- JWT authentication
- Bcrypt password hashing
- SQL injection protection
- XSS prevention
- CORS configuration
- Protected .env files
- File upload validation

## SEO Features

- Semantic HTML5
- Rich meta tags
- Open Graph support
- Schema.org markup
- Canonical URLs
- Mobile responsive
- Fast loading

## Next Steps

1. Create additional frontend pages (coaches.html, questionnaire.html, etc.)
2. Populate database with sample data
3. Configure email notifications
4. Set up Stripe payments
5. Deploy to production
6. Change default passwords

## Support

For questions or issues, contact the development team.

## License

Proprietary - All rights reserved
