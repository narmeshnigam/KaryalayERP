# Karyalay ERP - Core PHP Project

A complete **Core PHP** project with login system, database auto-setup, and session-based authentication.

## 🚀 Features

- ✅ **Pure Core PHP** - No frameworks, lightweight and fast
- ✅ **Secure Authentication** - Password hashing with `password_hash()` and `password_verify()`
- ✅ **Auto Database Setup** - Automatically creates database and tables on first run
- ✅ **Session Management** - Secure session handling for login persistence
- ✅ **SQL Injection Protection** - Uses prepared statements (MySQLi)
- ✅ **Clean UI** - Responsive design with modern CSS
- ✅ **Modular Structure** - Organized folder structure for maintainability
- ✅ **Well Commented Code** - Easy to understand and extend
- ✅ **Salary Viewer Module** - Admin-managed payroll uploads with employee slip access

## 📁 Project Structure

```
KaryalayERP/
│
├── config/
│   ├── env_loader.php          # Environment variable loader
│   ├── config.php              # Configuration loader (uses .env)
│   ├── db_connect.php          # Database connection handler
│   └── setup_helper.php        # Setup utilities
│
├── includes/
│   ├── header.php              # Common header and navigation
│   ├── sidebar.php             # Application sidebar navigation
│   └── footer.php              # Common footer
│
├── public/
│   ├── login.php               # Login form and authentication
│   ├── index.php               # Protected dashboard page
│   ├── logout.php              # Logout handler
│   └── [modules]/              # Various application modules
│
├── scripts/
│   └── setup_*.php             # Database table setup scripts
│
├── setup/
│   └── *.php                   # Installation wizard files
│
├── .env                        # Environment configuration (DO NOT COMMIT)
├── .env.example                # Environment variables template
├── .gitignore                  # Git ignore rules
├── validate_config.php         # Configuration validator tool
├── migrate_config.php          # Migration helper tool
├── ENV_CONFIGURATION_GUIDE.md  # Environment configuration guide
├── index.php                   # Main entry point
└── README.md                   # This file
```

## 🛠️ Installation & Setup

### Prerequisites
- **XAMPP** (or any PHP development environment)
- **PHP 7.4+**
- **MySQL 5.7+**

### Step-by-Step Installation

1. **Clone/Download the project**
   ```bash
   # Place the project in your XAMPP htdocs folder
   C:\xampp\htdocs\KaryalayERP\
   ```

2. **Start XAMPP**
   - Start **Apache** server
   - Start **MySQL** server

3. **Configure Database**
   
   **Option A: Using Setup Wizard (Recommended)**
   - Navigate to: `http://localhost/KaryalayERP/setup/`
   - Follow the guided setup wizard
   - Enter your database credentials
   - Setup wizard will automatically create `.env` file
   
   **Option B: Manual Configuration**
   - Copy `.env.example` to `.env`
   - Edit `.env` with your database credentials:
     ```bash
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=karyalay_db
     ```
   - Save the file

4. **Access the Application**
   - Open your browser and visit:
   ```
   http://localhost/KaryalayERP/
   ```

5. **Verify Setup**
   - Run the configuration validator:
   ```
   http://localhost/KaryalayERP/validate_config.php
   ```
   - Or use the migration helper if upgrading:
   ```
   http://localhost/KaryalayERP/migrate_config.php
   ```

6. **Complete Setup**
   - On first visit, the setup wizard will guide you through:
     - Database creation
     - Table creation
     - Admin user creation
     - Initial branding configuration
     - Insert default admin user
   - You'll be redirected to the setup page showing progress

6. **Login**
   - After setup, you'll be redirected to the login page
   - Use default credentials:
     ```
     Username: admin
     Password: admin123
     ```

## 🔐 Default Credentials

**⚠️ IMPORTANT: Change these credentials after first login!**

- **Username:** `admin`
- **Password:** `admin123`

## 💻 Usage

### Login Flow
1. Visit `http://localhost/KaryalayERP/`
2. Enter username and password
3. Click "Login"
4. Redirected to dashboard on success

### Logout
- Click the "Logout" button in the navigation
- Session is destroyed and you're redirected to login

### Protected Pages
- Dashboard and other pages check for active session
- Non-logged-in users are redirected to login page

## 🗄️ Database Schema

