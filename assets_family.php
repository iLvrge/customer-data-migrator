<?php 
require_once('/var/www/html/trash/vendor/autoload.php');
require_once('/var/www/html/trash/noti_config.php');
ignore_user_abort(true);
ini_set('max_execution_time', '0');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '/var/www/html/trash/connection.php';

function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}



$variables = $argv;
if(count($variables) > 1) {
	$organisationID = (int) $variables[1];
    $companyID = isset($variables[2]) ? $variables[2] : 0;
    $retrievedAll = false; 
    
    if(isset($variables[3]) && (int)$variables[3] == 1){
        $retrievedAll = true;
    }
	if((int)$organisationID > 0) {	
       /*  $queryAssets = "SELECT assets.grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM assets LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = assets.grant_doc_num  WHERE  assets.grant_doc_num <> '' AND assets.organisation_id = ".(int)$organisationID;

        if($companyID != '' && $companyID > 0) {
            $queryAssets .= " AND assets.company_id = ".$companyID;
        }
        
        $queryAssets .= " AND date_format(assets.appno_date, '%Y') > 1999  GROUP BY assets.grant_doc_num HAVING counter = 0 "; */
        $allCompanyIDs = array();
        $accountName = "";
        $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID; 
            $result = $con->query($query);
            $accountType = "";
            $companyIDDDD = 0;
            $allCompanyIDs = array();
            $orgConnect = '';
            $orgDB = '';
            $orgData = null;
            if($result && $result->num_rows > 0) {  
                while($row = $result->fetch_object()) { 
                    $accountType = $row->organisation_type;
                    $accountName = $row->name;
                    $orgData =  $row;
                }
            }
        if($companyID == "" || $companyID == "[]" ) {
            $orgConnect = new mysqli($orgData->org_host,$orgData->org_usr,$orgData->org_pass,$orgData->org_db);
            $orgDB = $orgData->org_db;
            if($orgConnect) {
                echo "CONNECTED";
                $queryRepresentative = "SELECT representative_name, company_id AS representative_id  FROM representative WHERE company_id > 0 AND mode = 0 ";
                $queryRepresentative .= " GROUP BY company_id ORDER BY status DESC";
                echo $queryRepresentative."<br/>";
                $resultRepresentative = $orgConnect->query($queryRepresentative);		
                        
                if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                    $companiesData = array();
                    
                    while($representative = $resultRepresentative->fetch_object()){ 
                        array_push($allCompanyIDs, $representative->representative_id);
                    }
                }
            } 
        } else { 
            if(isJson($companyID)) {
                $allCompanyIDs = json_decode($companyID);
            } else { 
                array_push($allCompanyIDs, $companyID);
            } 
        }
 

        if(count($allCompanyIDs) > 0) { 
            $assetsRetreieved = array();
            $totalAssets = 0;
            $queryAssets = "SELECT di.patent AS grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM db_new_application.dashboard_items AS di LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = di.patent  WHERE /* di.type IN (30, 36, 17, 20, 21, 22)  AND */ di.patent <> '' AND (di.organisation_id = 0 OR di.organisation_id IS NULL)  AND di.representative_id IN (".implode(',', $allCompanyIDs).") ";
            $queryAssets .= "  GROUP BY di.patent ";

            if($retrievedAll == false ) { 
                $queryAssets .= " HAVING counter = 0 ";
            } 


            echo $queryAssets; 
            $updateAllIDs = array();
            $counter = 0;
            $resultAllAssetsList = $con->query($queryAssets);
            $assetsRetreieved = array();

            if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                $totalAssets = $resultAllAssetsList->num_rows;
                echo "TOTAL: ".$totalAssets;
                
                
                $updateAllIDs = addLogData($organisationID, $allCompanyIDs, $totalAssets, $con);


                while($rowAsset = $resultAllAssetsList->fetch_object()) {
                    $assetsRetreieved[] = '"'.$rowAsset->grant_doc_num.'"';
                    if($retrievedAll == true || $rowAsset->counter == 0) { 
                        echo "ASSET: ".$rowAsset->grant_doc_num;  
                        $output = shell_exec('./node_modules/.bin/env-cmd node /var/www/html/script/assets_family.js "'.$rowAsset->grant_doc_num.'" > /var/www/html/log/assets_family_'.$organisationID.'.log 2>&1 &');
    
                        print_r($output);
    
                        echo "---------------IN SLEEPING MODE-------------";
    
                        sleep(15); // 15 seconds sleep;
                    } 
                    $counter++;

                    if(count($updateAllIDs) > 0) {
                        updateData($con, $updateAllIDs, $organisationID,  $totalAssets, $counter, 0);
                    }

                    if ($counter % 20 == 0) {
                        // Send your message here
                        sendNotifications("Total $counter / $totalAssets Family retrieved for $accountName.");
                    }
                }
            }
 
            sendNotifications("Dashboard, KPI assets family retrieved for $accountName. Now retriving for all transaction assets you can click update. You will see data for all the dashboard assets.");

            $queryAssets = "SELECT di.grant_doc_num AS grant_doc_num, COUNT(apf.grant_doc_num) AS counter FROM db_new_application.assets AS di LEFT JOIN db_uspto.assets_family AS apf ON apf.grant_doc_num = di.grant_doc_num  WHERE  di.grant_doc_num <> '' AND (di.organisation_id = 0 OR di.organisation_id IS NULL)  AND di.company_id IN (".implode(',', $allCompanyIDs).") ";

            $queryAssets .= "  GROUP BY di.grant_doc_num ";
            
            if($retrievedAll == false ) { 
                $queryAssets .= " HAVING counter = 0 ";
            } 

            


            echo $queryAssets; 
            $resultAllAssetsList = $con->query($queryAssets);
            if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                if(count($updateAllIDs) == 0) {
                    $totalAssets = $resultAllAssetsList->num_rows;
                    $counter = 0;
                } else {
                    $totalAssets = $totalAssets + $resultAllAssetsList->num_rows;
                    $counter = $counter;
                } 
                echo "TOTAL: ".$totalAssets;

                if(count($updateAllIDs) == 0) {
                    $updateAllIDs = addLogData($organisationID, $allCompanyIDs, $totalAssets, $con);
                } else {
                    updateData($con, $updateAllIDs, $organisationID,  $totalAssets, $counter, 0);
                }


                while($rowAsset = $resultAllAssetsList->fetch_object()) { 
                    $asset = '"'.$rowAsset->grant_doc_num.'"';
                    if(!in_array($asset, $assetsRetreieved)) {
                        echo "ASSET: ".$rowAsset->grant_doc_num;
                    
                        echo "FINDDDDDDDD";
                        if($retrievedAll == true || $rowAsset->counter == 0) { 
                            $output = shell_exec('./node_modules/.bin/env-cmd node /var/www/html/script/assets_family.js "'.$rowAsset->grant_doc_num.'" > /var/www/html/log/assets_family_'.$organisationID.'.log 2>&1 &');
        
                            print_r($output);
        
                            echo "---------------IN SLEEPING MODE-------------";
        
                            sleep(15); // 15 seconds sleep;
                        }
                        $counter++; 
                        if(count($updateAllIDs) > 0) {
                            updateData($con, $updateAllIDs, $organisationID,  $totalAssets, $counter, 0);
                        }
                        if ($counter % 20 == 0) {
                            // Send your message here
                            sendNotifications("Total $counter / $totalAssets Family retrieved for $accountName.");
                        }
                    } else {
                        $counter++; 
                    } 
                }
            }
            if(count($updateAllIDs) > 0) {
                updateData($con, $updateAllIDs, $organisationID,  $totalAssets, $counter, 1);
            }
            sendNotifications("All assets family retrieved for $accountName.");
        }  else {
            sendNotifications("No assets found for family to be retrieved for $accountName.");
        }
    }
}
function sendNotifications($data) {	
	$pusher = new Pusher\Pusher(CONSTANT_PUSHER_KEY, CONSTANT_PUSHER_SECRET, CONSTANT_PUSHER_APPID, array( 'cluster' => CONSTANT_PUSHER_CLUSTER, 'useTLS' => CONSTANT_PUSHER_ENCRYPTED ) );
	$pusher->trigger( CONSTANT_PUSHER_CHANNEL, CONSTANT_PUSHER_EVENT, $data );
}


