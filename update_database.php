<?php
$host = 'localhost';
$dbname = 'sarathi_volunteer_db';
$username = 'root';
$password = 'rajkumar@123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Current Database vs Actual Files</h2>";
    
    // Get all records (you need to use your actual table name here)
    $stmt = $pdo->query("SELECT id, passport_path, aadhaar_path, certificate_path FROM volunteers ORDER BY id");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($records as $record) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px;'>";
        echo "<h3>Record ID: " . $record['id'] . "</h3>";
        
        // Check passport
        if (file_exists($record['passport_path'])) {
            echo "✅ Passport: " . basename($record['passport_path']) . "<br>";
        } else {
            echo "❌ Passport NOT FOUND: " . basename($record['passport_path']) . "<br>";
        }
        
        // Check aadhaar
        if (file_exists($record['aadhaar_path'])) {
            echo "✅ Aadhaar: " . basename($record['aadhaar_path']) . "<br>";
        } else {
            echo "❌ Aadhaar NOT FOUND: " . basename($record['aadhaar_path']) . "<br>";
        }
        
        // Check certificate
        if (file_exists($record['certificate_path'])) {
            echo "✅ Certificate: " . basename($record['certificate_path']) . "<br>";
        } else {
            echo "❌ Certificate NOT FOUND: " . basename($record['certificate_path']) . "<br>";
        }
        
        echo "</div>";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>