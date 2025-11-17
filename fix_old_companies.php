<?php 

ini_set('max_execution_time', '0');
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$dbUSPTO = getenv('DB_USPTO_DB');
$dbBusiness = getenv('DB_BUSINESS');
$dbApplication = 'db_new_application';
$con = new mysqli($host, $user, $password, $dbUSPTO);


$variables = $argv;
//$variables = $_GET;
if(count($variables) == 2) {
    //if(count($variables) > 0) {
    $organisationID = $variables[1];
    if((int)$organisationID > 0) {
         $queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id ="'.$organisationID.'"'; 

       /* $queryOrganisation = 'SELECT * FROM db_business.organisation WHERE organisation_id > 3'; */
        
        $resultOrganisation = $con->query($queryOrganisation);
        
        if($resultOrganisation && $resultOrganisation->num_rows > 0) {

            while ($orgRow = $resultOrganisation->fetch_object()) { 
                /* $orgRow = $resultOrganisation->fetch_object(); */
                
                $accountOrgConnect = new mysqli($orgRow->org_host,$orgRow->org_usr,$orgRow->org_pass,$orgRow->org_db); 
                
                if($accountOrgConnect) { 
                    $queryRepresentative = "SELECT * FROM representative WHERE type = 0 AND parent_id = 0";

                    $resultRepresentative = $accountOrgConnect->query($queryRepresentative);
                    $allAddedRepresentatives = array();
                    echo $resultRepresentative->num_rows ;
                    if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                        while( $rowParent = $resultRepresentative->fetch_object()) {
                            array_push($allAddedRepresentatives, array('representative_id'=> $rowParent->representative_id, 'name'=>$rowParent->original_name)); 
                        }
                    }

                    $queryRepresentative = "SELECT * FROM representative WHERE type = 1 AND parent_id = 0";

                    $resultRepresentative = $accountOrgConnect->query($queryRepresentative); 
                    if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                        while($groupRepresentative = $resultRepresentative->fetch_object()) { 
                            $queryRepresentativeFromGroup = "SELECT * FROM representative WHERE parent_id = ".$groupRepresentative->representative_id." AND child = 1 GROUP BY original_name";
    
                            $resultRepresentativeGroup = $accountOrgConnect->query($queryRepresentativeFromGroup);
    
    
                            while( $rowParentGroup = $resultRepresentativeGroup->fetch_object()) {
                                array_push($allAddedRepresentatives, array('representative_id'=> $rowParentGroup->representative_id, 'name'=>$rowParentGroup->original_name)); 
                            }
                        }
                        
                    }

                    print_r($allAddedRepresentatives);

                    if(count($allAddedRepresentatives) > 0) {

                        foreach($allAddedRepresentatives as $company) {
                            $queryFindRepresentative = "SELECT representative_id FROM ".$dbUSPTO.".representative WHERE representative_name = '".$con->real_escape_string($company['name'])."'  ORDER BY representative_id DESC LIMIT 1";
                            echo $queryFindRepresentative;
                            $resultCompanyRepresentative = $con->query($queryFindRepresentative);	
                            $representativeID = 0;
                            if($resultCompanyRepresentative->num_rows > 0) {
                                $representativeRow = $resultCompanyRepresentative->fetch_object();
                                $representativeID = $representativeRow->representative_id; 
        
                                $queryUpdate = "UPDATE representative SET company_id = ".$representativeID. " WHERE representative_id = ".$company['representative_id'];
                                echo $queryUpdate;
                                $accountOrgConnect->query($queryUpdate); 
                            }
                        }
                    } 
                }
            }
        }
    }
}