function addLogData($organisationID, $allCompanyIDs, $totalAssets, $con) {  

    $con->query("DELETE FROM db_new_application.log_family_assets_messages WHERE organisation_id = ".$organisationID." AND company_id IN (".implode(',', $allCompanyIDs).")");

    $queryInsert = "INSERT INTO db_new_application.log_family_assets_messages(total_assets, organisation_id, company_id) VALUES ";

    foreach($allCompanyIDs as $company) {
        $queryInsert .= "($totalAssets, $organisationID, $company), ";
    }

    $queryInsert = substr($queryInsert, 0, -2);

     

    $result = $con->query($queryInsert);

    $assetsList = array() ;

    if($result) {
        $query = "SELECT id FROM db_new_application.log_family_assets_messages WHERE organisation_id = $organisationID AND company_id IN (".implode(',', $allCompanyIDs).") AND status = 0 ";


        $resultAssetsLogs = $con->query($query);
        if($resultAssetsLogs && $resultAssetsLogs->num_rows > 0) {
            while($row = $resultAssetsLogs->fetch_object()) {
                array_push($assetsList, $row->id);
            }
        } 
    }

    return $assetsList; 
}


function updateData($con, $updateAllIDs, $organisationID,  $totalAssets, $retrievedAssets, $status) {
    echo "UPDATE db_new_application.log_family_assets_messages SET total_assets = $totalAssets, retrieved_assets = $retrievedAssets, `status` = $status WHERE id IN (".implode(',', $updateAllIDs).") ";
    $con->query("UPDATE db_new_application.log_family_assets_messages SET total_assets = $totalAssets, retrieved_assets = $retrievedAssets, `status` = $status WHERE id IN (".implode(',', $updateAllIDs).") ");
}