### Users Table
```sql
CREATE TABLE `users` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('admin', 'user', 'manager') DEFAULT 'user',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔒 Security Features

1. **Password Hashing**
   - Passwords stored using `password_hash()` with `PASSWORD_DEFAULT`
   - Verification with `password_verify()`

2. **SQL Injection Prevention**
   - All queries use prepared statements
   - Input sanitization with `trim()` and validation

3. **Session Security**
   - Session regeneration on login (`session_regenerate_id()`)
   - Proper session destruction on logout
   - Session-based access control

4. **XSS Prevention**
   - Output escaping with `htmlspecialchars()`

## 📝 Configuration

### Environment-Based Configuration (New!)

KaryalayERP now uses **environment variables** for configuration, providing better security and flexibility.

**Key Benefits:**
- ✅ Credentials stored in `.env` file (not committed to version control)
- ✅ Easy to configure for different environments
- ✅ No hardcoded credentials in code
- ✅ Simple deployment and updates

**Configuration Files:**
- `.env` - Your actual configuration (DO NOT commit to Git)
- `.env.example` - Template with all available options
- `config/config.php` - Loads configuration from `.env`
- `config/env_loader.php` - Environment variable parser

### Database Settings
Edit `.env` file:
```bash
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=karyalay_db
DB_CHARSET=utf8mb4
```

### Application Settings
Edit `.env` file:
```bash
APP_NAME=Karyalay ERP
APP_URL=http://localhost/KaryalayERP
SESSION_NAME=karyalay_session
SESSION_LIFETIME=3600
TIMEZONE=Asia/Kolkata
```

### Environment Modes
```bash
# Development (shows errors)
ENVIRONMENT=development
DEBUG_MODE=true

# Production (hides errors)
ENVIRONMENT=production
DEBUG_MODE=false
```

**📖 For detailed configuration guide, see:** [ENV_CONFIGURATION_GUIDE.md](ENV_CONFIGURATION_GUIDE.md)

## 🎨 Customization

### Adding New Pages
1. Create new PHP file in `public/` folder
2. Include header: `require_once '../includes/header.php';`
3. Add session check for protected pages
4. Include footer: `require_once '../includes/footer.php';`

### Adding New Users
You can add users through database or create a registration page:
```php
$password = password_hash('user_password', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, full_name, role) 
        VALUES (?, ?, ?, ?)";
```

## 🐛 Troubleshooting

### Database Connection Error
- Check if MySQL is running in XAMPP
- Verify database credentials in `.env` file (not config.php)
- Run configuration validator: `http://localhost/KaryalayERP/validate_config.php`
- Check MySQL error logs

### .env File Not Found
- Copy `.env.example` to `.env`
- Or run the migration helper: `http://localhost/KaryalayERP/migrate_config.php`
- Or use the setup wizard: `http://localhost/KaryalayERP/setup/`

### Configuration Not Loading
- Ensure `.env` file is in the project root (not in subdirectories)
- Check file permissions - PHP must be able to read `.env`
- Verify `.env` syntax (key=value format, no spaces around =)
- Restart Apache after changing `.env`

### Setup Not Running
- Visit `http://localhost/KaryalayERP/setup/` for guided setup
- Or manually configure `.env` file
- Check PHP error logs in XAMPP

### Session Issues
- Ensure session cookies are enabled in browser
- Check session save path permissions
- Verify SESSION_NAME and SESSION_LIFETIME in `.env`

### Migration from Old Version
- Run: `http://localhost/KaryalayERP/migrate_config.php`
- Follow the migration steps
- Old hardcoded credentials will be detected and suggested for `.env`

## 📚 Code Documentation

All PHP files include:
- File-level documentation explaining purpose
- Function-level comments
- Inline comments for complex logic
- Variable naming follows `snake_case` convention
- Functions use `camelCase` convention

## 🔄 Future Enhancements

Potential features to add:
- User registration system
- Password reset functionality
- Email verification
- Role-based access control (RBAC)
- User management (CRUD)
- Profile editing
- Activity logging
- Password strength validator
- Remember me functionality
- Multi-factor authentication

## 📄 License

This project is open-source and available for learning and commercial use.

## 👨‍💻 Developer

Built with ❤️ using Core PHP

---

## 🆘 Support

For issues or questions:
1. Check this README thoroughly
2. Review code comments
3. Check browser console for JavaScript errors
4. Check PHP error logs in XAMPP

---

**Happy Coding! 🎉**