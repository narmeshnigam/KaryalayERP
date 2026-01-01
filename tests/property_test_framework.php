<?php
/**
 * Simple Property-Based Testing Framework
 * Provides basic PBT functionality for testing universal properties
 */

class PropertyTestFramework {
    private int $iterations;
    private array $results = [];
    
    public function __construct(int $iterations = 100) {
        $this->iterations = $iterations;
    }
    
    /**
     * Run a property test
     * 
     * @param string $name Test name
     * @param callable $generator Function that generates test data
     * @param callable $property Function that tests the property (returns bool)
     * @return array Test results
     */
    public function test(string $name, callable $generator, callable $property): array {
        $failures = [];
        $passed = 0;
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $testData = $generator();
                $result = $property($testData);
                
                if ($result === true) {
                    $passed++;
                } else {
                    $failures[] = [
                        'iteration' => $i + 1,
                        'input' => $testData,
                        'reason' => 'Property returned false'
                    ];
                }
            } catch (Exception $e) {
                $failures[] = [
                    'iteration' => $i + 1,
                    'input' => $testData ?? null,
                    'reason' => $e->getMessage()
                ];
            }
        }
        
        $result = [
            'name' => $name,
            'iterations' => $this->iterations,
            'passed' => $passed,
            'failed' => count($failures),
            'success' => count($failures) === 0,
            'failures' => $failures
        ];
        
        $this->results[] = $result;
        return $result;
    }
    
    /**
     * Get all test results
     */
    public function getResults(): array {
        return $this->results;
    }
    
    /**
     * Print test results
     */
    public function printResults(): void {
        foreach ($this->results as $result) {
            echo "\n" . str_repeat('=', 70) . "\n";
            echo "Test: {$result['name']}\n";
            echo str_repeat('=', 70) . "\n";
            echo "Iterations: {$result['iterations']}\n";
            echo "Passed: {$result['passed']}\n";
            echo "Failed: {$result['failed']}\n";
            echo "Status: " . ($result['success'] ? '✓ PASS' : '✗ FAIL') . "\n";
            
            if (!$result['success']) {
                echo "\nFailures:\n";
                foreach (array_slice($result['failures'], 0, 5) as $failure) {
                    echo "  - Iteration {$failure['iteration']}: {$failure['reason']}\n";
                    if ($failure['input']) {
                        echo "    Input: " . json_encode($failure['input']) . "\n";
                    }
                }
                if (count($result['failures']) > 5) {
                    echo "  ... and " . (count($result['failures']) - 5) . " more failures\n";
                }
            }
        }
        
        echo "\n" . str_repeat('=', 70) . "\n";
        $totalTests = count($this->results);
        $passedTests = count(array_filter($this->results, fn($r) => $r['success']));
        echo "Summary: {$passedTests}/{$totalTests} tests passed\n";
        echo str_repeat('=', 70) . "\n\n";
    }
}

/**
 * Generator helpers
 */
class Generators {
    /**
     * Generate a random string
     */
    public static function string(int $minLength = 1, int $maxLength = 20): string {
        $length = rand($minLength, $maxLength);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Generate a random integer
     */
    public static function int(int $min = 0, int $max = 1000): int {
        return rand($min, $max);
    }
    
    /**
     * Generate a random boolean
     */
    public static function bool(): bool {
        return (bool)rand(0, 1);
    }
    
    /**
     * Generate a random element from an array
     */
    public static function element(array $array) {
        return $array[array_rand($array)];
    }
    
    /**
     * Generate a random subset of an array
     */
    public static function subset(array $array): array {
        $size = rand(0, count($array));
        shuffle($array);
        return array_slice($array, 0, $size);
    }
}
?>
