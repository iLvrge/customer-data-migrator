<?php 

 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL);
ini_set('display_errors', 1);*/
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
//$dbApplication = getenv('DB_APPLICATION_DB');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);

$variables = $argv;
print_r($variables);
if(count($variables) >= 3) {
    try {
 
        $organisationID = $variables[1];
        $representativeID = $variables[2];
        $runOtherScript = 0;
        if(isset($variables[3]) && $variables[3] == '1') {
            $runOtherScript = 1;
        }
        if((int)$organisationID > 0) {		
              
            $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID;	
            $result = $con->query($query);
            $accountType = "";
            $companyIDDDD = 0;
            $allCompanyIDs = array();
            $orgConnect = '';
            $orgDB = '';
            if($result && $result->num_rows > 0) {  
                while($row = $result->fetch_object()) {
                    $accountType = $row->organisation_type;
                    $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
                    $orgDB = $row->org_db;
                    if($orgConnect) {
                        $queryRepresentative = "SELECT company_id AS representative_id, original_name, parent_id, child, representative_name FROM representative WHERE company_id > 0 AND mode = 0 ";
                        if($representativeID != '') {
                            $queryRepresentative .= " AND company_id = '".$representativeID."'";
                        }

                        $queryRepresentative .= " GROUP BY company_id ORDER BY representative_name, status DESC";
                        echo $queryRepresentative."<br/>";
                        $resultRepresentative = $orgConnect->query($queryRepresentative);		
                                
                        if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                            $companiesData = array();
                            
                            while($representative = $resultRepresentative->fetch_object()){
                                if($representative->representative_id == $representativeID) {
                                    $companyIDDDD = $representative->representative_id;
                                }
                                array_push($allCompanyIDs, $representative->representative_id);
                                array_push($companiesData , array('company_id'=>$representative->representative_id, 'company_name'=>$representative->original_name, 'parent_id'=>$representative->parent_id, 'child'=>$representative->child, 'organisation_id'=> 0));
                            }
                            $query = "DELETE FROM ".$dbApplication.".company WHERE  company_id IN (".implode(',', $allCompanyIDs).") " ;
                            $con->query($query);
                            insertData($dbApplication, 'company', $companiesData, $con);
                        }
                    }
                }
            }
            /*if($representativeName != '') {
                $query = "SELECT * FROM company WHERE company_name = '".$con->real_escape_string($representativeName)."' AND (parent_id = 0 OR child = 1) AND organisation_id = ".(int)$organisationID;
            } else {
                
            }*/
            
            $query = "SELECT * FROM ".$dbApplication.".company  WHERE  company_id IN (".implode(',', $allCompanyIDs).") " ;
             
            $result = $con->query($query);
            echo $result->num_rows."<br/>";
            echo "companyIDDDD".$companyIDDDD."<br/>";;
            if($result && $result->num_rows > 0) {
                
                /* if($companyIDDDD > 0) { 
                    exec('php -f /var/www/html/scripts/find_release_security.php '.(int)$organisationID.' '.$companyIDDDD);
                } else {
                    exec('php -f /var/www/html/scripts/find_release_security.php '.(int)$organisationID);
                }  */ 

                if($accountType == 2) {
                    /**
                     * Bank Mode
                     */
                    
                     /**
                      * Get List of Security RFIDs
                      */
                    $yearTransaction = 1999;
                    echo $securityQuery = 'SELECT ass.rf_id, r.representative_id, r.representative_name, aaa.name, aor.or_name AS original_name, aaa.instances FROM db_uspto.assignment as ass INNER JOIN db_uspto.representative_assignment_conveyance AS ac ON ac.rf_id = ass.rf_id INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = ass.rf_id INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id INNER JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id WHERE date_format(aor.exec_dt, "%Y") > '.$yearTransaction.' AND ((ac.convey_ty = "security" AND ac.convey_ty <> "release") AND  MATCH (convey_text) AGAINST ("+SECURITY -RELEASE -TERMINATION" IN BOOLEAN MODE)) AND ass.rf_id IN (SELECT rf_id FROM db_uspto.list2 WHERE company_id IN ('.implode(',', $allCompanyIDs).')) GROUP BY ass.rf_id, r.representative_id ';

                    $resultSecurity = $con->query($securityQuery) or die('ERROR '.$con->error); 
                    $allSecurityRFIDsData = array();
                    $allCustomers = array(); 
                    $allCustomersData = array(); 
                    echo "ROW: ".$resultSecurity->num_rows."<br/>";
                    if($resultSecurity) {
                        while($rowSecurity = $resultSecurity->fetch_object()) { 
                            array_push($allSecurityRFIDsData, array('rf_id' => $rowSecurity->rf_id, 'representative_id' => $rowSecurity->representative_id)); 
                            if(!in_array($rowSecurity->representative_id, $allCustomers)) {
                                array_push($allCustomers, $rowSecurity->representative_id); 
                                array_push($allCustomersData, array('company_id' => $rowSecurity->representative_id, 'original_name' => $rowSecurity->original_name, 'representative_name' => $rowSecurity->representative_name, 'instances' => $rowSecurity->instances, 'mode' => 1)); 
                            }
                        }
                    }



                   

                    echo $releaseQuery = 'SELECT ass.rf_id, r.representative_id FROM db_uspto.assignment as ass INNER JOIN db_uspto.representative_assignment_conveyance AS ac ON ac.rf_id = ass.rf_id INNER JOIN db_uspto.assignor AS aor ON aor.rf_id = ass.rf_id INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id INNER JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id WHERE date_format(aor.exec_dt, "%Y") > '.$yearTransaction.' AND ac.convey_ty = "release" AND ass.rf_id IN (SELECT rf_id FROM db_uspto.list2 WHERE company_id IN ('.implode(',', $allCompanyIDs).')) AND aaa.representative_id IN ('.implode(',', $allCompanyIDs).') GROUP BY ass.rf_id, r.representative_id ';
                    $resultRelease = $con->query($releaseQuery) or die('ERROR '.$con->error); 
                    $allReleaseRFIDsData = array();
                    echo "ROW: ".$resultRelease->num_rows."<br/>";
                    if($resultRelease) {
                        while($rowRelease = $resultRelease->fetch_object()) { 
                            array_push($allReleaseRFIDsData, array('rf_id' => $rowRelease->rf_id, 'representative_id' => $rowRelease->representative_id)); 
                        }
                    }

                    
                     
                    
                    if(count($allSecurityRFIDsData) > 0) {
                        /**
                         * Delete from main database
                         */
                        
                        $query = "DELETE FROM ".$dbUSPTO.".bank_security_transactions WHERE representative_id IN (".implode(',', $allCustomers).")" ;
                        $con->query($query);
                        insertData($dbUSPTO, 'bank_security_transactions', $allSecurityRFIDsData, $con);


                      
                        $query = "DELETE FROM ".$dbUSPTO.".bank_release_transactions WHERE representative_id IN (".implode(',', $allCustomers).")" ;
                        $con->query($query);
                        if(count($allReleaseRFIDsData) > 0) {
                            insertData($dbUSPTO, 'bank_release_transactions', $allReleaseRFIDsData, $con);
                        } 
                        
                        /**
                         * Delete from customer database
                         */
                        $query = "DELETE FROM ".$orgDB.".representative WHERE company_id IN (".implode(',', $allCustomers).") AND mode = 1" ;
                        $orgConnect->query($query) or die('Error in representative '.$orgConnect->error);
                        insertData($orgDB, 'representative', $allCustomersData, $orgConnect);

                         
                    
                        if(count($allCustomers) > 0) {

                            $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items WHERE mode = 1 AND representative_id (".implode(',', $allCustomers).")";

                            $con->query($deleteQuery);

                            $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items_count WHERE mode = 1 AND representative_id (".implode(',', $allCustomers).")";

                            $con->query($deleteQuery);



                            
                            /**
                             * Check if in Representative then directly check from dashboard items table
                             * if not in representative table
                             */
                            foreach($allCustomers as $customer) {  
                                /**
                                 * Query check data exist in the dashboard Item table (Owned, Divested, Abandoned and Maintainence)
                                 * if exit then run dashboard item with bank mode
                                 * otherwise run dashboard item with company mode and then run again with dashboard item with bank mode
                                 */

                                    echo "CUSTOMER ID: ";
                                    print_r($customer);
                                   

                                $allSecurityAssets = array();
                                $allReleasedAssets = array();
                                $getSecurityAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid where rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".bank_security_transactions WHERE representative_id = ".$customer.") GROUP BY appno_doc_num";
                                $resultSecurityAssets = $con->query($getSecurityAssets);	
                                
                                if($resultSecurityAssets) {
                                    while($assetRow = $resultSecurityAssets->fetch_object()) {
                                        array_push($allSecurityAssets, '"'.$assetRow->appno_doc_num.'"');
                                    }
                                }

                                $getReleaseAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid where rf_id IN (SELECT rf_id FROM ".$dbUSPTO.".bank_release_transactions WHERE representative_id = ".$customer.") GROUP BY appno_doc_num";
                                $resultReleaseAssets = $con->query($getReleaseAssets);	 
                                if($resultReleaseAssets) {
                                    while($assetRow = $resultReleaseAssets->fetch_object()) {
                                        array_push($allReleasedAssets, '"'.$assetRow->appno_doc_num.'"');
                                    }
                                }

                                $runScript = true;
                                print_r($allSecurityAssets);
                                print_r($allReleasedAssets);
                                if(count($allSecurityAssets) == 0) {
                                    $runScript = false;
                                } else if(count($allSecurityAssets) > 0 && count($allReleasedAssets) > 0 ) {
                                    $resultArray = array_diff($allSecurityAssets, $allReleasedAssets);

                                    print_r($resultArray);
                                    if(count($resultArray) == 0) {
                                        $runScript = false;
                                    }
                                }

                                

                                if($runScript === true) { 
                                    echo "Bank On Fly";
                                    $queryDashboardItemCheck = "SELECT COUNT(*) as assetsData FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$customer." AND type IN (30, 33, 35, 36) AND mode = 0";

                                    $resultDataItem = $con->query($queryDashboardItemCheck);
                                    if($resultDataItem) {
                                        $rowDashbardItem = $resultDataItem->fetch_object();
                                        print_r($rowDashbardItem);
                                        if($rowDashbardItem->assetsData == 0) {
                                            /**
                                             * Add all the data for tables first
                                            */
                                            $customerRowData = array();
                                            foreach($allCustomersData as $customerData) {
                                                if(count($customerRowData) == 0) {
                                                    echo $customerData['company_id'].'@@@@'.$customer."<br/>";
                                                
                                                    if($customerData['company_id'] == $customer) { 
                                                        $customerRowData = array('company_name'=> $customerData['representative_name'], 'company_id' => $customerData['company_id']); 
                                                    }
                                                }
                                                
                                            }
                                            print_r($customerRowData);
                                            echo "I AM IN COLLECTING DATA";
                                            if(count($customerRowData) > 0) { 
                                                collectRawData((object) $customerRowData, $con);
                                                exec('php -f /var/www/html/scripts/find_release_security.php '.$customer);
                                                exec('php -f /var/www/html/scripts/dashboard_with_company_on_fly.php "'.$customer.'"');  
                                            }
                                        }
                                    } 

                                    $resultDataItem = $con->query($queryDashboardItemCheck);
                                    if($resultDataItem) {
                                        $rowDashbardItem = $resultDataItem->fetch_object();
                                        print_r($rowDashbardItem);

                                        if($rowDashbardItem->assetsData >  0) {  
                                            exec('php -f /var/www/html/scripts/dashboard_with_bank.php "'.$customer.'"');  
                                        } else {
                                            $orgConnect->query("DELETE FROM representative WHERE mode = 1 AND company_id = ".$customer );
                                        }
                                    }
                                } else {
                                    $orgConnect->query("DELETE FROM representative WHERE  mode = 1 AND company_id = ".$customer );
                                }  
                                /* $query = "DELETE FROM ".$dbUSPTO.".bank_security_transactions WHERE representative_id = ".$customer ;
                                $con->query($query);
                                $query = "DELETE FROM ".$dbUSPTO.".bank_release_transactions WHERE representative_id = ".$customer ;
                                $con->query($query);   */
                            }
                        } 
                    } 
                } 


                $allCompanies = array();
                while($row = $result->fetch_object()) {
                    print_r($row);
                    echo "ACCOUNT TYPE: ".$accountType;
                    array_push($allCompanies, $row->company_id);

                    collectRawData($row, $con);
                    
                    $date1 = getMinusYear(4);
                    $date2 = getMinusYear(3);
                    $date3 = getMinusYear(8);
                    $date4 = getMinusYear(7);
                    $date5 = getMinusYear(12);
                    $date6 = getMinusYear(11);

                    exec('php -f /var/www/html/scripts/find_release_security.php '.$row->company_id);
                    
                    /*Maintainence Assets*/
                    
                    //$con->query('CALL db_uspto.routine_maintainence_assets('.$row->company_id.', "'.$date1.'", "'.$date2.'", "'.$date3.'", "'.$date4.'", "'.$date5.'", "'.$date6.'");');
                    

                    //exec('php -f /var/www/html/scripts/dashboard_with_company.php '.$row->company_id.' '.$row->organisation_id.' "'.$con->real_escape_string($row->company_name).'"');
                    
                    echo "Done with all procedures"; 
                    echo "Send request for summary and dashboard_with_company";
                    //exec('php -f /var/www/html/scripts/summary.php "'.(int)$organisationID.'" "'.$row->company_id.'"');
                    echo 'php -f /var/www/html/scripts/dashboard_with_company.php "'.$row->company_id.'" "'.(int)$organisationID.'"';
                    exec('php -f /var/www/html/scripts/dashboard_with_company.php "'.$row->company_id.'" "'.(int)$organisationID.'"');  
                    echo "creating report_represetative_assets_transactions_by_account";
                    exec('php -f /var/www/html/scripts/report_represetative_assets_transactions_by_account.php '.(int)$organisationID.' "'.$row->company_id.'"');

                    /**
                     * INVENTORS and Assignments
                     */

                    $con->query("INSERT IGNORE INTO db_uspto.inventors(assignor_and_assignee_id) SELECT assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE activity_id = 10 AND company_id =".$row->company_id);

                    $con->query("DELETE db_uspto.inventors WHERE assignor_and_assignee_id IN (SELECT assignor_and_assignee_id FROM db_new_application.activity_parties_transactions WHERE activity_id <> 10 AND company_id =".$row->company_id.")");
                }	 
                echo "Loop finished Summary Org and Assets Family and Logos retrieval";
                /* exec('php -f /var/www/html/scripts/summary.php "'.(int)$organisationID.'" "'.$representativeName.'" "1"'); */
                if($representativeID == "") {
                    exec('php -f /var/www/html/scripts/summary.php "'.(int)$organisationID.'" "" "1"');
                } else { 
                    exec('php -f /var/www/html/scripts/summary.php "'.(int)$organisationID.'" "'.$representativeID.'" "1"');
                }
                /**
                 * Send Push Notification
                 */
                exec('node /var/www/html/script/send_push_notification.js "Update is compelete sucessfully"');

                exec('node /var/www/html/script/retrieve_cited_patents_assignees.js  0 "'.json_encode($allCompanies).'" 2');


                if($runOtherScript == '0') {
                    exec('php -f /var/www/html/script/assets_family.php "'.(int)$organisationID.'"');
                    /*  if(count($allCompanies) > 0) {
                        foreach($allCompanies as $company) {
                            echo "retrieved_logos";
                            $output = shell_exec('php -f /var/www/html/scripts/retrieved_logos.php "'.(int)$organisationID.'" "'.$company.'"');
                            print_r($output);
                        }
                    } */
                }
            }
        }	 
    } catch (Exception $e) {
        exec('node /var/www/html/script/send_push_notification.js "Erron in update."');
    }  
}

