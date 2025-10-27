<?php
/**
 * Configuration Validator
 * 
 * This script validates your environment configuration setup.
 * Run this to verify everything is working correctly.
 */

echo "<h1>KaryalayERP Environment Configuration Validator</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
    h2 { border-bottom: 2px solid #003581; padding-bottom: 10px; }
    pre { background: #f0f0f0; padding: 10px; border-left: 3px solid #003581; overflow-x: auto; }
</style>";

echo "<div class='section'>";
echo "<h2>1. File Structure Check</h2>";

// Check for .env file
if (file_exists(__DIR__ . '/.env')) {
    echo "<p class='success'>✓ .env file exists</p>";
    $env_readable = is_readable(__DIR__ . '/.env');
    if ($env_readable) {
        echo "<p class='success'>✓ .env file is readable</p>";
    } else {
        echo "<p class='error'>✗ .env file is not readable - check file permissions</p>";
    }
} else {
    echo "<p class='error'>✗ .env file NOT found</p>";
    echo "<p class='info'>Create it by copying .env.example:</p>";
    echo "<pre>cp .env.example .env</pre>";
}

// Check for .env.example file
if (file_exists(__DIR__ . '/.env.example')) {
    echo "<p class='success'>✓ .env.example file exists</p>";
} else {
    echo "<p class='warning'>⚠ .env.example file NOT found (template missing)</p>";
}

// Check for env_loader.php
if (file_exists(__DIR__ . '/config/env_loader.php')) {
    echo "<p class='success'>✓ config/env_loader.php exists</p>";
} else {
    echo "<p class='error'>✗ config/env_loader.php NOT found</p>";
}

// Check for config.php
if (file_exists(__DIR__ . '/config/config.php')) {
    echo "<p class='success'>✓ config/config.php exists</p>";
} else {
    echo "<p class='error'>✗ config/config.php NOT found</p>";
}

// Check for .gitignore
if (file_exists(__DIR__ . '/.gitignore')) {
    $gitignore_content = file_get_contents(__DIR__ . '/.gitignore');
    if (strpos($gitignore_content, '.env') !== false) {
        echo "<p class='success'>✓ .gitignore properly configured to protect .env</p>";
    } else {
        echo "<p class='warning'>⚠ .gitignore does not include .env protection</p>";
    }
} else {
    echo "<p class='warning'>⚠ .gitignore file NOT found</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>2. Environment Loader Test</h2>";

try {
    require_once __DIR__ . '/config/env_loader.php';
    echo "<p class='success'>✓ EnvLoader class loaded successfully</p>";
    
    // Test EnvLoader functionality
    if (class_exists('EnvLoader')) {
        echo "<p class='success'>✓ EnvLoader class is available</p>";
    } else {
        echo "<p class='error'>✗ EnvLoader class not found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to load EnvLoader: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>3. Configuration Loading Test</h2>";

try {
    require_once __DIR__ . '/config/config.php';
    echo "<p class='success'>✓ config.php loaded successfully</p>";
    
    // Check if constants are defined
    $required_constants = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'DB_CHARSET', 'APP_NAME', 'APP_URL'];
    
    foreach ($required_constants as $constant) {
        if (defined($constant)) {
            $value = constant($constant);
            $display_value = ($constant === 'DB_PASS') ? '***hidden***' : $value;
            echo "<p class='success'>✓ $constant = ";
            echo empty($value) ? "<span class='warning'>(empty)</span>" : "<span class='info'>" . htmlspecialchars($display_value) . "</span>";
            echo "</p>";
        } else {
            echo "<p class='error'>✗ $constant is NOT defined</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to load config.php: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>4. Database Connection Test</h2>";

if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS')) {
    // Test connection without database
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            echo "<p class='error'>✗ Cannot connect to database server: " . $conn->connect_error . "</p>";
            echo "<p class='info'>Please verify your database credentials in .env file</p>";
        } else {
            echo "<p class='success'>✓ Successfully connected to database server</p>";
            
            // Check if database exists
            if (defined('DB_NAME')) {
                $db_name = DB_NAME;
                $result = $conn->query("SHOW DATABASES LIKE '$db_name'");
                
                if ($result && $result->num_rows > 0) {
                    echo "<p class='success'>✓ Database '" . htmlspecialchars($db_name) . "' exists</p>";
                    
                    // Try to select the database
                    if ($conn->select_db($db_name)) {
                        echo "<p class='success'>✓ Successfully selected database</p>";
                        
                        // Check for users table
                        $result = $conn->query("SHOW TABLES LIKE 'users'");
                        if ($result && $result->num_rows > 0) {
                            echo "<p class='success'>✓ Users table exists</p>";
                        } else {
                            echo "<p class='warning'>⚠ Users table does not exist - run setup to create tables</p>";
                        }
                    } else {
                        echo "<p class='error'>✗ Cannot select database: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p class='warning'>⚠ Database '" . htmlspecialchars($db_name) . "' does not exist</p>";
                    echo "<p class='info'>Run the setup wizard to create it</p>";
                }
            }
            
            $conn->close();
        }
    } catch (Exception $e) {
        echo "<p class='error'>✗ Database connection error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='warning'>⚠ Database credentials not configured yet</p>";
    echo "<p class='info'>Please run the setup wizard or configure .env file manually</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>5. Recommendations</h2>";

$recommendations = [];

// Check if credentials are empty
if (defined('DB_HOST') && empty(DB_HOST)) {
    $recommendations[] = "Set DB_HOST in your .env file";
}
if (defined('DB_USER') && empty(DB_USER)) {
    $recommendations[] = "Set DB_USER in your .env file";
}
if (defined('DB_NAME') && empty(DB_NAME)) {
    $recommendations[] = "Set DB_NAME in your .env file";
}

// Check production settings
if (defined('ENVIRONMENT')) {
    $env = constant('ENVIRONMENT');
    if ($env === 'production') {
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE') === 'true') {
            $recommendations[] = "Set DEBUG_MODE=false in production environment";
        }
        if (defined('DB_PASS') && empty(DB_PASS)) {
            $recommendations[] = "Set a strong database password in production";
        }
    }
}

if (empty($recommendations)) {
    echo "<p class='success'>✓ No immediate issues found!</p>";
    echo "<p class='info'>Your environment configuration appears to be properly set up.</p>";
} else {
    echo "<p class='warning'>⚠ Recommendations:</p>";
    echo "<ul>";
    foreach ($recommendations as $rec) {
        echo "<li>$rec</li>";
    }
    echo "</ul>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li>If credentials are not set: Edit .env file or run the <a href='setup/'>setup wizard</a></li>";
echo "<li>If database doesn't exist: Run the <a href='setup/'>setup wizard</a></li>";
echo "<li>If everything is OK: Go to <a href='public/'>application home</a></li>";
echo "</ul>";
echo "</div>";
?>
