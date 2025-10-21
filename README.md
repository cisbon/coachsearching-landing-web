# CoachSearching - Early Bird Coach Landing Page

A modern, professional landing page for recruiting early-bird coaches to the CoachSearching platform with lifetime free access.

## Features

- **Modern, Responsive Design**: Professional UI optimized for both desktop and mobile devices
- **Supabase Integration**: Complete backend authentication and database setup
- **Invite Code Validation**: Quality control through required invitation codes
- **Manual Approval Process**: All signups are reviewed before granting access
- **No External Dependencies**: Self-contained HTML file with embedded CSS and vanilla JavaScript

## Quick Start

### 1. Set Up Supabase

1. Create a free account at [https://app.supabase.com](https://app.supabase.com)
2. Create a new project
3. Go to the **SQL Editor** in your Supabase dashboard
4. Copy the entire contents of `supabase-setup.sql`
5. Paste it into the SQL Editor and click **Run**
6. Wait for the confirmation message

### 2. Configure the Landing Page

1. Open `index.html` in a text editor
2. Find the JavaScript section near the bottom of the file
3. Locate these two configuration variables:

```javascript
const SUPABASE_URL = 'YOUR_SUPABASE_PROJECT_URL';
const SUPABASE_ANON_KEY = 'YOUR_SUPABASE_ANON_KEY';
```

4. Replace the values with your actual Supabase credentials:
   - Go to **Settings > API** in your Supabase dashboard
   - Copy the **Project URL** and paste it as `SUPABASE_URL`
   - Copy the **anon/public key** and paste it as `SUPABASE_ANON_KEY`

### 3. Deploy

You can deploy this landing page to any static hosting service:

- **Netlify**: Drag and drop the `index.html` file
- **Vercel**: Upload via CLI or GitHub integration
- **GitHub Pages**: Push to a repository and enable Pages
- **Any Web Server**: Upload the `index.html` file to your server

## Database Schema

### Tables

#### `invite_codes`
Stores valid invitation codes that coaches must use to sign up.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| code | TEXT | Unique invitation code |
| created_by | TEXT | Name of the coach trainer who created it |
| is_active | BOOLEAN | Whether the code can still be used |
| max_uses | INTEGER | Maximum number of times code can be used |
| current_uses | INTEGER | How many times it has been used |
| expires_at | TIMESTAMP | Optional expiration date |

#### `coaches`
Stores coach registration data pending approval.

| Column | Type | Description |
|--------|------|-------------|
| id | UUID | Primary key |
| user_id | UUID | Reference to auth.users |
| name | TEXT | Coach's full name |
| email | TEXT | Coach's email (unique) |
| social_link | TEXT | LinkedIn or professional profile URL |
| invite_code | TEXT | The code they used to sign up |
| approved | BOOLEAN | Whether admin approved (default: false) |
| approved_at | TIMESTAMP | When they were approved |
| approved_by | UUID | Admin who approved them |

## Managing Coaches

### Approving New Coaches

1. Go to your Supabase dashboard
2. Navigate to **Table Editor > coaches**
3. Find coaches where `approved = false`
4. Click on the row to edit
5. Change `approved` to `true`
6. Save the changes

The `approved_at` timestamp will be set automatically by a database trigger.

### Adding New Invite Codes

Run this SQL in the Supabase SQL Editor:

```sql
INSERT INTO public.invite_codes (code, created_by, is_active, max_uses)
VALUES ('YOUR-CODE-HERE', 'Trainer Name', true, 10);
```

### Viewing Pending Coaches

Use the `pending_coaches` view:

```sql
SELECT * FROM public.pending_coaches;
```

## Customization

### Colors

The primary brand color is **petrol (#006266)**. To change colors, edit the CSS variables in the `<style>` section:

```css
:root {
    --primary-petrol: #006266;
    --petrol-light: #008B8F;
    --petrol-dark: #004A4D;
    /* ... more colors */
}
```

### Copy/Text

All text content is in the HTML body. Search for specific headings or paragraphs and update as needed.

### Form Fields

To add or remove form fields:

1. Add/remove the HTML input in the form
2. Update the JavaScript form handler to capture the new field values
3. Update the `coaches` table schema in Supabase if adding new fields

## Security Features

- **Row Level Security (RLS)**: Enabled on all tables
- **Read-Only Invite Codes**: Users can only check validity, not modify
- **Self-Approval Prevention**: Users cannot approve their own accounts
- **Input Validation**: Email format, URL format, and required fields are validated
- **Invite Code Expiration**: Optional expiration dates for codes

## Optional Enhancements

### Email Notifications

Set up Supabase Edge Functions to send email notifications when:
- A new coach signs up (notify admins)
- A coach is approved (notify the coach)

### Admin Dashboard

Build a custom admin panel to:
- View pending coaches in a nice UI
- Approve/reject coaches with one click
- Generate new invite codes
- View analytics (signups over time, etc.)

### Rate Limiting

Consider adding rate limiting to prevent spam signups:
- Use Supabase Edge Functions with middleware
- Or implement at the hosting level (Netlify/Vercel functions)

## Files Included

- `index.html` - Complete landing page (HTML + CSS + JS)
- `supabase-setup.sql` - Database setup script
- `README.md` - This documentation file

## Support

For issues or questions:
- Check the Supabase documentation: [https://supabase.com/docs](https://supabase.com/docs)
- Review the commented code in `index.html` and `supabase-setup.sql`

## License

All rights reserved - CoachSearching.com
