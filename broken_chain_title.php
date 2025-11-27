<?php 
ignore_user_abort(true);
ini_set('max_execution_time', '0');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_WARNING);
error_reporting(E_ALL);


$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);
$assets1 = 14071185;
$assets2 = 13132115;
$clientOwnedAssets = array('"'.$assets1.'"', '"'.$assets2.'"');
if(count($clientOwnedAssets) > 0) {

                        $companyAssignorAndAssigneeIDs = array(2052932);
                        $allCompanyNames = array('Intellijoint Surgical Inc');
                        $companyID = 34444444; 
 
                        $applicationBroken = array();
                        $brokedNonInventorAssets = array();
                        foreach($clientOwnedAssets as $ownAsset) {
                            if(!in_array($ownAsset, $applicationBroken)) { 
                                $queryFindNonInventorLevel = "Select assigneeNames FROM (
                                    SELECT assigneeNames FROM (
                                    Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assigneeNames from documentid  AS doc
                                    INNER JOIN assignee AS aee ON aee.rf_id = doc.rf_id
                                    INNER JOIN assignor AS aor ON aor.rf_id = aee.rf_id 
                                    INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aee.assignor_and_assignee_id
                                    LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                                    INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aee.rf_id
                                    INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                                    where appno_doc_num = ".$ownAsset." AND (c.is_ota = 1 OR (rac.convey_ty = 'correct' AND rac.employer_assign = 1)) AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                        SELECT assignee.rf_id FROM documentid  
                                        INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                        where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN(".implode(',', $companyAssignorAndAssigneeIDs).") 
                                    )) AND aee.rf_id NOT IN (Select ee.rf_id from documentid as doc
                                    INNER JOIN assignee as ee ON ee.rf_id = doc.rf_id
                                    INNER JOIN assignor as aor ON aor.rf_id = doc.rf_id
                                    where appno_doc_num = ".$ownAsset." and ee.assignor_and_assignee_id IN(".implode(',', $companyAssignorAndAssigneeIDs).") HAVING MAX(aor.exec_dt))
                                    ORDER BY aor.exec_dt ASC
                                    ) AS temp
                                    WHERE assigneeNames NOT IN (".implode(',', $allCompanyNames).")
                                    GROUP BY assigneeNames) AS temp1
                                    LEFT JOIN (
                                    
                                    SELECT assignorNames FROM (
                                    Select IF(r.representative_name <> '', r.representative_name, aaa.name) AS assignorNames from documentid  AS doc
                                    INNER JOIN assignor AS aor ON aor.rf_id = doc.rf_id 
                                    INNER JOIN assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
                                    LEFT JOIN representative AS r On r.representative_id = aaa.representative_id
                                    INNER JOIN representative_assignment_conveyance AS rac ON rac.rf_id = aor.rf_id
                                    INNER JOIN conveyance AS c ON c.convey_name = rac.convey_ty
                                    where appno_doc_num = ".$ownAsset." AND (c.is_ota = 1 OR (rac.convey_ty = 'correct' AND rac.employer_assign = 1)) AND aor.exec_dt <= (SELECT MAX(exec_dt) FROM assignor WHERE rf_id IN (
                                        SELECT assignee.rf_id FROM documentid  
                                        INNER JOIN assignee on assignee.rf_id = documentid.rf_id
                                        where appno_doc_num = ".$ownAsset." AND assignee.assignor_and_assignee_id IN (".implode(',', $companyAssignorAndAssigneeIDs).") 
                                    ))
                                    ORDER BY aor.exec_dt ASC
                                    ) AS temp 
                                    GROUP BY assignorNames) AS temp2 ON temp2.assignorNames = temp1.assigneeNames
                                    WHERE temp2.assignorNames IS NULL";
                                echo $queryFindNonInventorLevel; 
                                 
                                $resultBrokednonInventorLevel = $con->query($queryFindNonInventorLevel); 
                                if($resultBrokednonInventorLevel && $resultBrokednonInventorLevel->num_rows > 0) {  
                                    $row = $resultBrokednonInventorLevel->fetch_object();
                                    echo $queryFindNonInventorLevel;
                                    print_r($row);


                                    array_push($brokedNonInventorAssets, $ownAsset);
                                }
                            }
                        } 
                        die;
                        if(count($brokedNonInventorAssets) > 0) {
                            $queryBrokenInsert = " INSERT IGNORE INTO db_new_application.assets (appno_doc_num, appno_date, grant_doc_num, grant_date, layout_id, company_id) SELECT MAX(appno_doc_num), MAX(appno_date), MAX(grant_doc_num), MAX(grant_date), 1, ".$companyID." FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $brokedNonInventorAssets).") GROUP BY appno_doc_num";
                            echo $queryBrokenInsert;
                            $con->query($queryBrokenInsert);
                        }


                        

                        echo "End Of Chain Of Title";
                        
                        /**s
                         * Broken Chain of Title
                         */
                        $type = 1;
                        $queryBrokenChain = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total)SELECT  ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type, grant_doc_num, appno_doc_num, 0 AS rf_id, ".count($clientOwnedAssets)."  FROM db_new_application.assets AS assets  WHERE date_format(assets.appno_date, '%Y') > 1999 AND assets.layout_id = 1 AND assets.company_id = ".$companyID." AND appno_doc_num IN (".implode(',', $clientOwnedAssets).") GROUP BY appno_doc_num";

                        $con->query($queryBrokenChain); 
                    } 