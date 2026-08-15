<?php
// Local environment overrides for development (XAMPP)
// Copy/rename this file as needed and DO NOT commit to version control.
// These calls set environment variables read by config.php (getenv()).

putenv('DB_DRIVER=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_NAME=srms');
putenv('DB_CHARSET=utf8mb4');
putenv('APP_NAME=ELIMU HUB');
putenv('AFRICASTALKING_USERNAME=kyandulu_primary_school');
putenv('SUPER_ADMIN_EMAIL=super@admin');
putenv('SUPER_ADMIN_PASSWORD=2006@shawn_Mutiso');

// If you need to enable debug behaviours locally, you can set more vars here.
// e.g. putenv('APP_SECRET=dev');
// If you want a fixed canonical URL, uncomment and update this:
// putenv('APP_URL=https://YOUR-LAN-IP/srms/script');
