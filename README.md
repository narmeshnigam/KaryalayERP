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
│   ├── config.php              # Database and app configuration constants
│   └── db_connect.php          # Database connection handler
│
├── includes/
│   ├── header.php              # Common header and navigation
│   └── footer.php              # Common footer
│
├── public/
│   ├── login.php               # Login form and authentication
│   ├── index.php           # Protected dashboard page
│   └── logout.php              # Logout handler
│
├── scripts/
│   └── setup_db.php            # Database and table setup script
│
├── index.php                   # Main entry point with auto-setup
├── .env.example                # Environment variables example
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

3. **Configure Database (Optional)**
   - Edit `config/config.php` to change database credentials if needed
   - Default settings work with XAMPP out of the box:
     ```php
     DB_HOST: localhost
     DB_USER: root
     DB_PASS: (empty)
     DB_NAME: karyalay_db
     ```

4. **Access the Application**
   - Open your browser and visit:
   ```
   http://localhost/KaryalayERP/
   ```

5. **Automatic Setup**
   - On first visit, the system will automatically:
     - Create the database `karyalay_db`
     - Create the `users` table
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

### Database Settings
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'karyalay_db');
```

### Application Settings
```php
define('APP_NAME', 'Karyalay ERP');
define('APP_URL', 'http://localhost/KaryalayERP');
define('SESSION_LIFETIME', 3600); // 1 hour
```

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
- Verify database credentials in `config/config.php`
- Check MySQL error logs

### Setup Not Running
- Visit `http://localhost/KaryalayERP/scripts/setup_db.php` manually
- Check PHP error logs in XAMPP

### Session Issues
- Ensure session cookies are enabled in browser
- Check session save path permissions

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