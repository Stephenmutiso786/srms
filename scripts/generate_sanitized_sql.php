<?php
// Usage: php generate_sanitized_sql.php /path/to/original.sql /path/to/output_sanitized.sql
if ($argc < 3) {
    echo "Usage: php generate_sanitized_sql.php <input.sql> <output.sql>\n";
    exit(1);
}
$in = $argv[1];
$out = $argv[2];
if (!is_file($in)) {
    echo "Input file not found: $in\n";
    exit(2);
}
$data = file_get_contents($in);
if ($data === false) {
    echo "Failed to read input file\n";
    exit(3);
}

// Replace demo emails
$data = preg_replace('/@srms\.test\b/i', '@example.edu', $data);

// Remove password hashes to avoid shipping credentials
$data = preg_replace('/\'\$2y\$[0-9A-Za-z\.\/]{53}\'/','\'PASSWORD_REMOVED\'',$data);

// Replace obvious placeholder logos or paths
$data = str_replace('school_logo1711003619.png', 'school_logo.png', $data);

// Replace dummy phone/email placeholders
$data = preg_replace('/(\'|\")?demo@srms\.test(\'|\")?/i', '\'admin@example.edu\'', $data);

// Write sanitized output
if (file_put_contents($out, $data) === false) {
    echo "Failed to write output file\n";
    exit(4);
}

echo "Sanitized SQL written to $out\n";
exit(0);
