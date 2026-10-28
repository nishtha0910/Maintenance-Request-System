# Vercel and Aiven deployment

The existing XAMPP pages continue to work locally. Vercel routes requests through
api/vercel.php, which allows only known public pages. Private PHP, SQL, dependency,
and configuration files cannot be requested through this entry point.

## Database
Import the application schema into Aiven defaultdb, then run database/app_sessions.sql.
Sessions expire after 30 minutes of inactivity. The database session handler uses
per-session advisory locks and a separate connection from application transactions.

## Vercel project
Use Other, root ./, Node.js 22.x, and leave build/output overrides unset.
vercel.json builds one PHP function with the community vercel-php@0.9.0 runtime
(PHP 8.5), plus static assets. Composer dependencies are installed by that runtime.
Local validation runs on PHP 8.2; verify the deployed PHP 8.5 flows before hand-in.

Set these private environment variables in Vercel:
- APP_ENV: production
- DB_HOST: the Aiven hostname
- DB_PORT: 23733
- DB_NAME: defaultdb
- DB_USER: avnadmin
- DB_PASSWORD: the Aiven password
- APP_URL: the final HTTPS site origin (no trailing slash)
- SMTP_HOST: smtp.gmail.com (or your STARTTLS provider)
- SMTP_USER: the sending email address
- SMTP_PASSWORD: its app password

The checked-in config/aiven-ca.pem is a public CA certificate, not a private key.
Do not commit passwords or config/mail.local.php. .vercelignore excludes local
secrets, tests and SQL exports. Preview deployments must not share a production
database unless intentionally authorized.

## After deployment
Check root redirect and CSS; register and log in; verify sessions survive several
requests; create a maintenance request and check the redirect/history; log out.
Create an administrator by changing only the intended registered test account's
role to lowercase admin in the database. Check admin status/priority updates and
tenant API denial. Test password-reset email with the final APP_URL.
Confirm /config/database.php, /composer.json and /tests/ return 404.
No local accounts were copied: the hosted database starts without users.
