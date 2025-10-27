<?php
/**
 * Hardcoded Path Detector
 * 
 * This script scans PHP files for hardcoded localhost URLs and absolute paths
 * that should be replaced with relative paths or APP_URL constant.
 */

// Color output for CLI
function colorOutput($text, $color = 'white') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];
    
    return $colors[$color] . $text . $colors['reset'];
}

echo colorOutput("🔍 Scanning for Hardcoded Paths...\n\n", 'blue');

$project_root = __DIR__;
$issues = [];

// Patterns to search for
$patterns = [
    'localhost_url' => [
        'pattern' => '/localhost\/KaryalayERP/i',
        'description' => 'Hardcoded localhost URL',
        'suggestion' => 'Use APP_URL constant or relative paths'
    ],
    'absolute_path' => [
        'pattern' => '/["\']\/public\//i',
        'description' => 'Absolute path from root',
        'suggestion' => 'Use relative path or APP_URL'
    ],
    'hardcoded_http' => [
        'pattern' => '/http:\/\/localhost/i',
        'description' => 'Hardcoded http://localhost',
        'suggestion' => 'Use APP_URL constant'
    ]
];

// Scan PHP files
function scanDirectory($dir, $patterns, &$issues, $exclude = []) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $filepath = $file->getPathname();
            
            // Skip excluded directories
            $skip = false;
            foreach ($exclude as $excluded) {
                if (strpos($filepath, $excluded) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;
            
            $content = file_get_contents($filepath);
            $lines = explode("\n", $content);
            
            foreach ($patterns as $type => $config) {
                foreach ($lines as $line_num => $line) {
                    if (preg_match($config['pattern'], $line)) {
                        $issues[] = [
                            'file' => str_replace($GLOBALS['project_root'] . DIRECTORY_SEPARATOR, '', $filepath),
                            'line' => $line_num + 1,
                            'type' => $type,
                            'description' => $config['description'],
                            'suggestion' => $config['suggestion'],
                            'code' => trim($line)
                        ];
                    }
                }
            }
        }
    }
}

// Directories to scan
$scan_dirs = [
    $project_root . '/public',
    $project_root . '/includes',
    $project_root . '/config',
    $project_root . '/setup'
];

// Directories to exclude
$exclude_dirs = [
    'vendor',
    'node_modules',
    '.git'
];

foreach ($scan_dirs as $dir) {
    if (is_dir($dir)) {
        scanDirectory($dir, $patterns, $issues, $exclude_dirs);
    }
}

// Display results
if (empty($issues)) {
    echo colorOutput("✅ No hardcoded paths found! Great job!\n", 'green');
} else {
    echo colorOutput("Found " . count($issues) . " potential issues:\n\n", 'yellow');
    
    $grouped = [];
    foreach ($issues as $issue) {
        $grouped[$issue['file']][] = $issue;
    }
    
    foreach ($grouped as $file => $file_issues) {
        echo colorOutput("📄 $file\n", 'blue');
        
        foreach ($file_issues as $issue) {
            echo colorOutput("   Line {$issue['line']}: ", 'yellow');
            echo colorOutput($issue['description'] . "\n", 'red');
            echo "   Code: " . htmlspecialchars(substr($issue['code'], 0, 100)) . "\n";
            echo colorOutput("   → Suggestion: {$issue['suggestion']}\n\n", 'green');
        }
    }
    
    echo colorOutput("\n📊 Summary by Type:\n", 'blue');
    $type_counts = [];
    foreach ($issues as $issue) {
        $type_counts[$issue['description']] = ($type_counts[$issue['description']] ?? 0) + 1;
    }
    
    foreach ($type_counts as $type => $count) {
        echo "   • $type: " . colorOutput("$count occurrences", 'yellow') . "\n";
    }
}

echo "\n" . colorOutput("💡 Tips:\n", 'blue');
echo "   • Use relative paths for same-level navigation (e.g., 'login.php', '../login.php')\n";
echo "   • Use APP_URL constant for cross-module links\n";
echo "   • Use url_helper.php functions for best practices\n";
echo "   • Review URL_PATH_GUIDE.md for detailed examples\n\n";

echo colorOutput("✅ Scan complete!\n", 'green');
?>
