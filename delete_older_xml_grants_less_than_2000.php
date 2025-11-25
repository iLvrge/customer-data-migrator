<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';

// Connect to database
$con = new mysqli($host, $user, $password, $dbUSPTO);

// Check connection
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

echo "Connected to database successfully\n";

// First, get the count of records
$countQuery = "SELECT COUNT(*) as total FROM db_uspto.documentid WHERE date_format(appno_date, '%Y') < 2000 AND appno_date IS NOT NULL AND appno_date != '0000-00-00' AND grant_doc_num IS NOT NULL AND grant_date IS NOT NULL";
$countResult = $con->query($countQuery);

if ($countResult) {
    $countRow = $countResult->fetch_assoc();
    $totalRecords = $countRow['total'];
    echo "Total grant records found before year 2000: " . number_format($totalRecords) . "\n";
} else {
    die("Error getting count: " . $con->error . "\n");
}

// Open file for writing
$outputFile = '/files_to_delete_grants.txt';
$fileHandle = fopen($outputFile, 'w');

if (!$fileHandle) {
    die("Error: Cannot create output file: $outputFile\n");
}

echo "Starting to generate grant file list...\n";

// Query to get grant_doc_num and grant_date for records before year 2000
$query = "SELECT grant_doc_num, DATE_FORMAT(grant_date, '%Y%m%d') as formatted_date 
          FROM db_uspto.documentid 
          WHERE date_format(appno_date, '%Y') < 2000 
          AND appno_date IS NOT NULL 
          AND appno_date != '0000-00-00' 
          AND grant_doc_num IS NOT NULL 
          AND grant_date IS NOT NULL";

$result = $con->query($query);

if (!$result) {
    fclose($fileHandle);
    die("Error executing query: " . $con->error . "\n");
}

$processedCount = 0;
$filesFoundCount = 0;
$filesNotFoundCount = 0;
$batchSize = 10000;
$basePath = '/mnt/volume_sfo2_12/patent/XML/';

// Process results and write to file
while ($row = $result->fetch_assoc()) {
    $grantDocNum = $row['grant_doc_num'];
    $grantDate = $row['formatted_date'];
    
    // Format: US{grant_doc_num}A1-{grant_date}.XML
    $filename = "US" . $grantDocNum . "-" . $grantDate . ".XML";
    $fullPath = $basePath . $filename;
    
    // Check if file exists before adding to delete list
    if (file_exists($fullPath)) {
        // Write filename to file (one per line)
        fwrite($fileHandle, $filename . "\n");
        $filesFoundCount++;
    } else {
        $filesNotFoundCount++;
    }
    
    $processedCount++;
    
    // Show progress every batch
    if ($processedCount % $batchSize == 0) {
        echo "Processed: " . number_format($processedCount) . " / " . number_format($totalRecords) . " records ";
        echo "(Found: " . number_format($filesFoundCount) . ", Not Found: " . number_format($filesNotFoundCount) . ")\n";
    }
}

// Close file handle
fclose($fileHandle);

// Close database connection
$con->close();

echo "\n=== COMPLETED ===\n";
echo "Total records processed: " . number_format($processedCount) . "\n";
echo "Files found (added to delete list): " . number_format($filesFoundCount) . "\n";
echo "Files not found (skipped): " . number_format($filesNotFoundCount) . "\n";
echo "Output file: $outputFile\n";

if ($filesFoundCount > 0) {
    echo "\nYou can now delete the files using:\n";
   /*  echo "xargs -I {} rm -f /mnt/volume_sfo2_12/applications/XML/{} < $outputFile\n";
    echo "\nOr for safer deletion with confirmation:\n";
    echo "xargs -I {} -p rm -f /mnt/volume_sfo2_12/applications/XML/{} < $outputFile\n"; */
} else {
    echo "\nNo files found to delete.\n";
}
?>
