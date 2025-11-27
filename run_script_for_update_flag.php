<?php 

require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); 
/*ignore_user_abort(true);*/
ini_set('xdebug.max_nesting_level', 1000); 
$host = getenv('DB_HOST');
$user = getenv('DB_USER'); 
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = getenv('DB_APPLICATION_DB');
$con = new mysqli($host, $user, $password, $dbUSPTO);
$con->query('SET GLOBAL range_optimizer_max_mem_size=0');

$variables = $argv;
if(count($variables) == 3) {
    $organisationID = $variables[1];
    $companyString = $variables[2];
     
    if ((int)$organisationID > 0 && !empty($companyString)) {
        $date = date('Y-m-d H:i:m');
        echo "INSERT INTO db_uspto.reclassify_log(organisation_id, `status`, `created_at`, `updated_at`) VALUES ($organisationID, 0, '".$date."', '".$date."')";
        $addData = $con->query("INSERT INTO db_uspto.reclassify_log(organisation_id, `status`, `created_at`, `updated_at`) VALUES ($organisationID, 0, '".$date."', '".$date."')");
        $updatedData = $con->insert_id;
        $companies = json_decode($companyString, true);
        if (!empty($companies)) {
            $commands = array_map(function($company) use ($organisationID) {
                return "php -f /var/www/html/scripts/update_flag.php $organisationID $company";
            }, $companies);
    
            // Run all commands concurrently
            foreach ($commands as $command) {
                exec($command);
            }
        }
        $date = date('Y-m-d H:i:m');
        echo "UPDATE db_uspto.reclassify_log SET `status` = 1, `updated_at` = '".$date."' WHERE id = $updatedData";
        $con->query("UPDATE db_uspto.reclassify_log SET `status` = 1, `updated_at` = '".$date."' WHERE id = $updatedData"); 
    }
} 