function getMinusYear($number){
	$time = new DateTime('now');
	return $time->modify('-'.$number.' year')->format('Y-m-d');
}

function insertData($dbUSPTO, $tableName, $list, $con, $childJSON = false, $param = ''){		
	if(count($list) > 0) {
		$i = 0;
		$stringName ="";
		$stringValue ="";
		for($i = 0; $i < count($list); $i++){
			$stringValue .="(";
			foreach($list[$i] as $key=>$value) {
				if($i == 0) {
					$stringName .= $key.", ";
				}
				if($childJSON === true && $param == $key){
					$stringValue .="'".json_encode($value)."'".", ";
				} else {
					$stringValue .="'".$con->real_escape_string($value)."'".", ";
				}
				
			}
			$stringValue = substr($stringValue, 0, -2);
			$stringValue .="), ";
		}
		$stringValue = substr($stringValue, 0, -2);
		$stringName = substr($stringName, 0, -2);
		$sql = "INSERT IGNORE INTO ".$dbUSPTO.".".$tableName."(".$stringName.") VALUES ".$stringValue;	
		echo $sql."<br/>";
		$result = $con->query($sql) or die($con->error);
	}
}

function collectRawData($row, $con) {
    echo "Now running procedure for collecting data";
    print_r($row); 
    try{
        //exec('php -f /var/www/html/scripts/find_release_security.php '.$row->company_id);
        $con->query('CALL db_uspto.routine_list1_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
 
        $con->query('CALL db_uspto.routine_list2_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
        $con->query('CALL db_uspto.routine_tableA_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
        
        $con->query('CALL db_uspto.routine_tableB_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
        $con->query('CALL db_uspto.routine_tableC_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
    
        //$con->query('CALL db_uspto.routine_broken_title('.$row->company_id.');');
        $con->query('CALL db_uspto.routine_correct_details_new("'.$row->company_name.'", '.$row->company_id.');') or die($con->error);
        
        /*Activities, Parties, and Transactions*/
        
        $con->query('CALL db_uspto.routine_activities_parties_transactions_new('.$row->company_id.');') or die($con->error);
    } catch (Exception $e) {
        print_r($e);
    }  
} 