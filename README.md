# ThemeStore Demo Access System

## Project Structure

```
themestore-demo-access-system/
├── config/
│   └── config.php              # Database and configuration
├── includes/
│   └── token-validator.php     # Token validation functions
├── public/
│   ├── lead-form.php           # Lead registration form
│   ├── demo-flow.php           # User journey flow
│   ├── watch.php               # Video viewing page with chatbox
│   └── stream.php              # Video streaming (if needed)
├── admin/
│   ├── admin-leads.php         # Leads listing page
│   └── admin-lead-detail.php   # Lead details with queries & follow-ups
├── api/
│   ├── send-otp.php           # Send OTP endpoint
│   ├── verify-otp.php         # Verify OTP endpoint
│   ├── generate-demo-link.php  # Generate demo link endpoint
│   ├── save-query.php         # Save client query endpoint
│   ├── respond-query.php      # Respond to query endpoint
│   ├── add-followup.php       # Add/edit follow-up endpoint
│   ├── track-video-activity.php # Video activity tracking
│   └── track-view.php         # Track demo link view
├── database/
│   ├── schema.sql             # Database schema
│   ├── expire-demo-links.php  # Cron script to expire links
│   ├── fix-demo-expiry.php    # Fix expiry times script
│   ├── fix-demo-expiry-web.php # Web-based fix script
│   └── update-leads-table.php # Migration scripts
├── assets/
│   └── video-tracking.js      # Video tracking JavaScript
├── vendor/
│   └── phpmailer/             # PHPMailer library
├── index.php                  # Entry point (redirects to lead form)
└── SYSTEM_RULES.md            # System requirements and rules
```

## Access URLs

- **Lead Form**: `http://localhost/themestore-demo-access-system/public/lead-form.php`
- **Admin Leads**: `http://localhost/themestore-demo-access-system/admin/admin-leads.php`
- **Watch Video**: `http://localhost/themestore-demo-access-system/public/watch.php?token=...`

## Setup

1. Import `database/schema.sql` into your MySQL database
2. Update `config/config.php` with your database and email settings
3. Access the lead form via `index.php` or directly via `public/lead-form.php`

## Features

- Lead registration with OTP verification
- Demo link generation (60 minutes validity)
- Video viewing with Vimeo integration
- Client query chatbox during video
- Follow-up tracking and management
- Admin dashboard for leads, queries, and follow-ups

