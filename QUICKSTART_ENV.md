# 🚀 Quick Start - Environment Configuration

## For New Installations

### Option 1: Using Setup Wizard (Recommended)
1. Navigate to: `http://localhost/KaryalayERP/setup/`
2. Enter your database credentials
3. Click "Test Connection" and "Save"
4. Follow remaining setup steps
5. ✅ Done! Your `.env` file is automatically created

### Option 2: Manual Configuration
1. Copy `.env.example` to `.env`:
   ```powershell
   Copy-Item .env.example .env
   ```
2. Edit `.env` with your credentials:
   ```bash
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=your_password
   DB_NAME=karyalay_db
   ```
3. Save and access: `http://localhost/KaryalayERP/`

## For Existing Installations (Upgrading)

### Step 1: Run Migration Helper
```
http://localhost/KaryalayERP/migrate_config.php
```
- View detected credentials
- Copy suggested `.env` content
- Save to `.env` file

### Step 2: Validate Configuration
```
http://localhost/KaryalayERP/validate_config.php
```
- Check all green checkmarks
- Verify database connection
- Fix any warnings

### Step 3: Done!
Your application now uses environment-based configuration.

## Testing Your Setup

### ✅ Checklist
- [ ] `.env` file exists in project root
- [ ] `.env` contains your database credentials
- [ ] `validate_config.php` shows all green checkmarks
- [ ] Application loads without database errors
- [ ] Setup wizard (if needed) completes successfully

### 🔍 Validation Command
Open in browser:
```
http://localhost/KaryalayERP/validate_config.php
```

## Common Commands (PowerShell)

```powershell
# Create .env from template
Copy-Item .env.example .env

# Edit .env file
notepad .env

# Check if .env exists
Test-Path .env

# View .env content (be careful - contains sensitive data!)
Get-Content .env
```

## Quick Reference

| File | Purpose | Commit to Git? |
|------|---------|----------------|
| `.env` | Your actual credentials | ❌ NO |
| `.env.example` | Template file | ✅ YES |
| `config/config.php` | Configuration loader | ✅ YES |
| `config/env_loader.php` | Environment parser | ✅ YES |

## Need Help?

1. **Configuration not loading?** → Run `validate_config.php`
2. **Database connection error?** → Check `.env` credentials
3. **File not found?** → Run `migrate_config.php`
4. **Need details?** → Read `ENV_CONFIGURATION_GUIDE.md`

## Security Reminder

⚠️ **NEVER commit `.env` file to version control!**

The `.gitignore` file already protects it, but always verify:
```powershell
# Check git status - .env should NOT appear
git status
```

If `.env` appears in git status, it's protected by `.gitignore` (which is correct).
If you accidentally staged it, unstage with:
```powershell
git reset .env
```

## Summary

**What changed:**
- ✅ No more hardcoded credentials in code
- ✅ Credentials now in `.env` file (protected)
- ✅ Easy to configure different environments
- ✅ Standard industry practice

**What stayed the same:**
- ✅ Application functionality unchanged
- ✅ Database structure unchanged
- ✅ Setup wizard still works
- ✅ All modules work as before

You're all set! 🎉
