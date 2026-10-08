# Application Environment
APP_ENV=development
APP_DEBUG=true
APP_NAME=OrdinaTrack
APP_URL=http://localhost

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=ordinatrack
DB_USER=root
DB_PASS=root
DB_CHARSET=utf8mb4

# JWT Configuration
JWT_SECRET=N/RcQxqFV8dWH0OI0LuolC6KhTGxcKC5y0s3OkFnynY=
JWT_ALGORITHM=HS256
JWT_EXPIRATION=86400
JWT_REFRESH_EXPIRATION=604800

# Email Configuration (for password reset, notifications)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS=noreply@ordinatrack.com
MAIL_FROM_NAME=OrdinaTrack

# Session Configuration
SESSION_TIMEOUT=3600
SESSION_SECURE=1
SESSION_HTTPONLY=1
SESSION_SAMESITE=Strict

# Security
CORS_ALLOWED_ORIGINS=http://localhost,http://localhost:3000
BCRYPT_COST=12

# Logging
LOG_LEVEL=info
LOG_PATH=logs/

# API Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600