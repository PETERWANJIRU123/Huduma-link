<?php
/**
 * Migration script to add completion_photo column to requests table
 * Run this file directly to update an existing database
 * This is safe to run multiple times - it checks if the column exists first
 */

require_once __DIR__ . '/config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if column already exists
    $columnExists = $pdo->query("SHOW COLUMNS FROM requests LIKE 'completion_photo'")->fetch();

    if ($columnExists) {
        echo "✓ The 'completion_photo' column already exists in the requests table.\n";
        echo "No migration needed.\n";
    } else {
        // Add the column
        $pdo->exec('ALTER TABLE requests ADD COLUMN completion_photo VARCHAR(180) NULL AFTER staff_note');
        echo "✓ Successfully added 'completion_photo' column to the requests table.\n";
    }

    echo "\nMigration complete!\n";
    echo "You can now delete this file if desired.\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease check:\n";
    echo "1. Your database credentials in config/config.php\n";
    echo "2. That the database exists\n";
    echo "3. That you have ALTER TABLE permissions\n";
    exit(1);
}
