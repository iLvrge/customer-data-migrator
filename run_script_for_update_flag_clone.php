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
	 
	if((int)$organisationID > 0) {  
        if(!empty($companyString)) {
            $companies = json_decode($companyString);
            if(count($companies) > 0) {
                foreach($companies as $company) {
                    exec("php -f /var/www/html/scripts/update_flag.php $organisationID $company");
                }
            }
        } 
	}
} 