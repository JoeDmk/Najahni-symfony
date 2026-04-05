<?php
/**
 * Temporary script to update schema with FK checks disabled.
 * Run: php update_schema.php
 * Delete after use.
 */
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=najahni_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get the SQL from doctrine
$output = shell_exec('php bin/console doctrine:schema:update --dump-sql 2>&1');
$lines = array_filter(array_map('trim', explode(';', $output)), fn($l) => strlen($l) > 5);

echo "Found " . count($lines) . " SQL statements\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
echo "FK checks disabled\n";

$success = 0;
$errors = 0;
foreach ($lines as $sql) {
    $sql = trim($sql);
    if (empty($sql) || str_starts_with($sql, '--') || str_starts_with($sql, '//')) continue;
    try {
        $pdo->exec($sql);
        $success++;
    } catch (PDOException $e) {
        $errors++;
        echo "WARN: " . substr($sql, 0, 80) . " => " . $e->getMessage() . "\n";
    }
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "\nDone: $success success, $errors warnings\n";
echo "FK checks re-enabled\n";
