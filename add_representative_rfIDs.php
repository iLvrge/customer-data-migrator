<?php 
 ini_set('max_execution_time', '0');
/*error_reporting(E_ALL); 
ini_set('display_errors', 1);*/

require_once '/var/www/html/trash/connection.php';


$variables = $argv;


if(count($variables) == 3) {
	$organisationID = $variables[1];
	$companyID = $variables[2];
	 
	if($companyID != "") {
		$representativeID = 0;
		
		/*Find Company in representative table*/
		$queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"';
		
		$resultOrganisation = $con->query($queryOrganisation);
		
		if($resultOrganisation && $resultOrganisation->num_rows > 0) {
			$orgRow = $resultOrganisation->fetch_object();
			
			$orgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db);
			
			if($orgConnect) {
				$queryRepresentative = 'SELECT company_id AS representative_id, representative_name, original_name FROM representative WHERE  company_id  = '.$companyID;
			
				$resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
		
				if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {
					$representativeData = $resultRepresentativeParentCompany->fetch_object();
					$representativeID = $representativeData->representative_id;
					if( $representativeID > 0 ) {
						
						$allNames = ' aa.name = "'.$con->real_escape_string($representativeData->representative_name).'" OR r.representative_id="'.$representativeID.'" '; 
						
						$queryAssignorAssignees = 'SELECT assignor_and_assignee_id FROM `db_uspto`.`assignor_and_assignee` as aa LEFT JOIN `db_uspto`.`representative` as r  ON r.representative_id = aa.representative_id WHERE ('.$allNames.') GROUP BY assignor_and_assignee_id';
						
						$assignorAssigneeIDs = array();
						$result = $con->query($queryAssignorAssignees);
						if($result->num_rows > 0) {	
							while($row = $result->fetch_object()){
								array_push($assignorAssigneeIDs, $row->assignor_and_assignee_id);
							}
						}
						
						if(count($assignorAssigneeIDs) > 0) {
							$rfIDs = []; 
				
							$queryAssignee = 'SELECT rf_id FROM `db_uspto`.`assignee` as ac WHERE assignor_and_assignee_id IN ( '.implode(",", $assignorAssigneeIDs).') GROUP BY rf_id';
							
							$result = $con->query($queryAssignee);
							if($result->num_rows > 0) {	
								while($row = $result->fetch_object()){
									array_push($rfIDs, $row->rf_id);
								}
							}
							
							$queryAssignor = 'SELECT rf_id FROM `db_uspto`.`assignor` as ac WHERE assignor_and_assignee_id IN ( '.implode(",", $assignorAssigneeIDs).') GROUP BY rf_id';

							$result = $con->query($queryAssignor);
							if($result->num_rows > 0) {	
								while($row = $result->fetch_object()){
									array_push($rfIDs, $row->rf_id);
								}
							}								
							if(count($rfIDs) > 0) {								
								$queryDocumentRF = "SELECT rf_id FROM db_uspto.documentid WHERE rf_id IN (".implode(',', $rfIDs).") GROUP BY rf_id";
							
								$result = $con->query($queryDocumentRF);
								$rfIDs = array();
								if($result && $result->num_rows > 0) {	
									while($row = $result->fetch_object()){
										array_push($rfIDs, $row->rf_id);
									}
								}									
								if(count($rfIDs) > 0) {
									$string = "";
								
									foreach($rfIDs as $r){
										$string .= '('.$representativeID.', '.$r.'), '; 
									}
									
									$string = substr($string, 0, -2);
									
									$con->query('INSERT IGNORE INTO `db_uspto`.`representative_transactions`(representative_id, rf_id) VALUES '.$string)	;
								}									
							} 
						}									
					}		
				}		
			}		
		}		
	}	
}