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

function removeDoubleSpace($string) {
    return trim(preg_replace('/\s+/',' ', $string));
}
 
function strReplace( $string ) {
    $string = preg_replace('/,/', '', $string);
    $string = preg_replace('/\./', '', $string);
    $string = preg_replace('/!/', '', $string);
    return trim(ucwords(strtolower($string)));
}

function get_corpus_index($corpus = array(), $separator=' ') {

    $dictionary = array();

    $doc_count = array();

    foreach($corpus as $doc_id => $doc) {

        $terms = explode($separator, $doc);

        $doc_count[$doc_id] = count($terms);

        // tf–idf, short for term frequency–inverse document frequency, 
        // according to wikipedia is a numerical statistic that is intended to reflect 
        // how important a word is to a document in a corpus

        foreach($terms as $term) {

            if(!isset($dictionary[$term])) {

                $dictionary[$term] = array('document_frequency' => 0, 'postings' => array());
            }
            if(!isset($dictionary[$term]['postings'][$doc_id])) {

                $dictionary[$term]['document_frequency']++;

                $dictionary[$term]['postings'][$doc_id] = array('term_frequency' => 0);
            }

            $dictionary[$term]['postings'][$doc_id]['term_frequency']++;
        }

        //from http://phpir.com/simple-search-the-vector-space-model/

    }

    return array('doc_count' => $doc_count, 'dictionary' => $dictionary);
}

function get_similar_documents($query='', $corpus=array(), $separator=' '){

    $similar_documents=array();

    if($query!=''&&!empty($corpus)){

        $words=explode($separator,$query);

        $corpus=get_corpus_index($corpus, $separator);

        $doc_count=count($corpus['doc_count']);

        foreach($words as $word) {

            if(isset($corpus['dictionary'][$word])){

                $entry = $corpus['dictionary'][$word];


                foreach($entry['postings'] as $doc_id => $posting) {

                    //get term frequency–inverse document frequency
                    $score=$posting['term_frequency'] * log($doc_count + 1 / $entry['document_frequency'] + 1, 2);

                    if(isset($similar_documents[$doc_id])){

                        $similar_documents[$doc_id]+=$score;

                    }
                    else{

                        $similar_documents[$doc_id]=$score;

                    }
                }
            }
        }

        // length normalise
        foreach($similar_documents as $doc_id => $score) {

            $similar_documents[$doc_id] = $score/$corpus['doc_count'][$doc_id];

        }

        // sort from  high to low

        arsort($similar_documents);

    }   

    return $similar_documents;
}

$variables = $argv;
if(count($variables) == 3) {
    $company = $variables[1];
    $organisationID = $variables[2];

    if((int)$organisationID > 0) {  
        $listAllAssets = array();
        $companiesData = array();
        $companyAddress = array();
        $query = 'SELECT * FROM '.$dbBusiness.'.organisation WHERE org_pass <> "" AND organisation_id = '.(int)$organisationID; 
        $result = $con->query($query);
        $companyList = array();

        if($company != "") {
            try {
                $companyList =  json_decode($company, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // JSON is invalid 
                    $companyList = array();
                }  
            } catch(Exception $e) { 
            }
        } 

        if($result && $result->num_rows > 0) {  
            while($row = $result->fetch_object()) {
                $orgConnect = new mysqli($row->org_host,$row->org_usr,$row->org_pass,$row->org_db);
                if($orgConnect) {
                    $queryRepresentative = "SELECT representative_id, company_id, representative_name FROM representative WHERE type = 0"; 
                    
                    if($company != "") {
                        if(is_array($companyList) && count($companyList) > 0) { 
                            $queryRepresentative .= " AND company_id IN (".implode(',', $companyList).")";
                        } else { 
                            $queryRepresentative .= " AND company_id = ".$company;
                        }
                    } else {
                        $queryRepresentative .= " AND parent_id = 0 ";
                    }

                    $resultRepresentative = $orgConnect->query($queryRepresentative);   
                    if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                        while($representative = $resultRepresentative->fetch_object()){
                            array_push($companiesData, array('representative_id'=>$representative->company_id, 'name'=>$representative->representative_name));


                            /**
                             * Company Address
                             */
                            $queryAddress = "SELECT * FROM address WHERE representative_id =".$representative->representative_id;
                            $resultAddress = $orgConnect->query($queryAddress);
                            if($resultAddress && $resultAddress->num_rows > 0) {
                                while($representativeAddress = $resultAddress->fetch_object()){
                                    array_push($companyAddress, $representativeAddress);
                                }
                            }
                        }
                    }

                    if($company == "") {
                        $queryRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 1 AND parent_id = 0";    
                        $resultRepresentativeParentCompany = $orgConnect->query($queryRepresentative);
                
                        if($resultRepresentativeParentCompany && $resultRepresentativeParentCompany->num_rows > 0) {                    
                            while($getGroup = $resultRepresentativeParentCompany->fetch_object()) {
                                $queryGroupRepresentative = "SELECT representative_id, company_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$getGroup->representative_id;
                                
                                $resultRepresentativeGroupParentCompany = $orgConnect->query($queryGroupRepresentative);
                                if($resultRepresentativeGroupParentCompany && $resultRepresentativeGroupParentCompany->num_rows > 0) {
                                    
                                    while($getCompanyRow = $resultRepresentativeGroupParentCompany->fetch_object()) {
                                        array_push($companiesData, array('representative_id'=>$getCompanyRow->company_id, 'name'=>$getCompanyRow->representative_name));
                                    }
                                }
                            }
                        }
                    }

                    if(count($companiesData) == 0 && $company != "") {
                        $queryGroupRepresentative = "SELECT representative_id, representative_name FROM representative WHERE type = 1 AND parent_id = 0 "; 

                        if(is_array($companyList) && count($companyList) > 0) { 
                            $queryGroupRepresentative .= " AND company_id IN (".implode(',', $companyList).")";
                        } else { 
                            $queryGroupRepresentative .= " AND company_id = ".$company;
                        }
                        
                        $resultGroupRepresentative = $orgConnect->query($queryGroupRepresentative); 
                        if($resultGroupRepresentative && $resultGroupRepresentative->num_rows > 0) {
                            while($representativeGroup = $resultGroupRepresentative->fetch_object()){

                                $queryRepresentative = "SELECT representative_id, company_id, representative_name FROM representative WHERE type = 0 AND parent_id = ".$representativeGroup->representative_id; 
                               
                                $resultRepresentative = $orgConnect->query($queryRepresentative);   
                                if($resultRepresentative && $resultRepresentative->num_rows > 0) {
                                    while($representative = $resultRepresentative->fetch_object()){
                                        array_push($companiesData, array('representative_id'=>$representative->company_id, 'name'=>$representative->representative_name));
                                        /**
                                         * Company Address
                                         */
                                        $queryAddress = "SELECT * FROM address WHERE representative_id =".$representative->representative_id;
                                        $resultAddress = $orgConnect->query($queryAddress);
                                        if($resultAddress && $resultAddress->num_rows > 0) {
                                            while($representativeAddress = $resultAddress->fetch_object()){
                                                array_push($companyAddress, $representativeAddress);
                                            }
                                        }
                                    }
                                }
                            }
                        } 
                    }
                }
            }
        }

        if(count($companiesData) > 0) {
            /**
             * Security End Date
             */
            $time = new DateTime('now');
            $year =  $time->modify('-24 year')->format('Y'); 
            $time = new DateTime('now');
            $YEAR =   $time->modify('-21 year')->format('Y'); 
            $releaseIDs = array();

            $queryReleaseIDs = "SELECT apt.release_rf_id AS rf_id, FROM ".$dbApplication.".activity_parties_transactions AS apt WHERE  apt.activity_id IN (5,12) AND date_format(apt.exec_dt, '%Y') > ".$year." AND release_rf_id <> '' AND release_rf_id <> 0 GROUP BY apt.release_rf_id";

            $resultReleaseIDs = $con->query($queryReleaseIDs);
            if($resultReleaseIDs && $resultReleaseIDs->num_rows > 0) {
                while($rowReleaseIDs = $resultReleaseIDs->fetch_object()){
                    array_push($releaseIDs, (int)$rowReleaseIDs->rf_id);
                }
            }

            $abandonedStatus = array(
                'Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                'Provisional Application Expired', 
                'Final Rejection Mailed', 
                'Expressly Abandoned  --  During Publication Process', 
                'Expressly Abandoned  --  During Examination', 
                'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                'Abandoned  --  Failure to Pay Issue Fee', 
                'Abandoned  --  File-Wrapper-Continuation Parent Application',
                'Abandoned  --  Failure to Respond to an Office Action',  
                'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                'Abandoned  --  Incomplete Application (Pre-examination)', 
                'Abandonment for Failure to Correct Drawings/Oath/NonPub Request');
            foreach($companiesData as $company) {  
                $companyID = $company['representative_id'];

                
                $companyAllAssignorAndAssigneeIDs = array();
                $listAllAssets = array();
                $queryFindCompanyRepresentative = "SELECT representative_id FROM ".$dbUSPTO.".representative WHERE representative_name = '".$con->real_escape_string($company['name'])."' OR representative_id = ".$companyID." ORDER BY representative_id DESC LIMIT 1";
                echo $queryFindCompanyRepresentative;
                $resultCompanyRepresentative = $con->query($queryFindCompanyRepresentative);    
                $representativeID = 0;
                if($resultCompanyRepresentative->num_rows > 0) {
                    $representativeRow = $resultCompanyRepresentative->fetch_object();
                    $representativeID = $representativeRow->representative_id;
                }

                $allCompanyNames = array();

                $queryAssignorAndAssigneeIDs = "SELECT assignor_and_assignee_id, name FROM ".$dbUSPTO.".assignor_and_assignee WHERE name = '".$con->real_escape_string($company['name'])."' ";

                if($representativeID > 0) {
                    $queryAssignorAndAssigneeIDs .= "  OR representative_id = ".$representativeID." GROUP BY assignor_and_assignee_id";
                }
                echo  $queryAssignorAndAssigneeIDs;
                $resultCompanyAssignorAndAssigneeIDs = $con->query($queryAssignorAndAssigneeIDs);   
                $companyAssignorAndAssigneeIDs = array();
                if($resultCompanyAssignorAndAssigneeIDs->num_rows > 0) {
                    while($companyAssignorAssigneeRow = $resultCompanyAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($companyAssignorAndAssigneeIDs, $companyAssignorAssigneeRow->assignor_and_assignee_id);
                        array_push($allCompanyNames, '"'.$con->real_escape_string($companyAssignorAssigneeRow->name).'"');
                    }
                }
                $companyAssignorAndAssigneeIDsImploded = implode(',', $companyAssignorAndAssigneeIDs);

                $applicantsIDs = [];

                $queryApplicant = "SELECT assignor_and_assignee_id FROM db_patent_application_bibliographic.assignor_and_assignee WHERE name = '".$con->real_escape_string($company['name'])."' ";

                if($representativeID > 0) {
                    $queryApplicant .= "  OR representative_id = ".$representativeID." GROUP BY assignor_and_assignee_id";
                }
                echo $queryApplicant;
                $resultApplicantAssignorAndAssigneeIDs = $con->query($queryApplicant);  
                $applicantAssignorAndAssigneeIDs = array();
                if($resultApplicantAssignorAndAssigneeIDs->num_rows > 0) {
                    while($ApplicantAssignorAssigneeRow = $resultApplicantAssignorAndAssigneeIDs->fetch_object()) {
                        array_push($applicantAssignorAndAssigneeIDs, $ApplicantAssignorAssigneeRow->assignor_and_assignee_id);
                    }
                }
                $applicantAssets = [];
                $originalApplicantAssets = []; 
                if (!empty($applicantAssignorAndAssigneeIDs) || !empty($companyAssignorAndAssigneeIDs)) {
                    $assignorAndAssigneeIDs = implode(',', $applicantAssignorAndAssigneeIDs);
                    $queryApplicantAssets = "";
                    if(!empty($applicantAssignorAndAssigneeIDs)) {
                        $queryApplicantAssets = "SELECT appno_doc_num FROM ( SELECT appno_doc_num  FROM db_patent_grant_bibliographic.applicant 
                        WHERE assignor_and_assignee_id IN (".$assignorAndAssigneeIDs.")
                        UNION 
                        SELECT appno_doc_num FROM db_patent_application_bibliographic.applicant 
                        WHERE assignor_and_assignee_id IN (".$assignorAndAssigneeIDs.")
                        UNION
                        SELECT appno_doc_num  FROM db_patent_grant_bibliographic.assignee 
                        WHERE assignor_and_assignee_id IN (".$assignorAndAssigneeIDs.")
                        UNION
                        SELECT appno_doc_num FROM db_patent_application_bibliographic.assignee 
                        WHERE assignor_and_assignee_id IN (".$assignorAndAssigneeIDs.")
                        ) AS temp GROUP BY appno_doc_num ";
                    }

                    

                    if (!empty($companyAssignorAndAssigneeIDs)) {

                        if(!empty($queryApplicantAssets)) {
                            $queryApplicantAssets .= " UNION ";
                        }

                        $queryApplicantAssets .= " SELECT appno_doc_num FROM $dbUSPTO.table_b  WHERE company_id = $companyID AND appno_doc_num IN ( SELECT d.appno_doc_num FROM $dbUSPTO.documentid AS d INNER JOIN $dbApplication.activity_parties_transactions AS apt ON apt.rf_id = d.rf_id 
                            WHERE apt.company_id = $companyID AND apt.activity_id IN ( 10 ) AND apt.recorded_assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.") GROUP BY d.appno_doc_num) GROUP BY appno_doc_num";
                    }
                    

                   
                    echo $queryApplicantAssets;
                    $resultApplicantAssetsList = $con->query($queryApplicantAssets);
                    if($resultApplicantAssetsList && $resultApplicantAssetsList->num_rows > 0) {
                        while($rowAsset = $resultApplicantAssetsList->fetch_object()) {
                            array_push($applicantAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                }
                $originalApplicantAssets = $applicantAssets;
               
                //print_r($companyAssignorAndAssigneeIDs);
                echo "COMPANYID: ".$companyID."<br/>";
                /*$queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbApplication.".assets WHERE company_id = ".$companyID." AND date_format(assets.appno_date, '%Y') > 1999 AND organisation_id = ".$organisationID." AND assets.layout_id = 15 GROUP BY appno_doc_num";*/
                /**
                 * 
                 * Find company OTA Assets minus Sold Assets AND merger Out
                 */
                $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID." AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID." AND activity_id IN ( 1, 6 ) AND recorded_assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.") ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                echo $queryAllAssetsList;
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 

                $originalList = $listAllAssets;

               
               $companyAllAssets = array();

               $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID."  AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID." AND recorded_assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.") ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";

                echo $queryAllAssetsList;
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($companyAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 


                $companyAllTransactionAssets = array();

               $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID." AND recorded_assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.") ) GROUP BY appno_doc_num";

                echo $queryAllAssetsList;
                
                $resultAllAssetsList = $con->query($queryAllAssetsList);
                if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) {
                    while($rowAsset = $resultAllAssetsList->fetch_object()) {
                        array_push($companyAllTransactionAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 
                /**
                 * 
                 */

                $soldAssets = [];

                $querySoldAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND activity_id IN ( 2, 7 ) ) GROUP BY appno_doc_num";

                
                
                $resultSoldAssetsList = $con->query($querySoldAssetsList);
                if($resultSoldAssetsList && $resultSoldAssetsList->num_rows > 0) {
                    while($rowAsset = $resultSoldAssetsList->fetch_object()) {
                        array_push($soldAssets, '"'.$rowAsset->appno_doc_num.'"');
                    }
                } 

                
                if(count($soldAssets) > 0) {
                    $listAllAssets = array_diff($listAllAssets, $soldAssets);

                    $applicantAssets = array_diff($applicantAssets, $soldAssets);
                }
               

  
                $ownedAfterSold = $listAllAssets ;
                $expiredAssets = array();
                $onlyExpiredAssets = array();
                $currentDate = new DateTime('now'); 
                if(count($listAllAssets) > 0 || count($applicantAssets) > 0) { 
                    $designAssetsFirstPart = array();
                    $designAssetsSecondPart = array(); 
                    $grantApplications = array();
                    if(count($listAllAssets) > 0) {
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).")  AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num";
    
    
                        //$listAllAssets = array();
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) { 
                                array_push($listAllAssets, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $listAllAssets = array_unique($listAllAssets);
                        /**
                         * ApplicationTypeCategory : Design
                         * in PED XMLS
                         */
    
                        
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).") AND grant_doc_num LIKE 'd%' AND date_format(grant_date, '%Y-%m-%d') < '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid AS doc  WHERE appno_doc_num IN ( ".implode(',', $listAllAssets).") AND grant_doc_num LIKE 'd%' AND date_format(grant_date, '%Y-%m-%d') >= '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsSecondPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 

                        $findFromAllAssets = array_merge($listAllAssets, $applicantAssets);

                        $findPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_uspto.documentid WHERE grant_doc_num <> '' AND grant_doc_num <> '' AND appno_doc_num IN (".implode(',', $findFromAllAssets).")  GROUP BY appno_doc_num ";

                        echo "Grant Application Assets: ".$findPatentsAssets."<br/>";
                        $resultGrantApplications = $con->query($findPatentsAssets); 
                        if($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
                            while($rowAsset = $resultGrantApplications->fetch_object()) {
                                array_push($grantApplications, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        }
                    }
                    if(count($grantApplications) > 0) {
                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362')  AND appno_doc_num IN (".implode(',', $grantApplications).") GROUP BY appno_doc_num ";

                    
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                array_push($expiredAssets, '"'.$rowAsset->application.'"');
                            }
                        }
                    }


                    if(count($listAllAssets) > 0) {
                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request')  AND appno_doc_num IN (".implode(',', $listAllAssets).") ";
                        
                        if(count($grantApplications) > 0) {
                            $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplications).") ";
                        }
                        
                        $queryExpiredStatusAssets .= " GROUP BY appno_doc_num ";

                        echo $queryExpiredStatusAssets;
                        
                        //die;

                        
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                array_push($expiredAssets, '"'.$rowAsset->application.'"');
                            }
                        }
                    }

                    /**
                     * From Maintainence
                     */
                    $allAssets = array_merge($listAllAssets, $applicantAssets);
                    $queryExpiredMaintainenceAssets = "SELECT appno_doc_num AS application FROM db_patent_maintainence_fee.event_maintainence_fees  WHERE event_code IN ('EXP', 'EXP.')  AND appno_doc_num IN (".implode(',', $allAssets).") GROUP BY appno_doc_num ";

                    
                    $resultAllExpiredAssetsList = $con->query($queryExpiredMaintainenceAssets); 
                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
                        }
                    }

                    

                    $queryExpiredDateAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") GROUP BY ap.appno_doc_num) AS temp GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num  = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $allAssets).") ";  

                    if(count($designAssetsFirstPart) > 0) {
                        $queryExpiredDateAssets .= " AND ap.appno_doc_num NOT IN (".implode(',', $designAssetsFirstPart).") ";
                    }

                    if(count($designAssetsSecondPart) > 0) {
                        $queryExpiredDateAssets .= " AND ap.appno_doc_num NOT IN (".implode(',', $designAssetsSecondPart).") ";
                    }
                    
                    
                    $queryExpiredDateAssets .= " GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer > 19 ";
                    $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
                    if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                            array_push($expiredAssets, '"'.$rowAsset->application.'"');
                        }
                    }
                    $expiredAssets = array_unique($expiredAssets);
                    if(count($applicantAssets) > 0) {

                        $queryAllAssetsList = "SELECT appno_doc_num FROM db_patent_application_bibliographic.application_grant AS doc  WHERE appno_doc_num IN ( ".implode(',', $applicantAssets).") AND grant_doc_num LIKE 'D%' AND date_format(grant_date, '%Y-%m-%d') < '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsFirstPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
    
                        $queryAllAssetsList = "SELECT appno_doc_num FROM db_patent_application_bibliographic.application_grant AS doc  WHERE appno_doc_num IN ( ".implode(',', $applicantAssets).") AND grant_doc_num LIKE 'D%' AND date_format(grant_date, '%Y-%m-%d') >= '2015-05-13' AND date_format(appno_date, '%Y') > ".$YEAR." GROUP BY appno_doc_num"; 
                         
                        $resultAllAssetsList = $con->query($queryAllAssetsList);
                        if($resultAllAssetsList && $resultAllAssetsList->num_rows > 0) { 
                            while($rowAsset = $resultAllAssetsList->fetch_object()) {
                                array_push($designAssetsSecondPart, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 

                    }
                    /**
                     * Design Assets
                     * Expired in 14 years if the data before May 13, 2015 otherwise its 15 years
                     */
                    if(count($designAssetsFirstPart) > 0) {
                        $queryExpiredDateDesignAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 14 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $designAssetsFirstPart).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 14 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $designAssetsFirstPart).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer >= 13 ";
                        echo $queryExpiredDateDesignAssets;
                        //die;
                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateDesignAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    } 
                    if(count($designAssetsSecondPart) > 0) {
                        $queryExpiredDateDesignAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 15 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num COLLATE utf8mb4_general_ci = ap.appno_doc_num COLLATE utf8mb4_general_ci WHERE ap.appno_doc_num IN (".implode(',', $designAssetsSecondPart).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, grant_date, extendedDate), timestampdiff(YEAR, grant_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, grant_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(grant_date, INTERVAL 15 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $designAssetsSecondPart).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer >= 14 ";

                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateDesignAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    } 

                    $grantApplicantApplications = array();
                    if(count($applicantAssets) > 0) {

                        $findApplicantPatentsAssets = "SELECT MAX(appno_doc_num) AS appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num <> '' AND grant_doc_num <> '' AND appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num ";
    
                        
                        $resultApplicantGrantApplications = $con->query($findApplicantPatentsAssets); 
                        if($resultApplicantGrantApplications && $resultApplicantGrantApplications->num_rows > 0) {
                            while($rowAsset = $resultApplicantGrantApplications->fetch_object()) {
                                array_push($grantApplicantApplications, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        }
    
                        if(count($grantApplicantApplications) > 0) {
                            $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362')  AND appno_doc_num IN (".implode(',', $grantApplicantApplications).") GROUP BY appno_doc_num ";
    
                        
                            $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                    array_push($expiredAssets, '"'.$rowAsset->application.'"');
                                }
                            }
                        }

                        $queryExpiredStatusAssets = "SELECT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request')  AND appno_doc_num IN (".implode(',', $applicantAssets).") " ;
                        
                        if(count($grantApplicantApplications) > 0) {
                            $queryExpiredStatusAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplicantApplications).") ";

                        }
                        
                        $queryExpiredStatusAssets .= " GROUP BY appno_doc_num ";

                         
                        $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }

                        /* $queryExpiredDateAssets = "SELECT application FROM ( SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19 UNION SELECT appno_doc_num AS application, timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19   UNION SELECT MAX(doc.appno_doc_num) AS application, timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer FROM db_uspto.documentid AS doc  AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = doc.appno_doc_num  WHERE doc.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num HAVING yearDiffer > 19) AS temp GROUP BY application"; */

                        $queryExpiredDateAssets = "SELECT application, yearDiffer FROM ( SELECT application, IF(extendedDate > currentDate, 0, yearDiffer) AS yearDiffer FROM ( SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_grant_bibliographic.application_publication AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_patent_application_bibliographic.application_grant AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp1 GROUP BY application UNION SELECT application, extendedDate, '".$currentDate->format('Y-m-d')."' AS currentDate, IF(extendedDate <> '', timestampdiff(YEAR, appno_date, extendedDate), timestampdiff(YEAR, appno_date, '".$currentDate->format('Y-m-d')."')) AS yearDiffer FROM ( SELECT ap.appno_doc_num AS application, appno_date, IF(ge.extension <> '', DATE_ADD( DATE_ADD(appno_date, INTERVAL 20 YEAR ), INTERVAL ge.extension DAY) , '') AS extendedDate FROM db_uspto.documentid AS ap LEFT JOIN db_patent_application_bibliographic.grant_extension AS ge ON ge.appno_doc_num = ap.appno_doc_num WHERE ap.appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY ap.appno_doc_num) AS temp2 GROUP BY application ) AS temp3 ) AS temp4 GROUP BY application HAVING yearDiffer > 19 ";
                        echo "Query";
                        
                        $resultAllExpiredAssetsList = $con->query($queryExpiredDateAssets); 
                        if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                            while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                $appLicationNo = '"'.$rowAsset->application.'"'; 
                                if(!in_array($appLicationNo, $expiredAssets)) { 
                                    array_push($expiredAssets, $appLicationNo);
                                }
                            }
                        }
                    }  
                    if(count($expiredAssets) > 0) {
                        $queryExpiredWithDate = "INSERT IGNORE INTO ".$dbApplication.".assets_with_bank_expired_status(appno_doc_num, status_date, expire_date, company_id) SELECT appno_doc_num, MAX(status_date) AS sDate, MAX(expiry_date) AS exDate, ".$companyID." FROM ( SELECT appno_doc_num, MAX(status_date) AS status_date, '' AS expiry_date FROM db_uspto.application_status WHERE appno_doc_num IN (".implode(',', $expiredAssets).")  GROUP BY appno_doc_num UNION ALL SELECT appno_doc_num, '' AS status_date, DATE_ADD(appno_date, INTERVAL 20 YEAR) AS expiry_date FROM db_uspto.documentid WHERE appno_doc_num IN (".implode(',', $expiredAssets).") GROUP BY appno_doc_num ) AS temp GROUP BY appno_doc_num";
                        //echo $queryExpiredWithDate;
                        $con->query($queryExpiredWithDate);
                    }
                } /* End of if for listAllAssets or applicantAssets*/
                /**
                 * In assignment db acquired
                 */
                $allAssets = $originalList;

               
                if(count($expiredAssets) > 0 && count($listAllAssets) > 0) {
                    $listAllAssets = array_diff($listAllAssets, $expiredAssets);
                    $allAssets = array_diff($allAssets, $expiredAssets);
                    
                }

                if(count($expiredAssets) > 0 && count($applicantAssets) > 0) {
                    $applicantAssets = array_diff($applicantAssets, $expiredAssets);
                }

                

                echo "listAllAssets Assets";
                print_r($listAllAssets); 


                echo "SOLD: ";
                print_r($soldAssets);
                echo "EXPIRED: ";
                echo count($expiredAssets);
                print_r($expiredAssets); 
                
                /**
                 * Asset Last Status 
                 */
                $patentedAssetsStatus = array();
                $employeeAssets = array();
                if(count($listAllAssets) > 0) {
                    echo $queryAllEmployeeAssetsList = "SELECT appno_doc_num FROM ".$dbUSPTO.".table_b  WHERE company_id = ".$companyID." AND appno_doc_num IN (".implode(',', $listAllAssets).")  AND appno_doc_num IN (SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE rf_id IN (SELECT rf_id FROM ".$dbApplication.".activity_parties_transactions WHERE company_id = ".$companyID."  AND activity_id IN ( 10 ) ) GROUP BY appno_doc_num) GROUP BY appno_doc_num";
                    
                    $resultAllEmployeeAssetsList = $con->query($queryAllEmployeeAssetsList);
                    if($resultAllEmployeeAssetsList && $resultAllEmployeeAssetsList->num_rows > 0) {
                        while($rowAsset = $resultAllEmployeeAssetsList->fetch_object()) {
                            array_push($employeeAssets, '"'.$rowAsset->appno_doc_num.'"');
                        }
                    } 
                } 
                echo "REmaining: ".count($listAllAssets); 
                echo implode(',', $listAllAssets);
                echo "Applicant: ";
                print_r($applicantAssets); 
                echo "Employee: ";
                print_r($employeeAssets);
                $implodeAssetsList = implode(',', $listAllAssets);
                if(count($employeeAssets) > 0 && count($listAllAssets) > 0) {
                    if(count($soldAssets) > 0 && count($employeeAssets) > 0) {
                        $employeeAssets = array_diff($employeeAssets, $soldAssets);
                    }
                    if(count($expiredAssets) > 0 && count($employeeAssets) > 0) {
                        $employeeAssets = array_diff($employeeAssets, $expiredAssets);
                    }  
                } 

                if(count($allAssets) > 0 && count($employeeAssets) > 0) {
                    $patentedAssetsStatus = array_diff($allAssets, $employeeAssets);
                } else {
                    $patentedAssetsStatus = $allAssets;
                }

                
                if(count($applicantAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $applicantAssets = array_diff($applicantAssets, $patentedAssetsStatus);
                }


               
 
                if(count($soldAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $patentedAssetsStatus = array_diff($patentedAssetsStatus, $soldAssets);
                }

                if(count($expiredAssets) > 0 && count($patentedAssetsStatus) > 0) {
                    $patentedAssetsStatus = array_diff($patentedAssetsStatus, $expiredAssets);
                }
                

                $implodePatentedAssetsList = implode(',', $patentedAssetsStatus); 
                $currentDate = new DateTime('now');
                $FORMAT = 'Y-m-d';
                $graceEndDate = $currentDate->modify('+6 month')->format($FORMAT);
                $currentDate = new DateTime('now');
                $dueDate = $currentDate->modify('-6 month')->format($FORMAT);
                $currentDate = new DateTime('now');
                $formatCurrentDate = $currentDate->format($FORMAT);

                $queryEntityStatus = "SELECT MAX(entity_status) AS entity_status FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") LIMIT 1";

                $defaultEntityStatus = 'N';

                $resultEntityStatus = $con->query($queryEntityStatus);
                if($resultEntityStatus && $resultEntityStatus->num_rows > 0) {
                    $rowEntityStatus = $resultEntityStatus->fetch_object();
                    $defaultEntityStatus =  $rowEntityStatus->entity_status;
                } 

                echo "ASSSSSSSSS";

                echo count($originalList)."%".count($expiredAssets)."%".count($soldAssets)."%".count($applicantAssets)."%".count($patentedAssetsStatus).'%'.count($employeeAssets)."%".count($listAllAssets)."%".count($allAssets);
                
                $allTypes = [30,31,32,33,34,35,36,37,38,39,40,41,1,17,18,19,20,21,22,23,24,25,26,27];
                    
                $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID;

                $con->query($deleteQuery);

                $deleteQuery = "DELETE FROM ".$dbApplication.".dashboard_items_count WHERE type IN (".implode(',', $allTypes).") AND representative_id = ".$companyID;

                $con->query($deleteQuery); 

                $con->query("DELETE FROM ".$dbApplication.".owned_assets WHERE company_id = ".$companyID);

                $YEARThreshold = ($YEAR + 1)."-01-01";

                if(
                    count($originalList) > 0  || 
                    count($expiredAssets) > 0 || 
                    count($soldAssets) > 0 || 
                    count($applicantAssets) > 0 || 
                    count($companyAllTransactionAssets) > 0
                ) {  
                     /**
                     * Assets Acquired 
                     */
                    $type = 32;
                    $acquiredAcitivityID = implode(',', array(1,6));
                    echo "AAA: ".count($patentedAssetsStatus)."asd";
                    if(count($patentedAssetsStatus) > 0) {
                        
                        $queryPatentAcquired = "
                          INSERT IGNORE INTO {$dbApplication}.dashboard_items 
                            (representative_id, assignor_id, type, patent, application, rf_id, total)
                          SELECT 
                            {$companyID}, 
                            0, 
                            {$type}, 
                            MAX(d.grant_doc_num), 
                            MAX(d.appno_doc_num), 
                            0, 
                            0
                          FROM 
                            {$dbUSPTO}.documentid AS d
                          WHERE 
                            d.appno_date >= {$YEARThreshold}
                            AND d.appno_doc_num IN ({$implodePatentedAssetsList})
                          GROUP BY 
                            d.appno_doc_num
                        ";

                        echo $queryPatentAcquired ; 
                    
                        $con->query($queryPatentAcquired); 
                    }  

                    /**
                     * Filled Assets Applicant
                     */
                    $soldAssetsImploded = implode(',', $soldAssets);
                    $expiredAssetsImploded = implode(',', $expiredAssets);
                    
                    if(count($applicantAssets) > 0 || count($employeeAssets) > 0) {
                        $type = 31;
                        $applicantAndEmployee = array_merge($applicantAssets, $employeeAssets);  
 
                        $queryApplicantPatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID.", 0, ".$type.", CASE 
                        WHEN d.grant_doc_num LIKE 'D0%' THEN CONCAT('D', SUBSTRING(d.grant_doc_num, 3))
                        ELSE d.grant_doc_num
                        END AS patent, d.appno_doc_num, 0, 0 FROM db_patent_application_bibliographic.application_grant AS d  WHERE d.appno_date >= ".$YEARThreshold." AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") ";

                        if(count($soldAssets) > 0) {
                            $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".$soldAssetsImploded.") ";
                        }

                        if(count($expiredAssets) > 0) {
                            $queryApplicantPatent .= " AND d.appno_doc_num NOT IN (".$expiredAssetsImploded.") ";
                        }
                        
                        
                        $queryApplicantPatent .= "GROUP BY d.appno_doc_num";

                        echo $queryApplicantPatent."<br/>";
                      
                        $con->query($queryApplicantPatent); 
                    

                        $queryEmployeePatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID.", 0, ".$type.", d.grant_doc_num, d.appno_doc_num, 0, 0 FROM db_uspto.documentid AS d  WHERE d.appno_date >= ".$YEARThreshold." AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).") AND d.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type.")";

                        if(count($soldAssets) > 0) {
                            $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".$soldAssetsImploded.") ";
                        }

                        if(count($expiredAssets) > 0) {
                            $queryEmployeePatent .= " AND d.appno_doc_num NOT IN (".$expiredAssetsImploded.") ";
                        }
                        
                        
                        $queryEmployeePatent .= "GROUP BY d.appno_doc_num";

                        echo $queryEmployeePatent."<br/>";
                      
                        $con->query($queryEmployeePatent); 
                    

                        $queryApplicantApplication = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) 
                            SELECT DISTINCT 
                              {$companyID},
                              0,
                              {$type},
                              '',
                              d.appno_doc_num,
                              0,
                              0
                            FROM db_patent_grant_bibliographic.application_publication AS d  
                            WHERE d.appno_date >= {$YEARThreshold}
                                AND d.appno_doc_num IN (".implode(',', $applicantAndEmployee).")  
                                AND d.appno_doc_num 
                                AND NOT EXISTS (
                                    SELECT 1
                                    FROM dbApplication.dashboard_items AS dash
                                    WHERE dash.representative_id = {$companyID}
                                      AND dash.type = {$type}
                                      AND dash.application = d.appno_doc_num
                                )  
                        ";

                        if(count($soldAssets) > 0) {
                            $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".$soldAssetsImploded.") ";
                        }

                        if(count($expiredAssets) > 0) {
                            $queryApplicantApplication .= " AND d.appno_doc_num NOT IN (".$expiredAssetsImploded.") ";
                        }
                        
                        
                        echo $queryApplicantApplication."<br/>";
                        $con->query($queryApplicantApplication);  
                    }
                    /*
                     * Owned Patents
                    */
                    $type = 30;
                    
                    $queryOwnedPatent = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT  ".$companyID.", 0, ".$type.", patent, application, 0, 0 FROM ".$dbApplication.".dashboard_items  WHERE type IN (31, 32) AND representative_id = ".$companyID." GROUP BY application";

                    $con->query($queryOwnedPatent);


                    if(count($listAllAssets) > 0) {
                        $con->query("INSERT IGNORE INTO ".$dbApplication.".owned_assets(appno_doc_num, company_id) SELECT application, ".$companyID." FROM ".$dbApplication.".dashboard_items WHERE   representative_id = ".$companyID." AND type = 30 GROUP BY application");
                    }

                    /**
                     * Assets Divested
                     */
                    if(count($soldAssets) > 0) {

                        $type = 33;
                        $querySoldAsset = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID.", 0, ".$type.", MAX(d.grant_doc_num), MAX(d.appno_doc_num), 0, 0 FROM ".$dbUSPTO.".documentid AS d WHERE d.appno_date  >= {$YEARThreshold} AND d.appno_doc_num IN (".$soldAssetsImploded.")  GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset);
  

                        $querySoldAsset2 = "
                            INSERT IGNORE INTO {$dbApplication}.dashboard_items 
                            (representative_id, assignor_id, type, patent, application, rf_id, total)
                            SELECT 
                              {$companyID}, 0, 33, 
                              CASE 
                                WHEN MAX(d.grant_doc_num) LIKE 'D0%' THEN CONCAT('D', SUBSTRING(MAX(d.grant_doc_num), 3))
                                ELSE MAX(d.grant_doc_num)
                              END AS patent, 
                              MAX(d.appno_doc_num), 
                              0, 0
                            FROM db_patent_application_bibliographic.application_grant AS d
                            WHERE 
                              d.appno_date >= {$YEARThreshold}
                              AND d.appno_doc_num IN ({$soldAssetsImploded})
                              AND NOT EXISTS (
                                SELECT 1 FROM {$dbApplication}.dashboard_items di
                                WHERE 
                                  di.application = d.appno_doc_num 
                                  AND di.representative_id = {$companyID}
                                  AND di.type = 33
                              )
                            GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset2);

                        $querySoldAsset3 = "
                            INSERT IGNORE INTO {$dbApplication}.dashboard_items 
                            (representative_id, assignor_id, type, patent, application, rf_id, total)
                            SELECT 
                              {$companyID}, 0, 33, 
                              '', 
                              MAX(d.appno_doc_num), 
                              0, 0
                            FROM db_patent_grant_bibliographic.application_publication AS d
                            WHERE 
                              d.appno_date >= {$YEARThreshold}
                              AND d.appno_doc_num IN ({$soldAssetsImploded})
                              AND NOT EXISTS (
                                SELECT 1 FROM {$dbApplication}.dashboard_items di
                                WHERE 
                                  di.application = d.appno_doc_num 
                                  AND di.representative_id = {$companyID}
                                  AND di.type = 33
                              )
                            GROUP BY d.appno_doc_num";
                        $con->query($querySoldAsset3);
                        
                    }
                    $clientOwnedQuery = "SELECT application FROM db_new_application.dashboard_items WHERE ( organisation_id = 0 OR organisation_id IS NULL ) AND representative_id = ".$companyID." AND type = 30 GROUP BY application";
                    
                    $clientOwnedAssets = array();
                    $resultClientOwned = $con->query($clientOwnedQuery);
                    if($resultClientOwned && $resultClientOwned->num_rows > 0) {
                        while($rowAsset = $resultClientOwned->fetch_object()) {
                            array_push($clientOwnedAssets, '"'.$rowAsset->application.'"');
                        }
                    } 

                    $clientOwnedAssetsImploded = implode(',', $clientOwnedAssets);
                    
                    if(count($clientOwnedAssets) > 0) {
                        /**
                         * Collateralized
                         * Show all the assets which are not released yet
                         */
                        $type = 34; 
                        $releaseTypes = "'release', 'partialrelease'";
                        $securityTypes = "'security', 'restatedsecurity'";

                        $seQuery = "
                        SELECT DISTINCT s.appno_doc_num
                        FROM (
                            SELECT 
                                doc.appno_doc_num,
                                IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName,
                                COUNT(DISTINCT ee.rf_id) AS counter
                            FROM assignee ee
                            INNER JOIN db_new_application.activity_parties_transactions apt ON apt.rf_id = ee.rf_id 
                            INNER JOIN documentid doc ON doc.rf_id = ee.rf_id
                            INNER JOIN representative_assignment_conveyance rac ON rac.rf_id = doc.rf_id
                            INNER JOIN assignor_and_assignee aaa ON aaa.assignor_and_assignee_id = ee.assignor_and_assignee_id
                            LEFT JOIN representative r ON r.representative_id = aaa.representative_id
                            WHERE 
                                rac.convey_ty IN ($securityTypes)
                                AND apt.recorded_assignor_and_assignee_id IN ($companyAssignorAndAssigneeIDsImploded)
                                AND doc.appno_doc_num IN ($clientOwnedAssetsImploded)
                            GROUP BY doc.appno_doc_num, eeName
                        ) s
                        WHERE NOT EXISTS (
                            SELECT 1
                            FROM (
                                SELECT 
                                    doc.appno_doc_num,
                                    IF(r.representative_id <> '', r.representative_name, aaa.name) AS eeName
                                FROM assignor aor
                                INNER JOIN db_new_application.activity_parties_transactions apt ON apt.rf_id = aor.rf_id 
                                INNER JOIN documentid doc ON doc.rf_id = aor.rf_id
                                INNER JOIN representative_assignment_conveyance rac ON rac.rf_id = doc.rf_id
                                INNER JOIN assignor_and_assignee aaa ON aaa.assignor_and_assignee_id = aor.assignor_and_assignee_id
                                LEFT JOIN representative r ON r.representative_id = aaa.representative_id
                                WHERE 
                                    rac.convey_ty IN ($releaseTypes)
                                    AND apt.recorded_assignor_and_assignee_id IN ($companyAssignorAndAssigneeIDsImploded)
                                    AND doc.appno_doc_num IN ($clientOwnedAssetsImploded)
                            ) r
                            WHERE r.appno_doc_num = s.appno_doc_num AND r.eeName = s.eeName
                        )";

                        echo "COLLATERALISATION<br>$seQuery<br>";
                        $resultseQuery = $con->query($seQuery);

                        $collaterializedAssets = [];
                        if ($resultseQuery && $resultseQuery->num_rows > 0) {
                            while ($row = $resultseQuery->fetch_object()) {
                                $collaterializedAssets[] = '"' . $row->appno_doc_num . '"';
                            }
                        }
                        print_r($collaterializedAssets);

                        if (count($collaterializedAssets) > 0) {
                            $assetsIn = implode(',', $collaterializedAssets);

                            $queryCollateralized = "
                            INSERT IGNORE INTO {$dbApplication}.dashboard_items
                            (representative_id, assignor_id, type, patent, application, rf_id, total)
                            SELECT 
                                {$companyID}, 0, {$type}, 
                                MAX(d.grant_doc_num), d.appno_doc_num, 0, 0
                            FROM {$dbUSPTO}.documentid AS d
                            WHERE 
                                d.appno_date >= {$YEARThreshold}
                                AND d.appno_doc_num IN ($assetsIn)
                            GROUP BY d.appno_doc_num";

                            echo $queryCollateralized;
                            $con->query($queryCollateralized);

                            echo 'AFFECTED ROWS: ' . $con->affected_rows;
                        }


                        /**
                         *  Maintainance Budget
                         */
                        $type = 35;
                        $currentDate = new DateTime('now');
                        $FORMAT = 'Y-m-d';
                        $graceEndDate = $currentDate->modify('+6 month')->format($FORMAT);
                        $currentDate = new DateTime('now');
                        $dueDate = $currentDate->modify('-6 month')->format($FORMAT);
                        $currentDate = new DateTime('now');
                        $formatCurrentDate = $currentDate->format($FORMAT);

                        // $queryEntityStatus = "SELECT MAX(entity_status) AS entity_status FROM db_patent_maintainence_fee.event_maintainence_fees WHERE appno_doc_num IN (".$implodeAssetsList.") LIMIT 1";

                        $defaultEntityStatus = 'N';

                        $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, event_code, total) SELECT ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code";
                        //echo $queryMaintainenceBudget;die;
    
                        
                        $con->query($queryMaintainenceBudget);


                        $queryMaintainenceBudget = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, event_code, total)
                        SELECT ".$companyID.", 0, ".$type.", patent, appno_doc_num, 0, event_code, fees_amount FROM (
                        SELECT CASE 
                        WHEN MAX(grant_doc_num) LIKE 'D0%' THEN CONCAT('D', SUBSTRING(MAX(grant_doc_num), 3))
                        ELSE MAX(grant_doc_num)
                    END AS patent, appno_doc_num, tempAll.event_code, emcf.fees_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_patent_application_bibliographic.application_grant AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code WHERE appno_doc_num NOT IN (SELECT application FROM db_new_application.dashboard_items
                        where representative_id = ".$companyID." AND type = ".$type." )) AS ALLTEMP WHERE event_code <> '' AND fees_amount IS NOT NULL";


                        

                        //echo $queryMaintainenceBudget;die;
                        $con->query($queryMaintainenceBudget);



                        $con->query("DELETE FROM db_new_application.maintainence_assets WHERE company_id = ".$companyID);

                        $con->query("INSERT IGNORE INTO db_new_application.maintainence_assets(company_id, asset, asset_type, grant_doc_num, appno_doc_num, grant_date, payment_due, payment_grace, fee_code, fee_amount, type, fee_code_surcharge, fee_surcharge)
                        SELECT ".$companyID.", grant_doc_num, 0, grant_doc_num, appno_doc_num, grant_date, due_date, grace_end_date, CASE WHEN tempAll.event_code = 'M1551' THEN 1551 WHEN tempAll.event_code = 'M2551' THEN 2551 WHEN tempAll.event_code = 'M3551' THEN 3551 WHEN tempAll.event_code = 'M1552' THEN 1552 WHEN tempAll.event_code = 'M2552' THEN 2552 WHEN tempAll.event_code = 'M3552' THEN 3552 WHEN tempAll.event_code = 'M1553' THEN 1553  WHEN tempAll.event_code = 'M2553' THEN 2553 WHEN tempAll.event_code = 'M3553' THEN 3553 ELSE 0 END, emcf.fees_amount, CASE WHEN tempAll.event_code = 'M1551' THEN 1 WHEN tempAll.event_code = 'M2551' THEN 1 WHEN tempAll.event_code = 'M3551' THEN 1 WHEN tempAll.event_code = 'M1552' THEN 2 WHEN tempAll.event_code = 'M2552' THEN 2 WHEN tempAll.event_code = 'M3552' THEN 2 WHEN tempAll.event_code = 'M1553' THEN 3  WHEN tempAll.event_code = 'M2553' THEN 3 WHEN tempAll.event_code = 'M3553' THEN 3 ELSE 0 END AS type, CASE WHEN tempAll.event_code = 'M1551' THEN 1554 WHEN tempAll.event_code = 'M2551' THEN 2554 WHEN tempAll.event_code = 'M3551' THEN 3554 WHEN tempAll.event_code = 'M1552' THEN 1555 WHEN tempAll.event_code = 'M2552' THEN 2555 WHEN tempAll.event_code = 'M3552' THEN 3555 WHEN tempAll.event_code = 'M1553' THEN 1556  WHEN tempAll.event_code = 'M2553' THEN 2556 WHEN tempAll.event_code = 'M3553' THEN 3556 ELSE 0 END AS surcharge_code,
                        CASE WHEN tempAll.event_code = 'M1551' THEN 500 WHEN tempAll.event_code = 'M2551' THEN 250 WHEN tempAll.event_code = 'M3551' THEN 125 WHEN tempAll.event_code = 'M1552' THEN 500 WHEN tempAll.event_code = 'M2552' THEN 250 WHEN tempAll.event_code = 'M3552' THEN 125 WHEN tempAll.event_code = 'M1553' THEN 500  WHEN tempAll.event_code = 'M2553' THEN 250 WHEN tempAll.event_code = 'M3553' THEN 125 ELSE 0 END AS surcharge_amount  FROM ( SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, CASE WHEN fees = '3.5' AND status = 'N' THEN 'M1551' WHEN fees = '3.5' AND status = 'Y' THEN 'M2551'  WHEN fees = '3.5' AND status = 'M' THEN 'M3551'  WHEN fees = '7.5' AND status = 'N' THEN 'M1552'  WHEN fees = '7.5' AND status = 'Y' THEN 'M2552'  WHEN fees = '7.5' AND status = 'M' THEN 'M3552'  WHEN fees = '11.5' AND status = 'N' THEN 'M1553'  WHEN fees = '11.5' AND status = 'Y' THEN 'M2553' WHEN fees = '11.5' AND status = 'M' THEN 'M3553' ELSE '' END AS event_code, due_date, grace_end_date FROM (SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '3.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 42 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 54 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1551','M2551', 'M3551', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '7.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 90 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 102 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate  FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1552','M2552', 'M3552', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND currentDate >= due_date AND currentDate <= grace_end_date UNION SELECT grant_doc_num, appno_doc_num, grant_date, appno_date, fees, status, due_date, grace_end_date FROM (SELECT MAX(doc.grant_doc_num) AS grant_doc_num, MAX(doc.appno_doc_num) AS appno_doc_num, MAX(grant_date) AS grant_date, MAX(appno_date) AS appno_date, '11.5' AS fees, '".$defaultEntityStatus."' AS status, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 138 MONTH), '%Y-%m-%d') AS due_date, date_format(DATE_ADD(MAX(doc.grant_date), INTERVAL 150 MONTH), '%Y-%m-%d') AS grace_end_date,  '".$formatCurrentDate."' AS currentDate FROM db_uspto.documentid AS doc WHERE doc.appno_doc_num IN (".$clientOwnedAssetsImploded.") AND doc.appno_doc_num NOT IN (SELECT appno_doc_num FROM db_patent_maintainence_fee.event_maintainence_fees
                        WHERE appno_doc_num IN (".$clientOwnedAssetsImploded.") AND event_code IN ('M1553','M2553', 'M3553', 'EXP', 'EXP.') ) GROUP BY doc.appno_doc_num) AS temp WHERE grant_doc_num <> '' AND grant_doc_num NOT LIKE 'D%' AND  currentDate >= due_date AND currentDate <= grace_end_date ) AS temp1) AS tempAll INNER JOIN db_patent_maintainence_fee.event_maintainence_code_fees AS emcf ON emcf.event_code = tempAll.event_code ");



                        /**
                         * Names
                         */
                        $type = 17;
                        $queryIncorrectNames = "
                            INSERT IGNORE INTO {$dbApplication}.dashboard_items (
                                representative_id, assignor_id, type, patent, application, rf_id, total
                            )
                            SELECT 
                                {$companyID} AS representative_id,
                                apt.recorded_assignor_and_assignee_id AS assignor_id,
                                {$type} AS type,
                                MAX(doc.grant_doc_num) AS patent,
                                MAX(doc.appno_doc_num) AS application,
                                rac.rf_id,
                                ".count($clientOwnedAssets)." AS total
                            FROM db_new_application.activity_parties_transactions AS apt
                            INNER JOIN db_uspto.documentid AS doc 
                                ON doc.rf_id = apt.rf_id
                            INNER JOIN db_uspto.representative_assignment_conveyance AS rac 
                                ON rac.rf_id = apt.rf_id
                            INNER JOIN db_uspto.assignee AS ass 
                                ON ass.rf_id = rac.rf_id
                            INNER JOIN db_uspto.conveyance AS con 
                                ON con.convey_name = rac.convey_ty AND con.is_ota = 1
                            INNER JOIN db_uspto.assignor_and_assignee AS aaa 
                                ON aaa.assignor_and_assignee_id = ass.assignor_and_assignee_id
                            INNER JOIN db_uspto.representative AS rep 
                                ON rep.representative_id = aaa.representative_id
                            WHERE 
                                apt.company_id = {$companyID}
                                AND doc.appno_doc_num IN (".$clientOwnedAssetsImploded.")
                                AND rep.representative_name <> ''
                                AND LOWER(aaa.name) <> LOWER(rep.representative_name)
                            GROUP BY 
                                apt.recorded_assignor_and_assignee_id, 
                                doc.appno_doc_num, 
                                rac.rf_id
                        ";
                        $con->query($queryIncorrectNames);

                        /**
                         * Encumbrances
                        */   
                        $type = 18;
                        $encumberedAssets = array();
                        $queryEncumbrances = "
                            INSERT IGNORE INTO {$dbApplication}.dashboard_items (
                                representative_id, assignor_id, type, patent, application, rf_id, total
                            )
                            SELECT 
                                {$companyID} AS representative_id,
                                aor.assignor_and_assignee_id,
                                {$type} AS type,
                                d.grant_doc_num,
                                d.appno_doc_num,
                                rac.rf_id,
                                ".count($clientOwnedAssets)." AS total
                            FROM db_uspto.documentid AS d
                            INNER JOIN db_uspto.representative_assignment_conveyance AS rac 
                                ON rac.rf_id = d.rf_id 
                                AND rac.convey_ty IN (
                                    'license', 'courtappointment', 'courtorder', 'govern', 'option', 'other'
                                )
                            INNER JOIN db_new_application.activity_parties_transactions AS aor 
                                ON aor.rf_id = rac.rf_id 
                            WHERE 
                                d.appno_doc_num IN ({$clientOwnedAssetsImploded})
                                AND aor.recorded_assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.")
                            GROUP BY 
                                d.appno_doc_num
                            ";
                        $con->query($queryEncumbrances);


                        /**
                         * To Divest
                         * the company does not have any patents in that letter in the most recent 3 years.
                         * OR
                         * the company has no more than 5 patents in the past 5 years.
                         */
                        $type = 21;
                        $allUnnecessaryAssets = array();
                        $currentDate = new DateTime();
                        $currentYear = $currentDate->format('Y');
                        $pastYear3 = $currentDate->modify('-3 year')->format('Y');
                        $pastYear5 = $currentDate->modify('-2 year')->format('Y');

                        $queryCPCSection = "
                            SELECT section FROM (
                                -- Section from patent applications with granted numbers
                                SELECT cpc.section 
                                FROM db_patent_application_bibliographic.patent_cpc AS cpc
                                INNER JOIN (
                                    SELECT doc.appno_doc_num 
                                    FROM db_uspto.documentid AS doc 
                                    WHERE doc.appno_date >= {$yearThreshold} 
                                      AND doc.appno_doc_num IN ({$clientOwnedAssetsImploded})
                                      AND doc.grant_doc_num <> ''
                                    GROUP BY doc.appno_doc_num
                                ) AS granted 
                                ON granted.appno_doc_num = cpc.application_number 
                                WHERE cpc.type = 0 
                                GROUP BY granted.appno_doc_num, cpc.section

                                UNION

                                -- Section from patent applications without granted numbers
                                SELECT cpc.section 
                                FROM db_patent_grant_bibliographic.application_cpc AS cpc
                                INNER JOIN (
                                    SELECT doc.appno_doc_num 
                                    FROM db_uspto.documentid AS doc 
                                    WHERE doc.appno_date >= {$yearThreshold}
                                      AND doc.appno_doc_num IN ({$clientOwnedAssetsImploded})
                                      AND doc.grant_doc_num = ''
                                    GROUP BY doc.appno_doc_num
                                ) AS not_granted 
                                ON not_granted.appno_doc_num = cpc.application_number 
                                WHERE cpc.type = 0 
                                GROUP BY not_granted.appno_doc_num, cpc.section
                            ) AS sections
                            GROUP BY section
                            ORDER BY section ASC
                        ";

                        $allSections = array();
                        $resultAllSections = $con->query($queryCPCSection);
                        if($resultAllSections && $resultAllSections->num_rows > 0) {
                            while($rowAsset = $resultAllSections->fetch_object()) {
                                array_push($allSections, '"'.$rowAsset->section.'"');
                            }
                        } 
                        $unNecessarySection = [];

                        $queryUnnecessarySections = "SELECT 
                            section,
                            SUM(CASE WHEN app_year BETWEEN {$pastYear3} AND {$currentYear} THEN 1 ELSE 0 END) AS count_3_years,
                            SUM(CASE WHEN app_year BETWEEN {$pastYear5} AND {$currentYear} THEN 1 ELSE 0 END) AS count_5_years
                        FROM (
                            SELECT cpc.section, YEAR(doc.appno_date) AS app_year
                            FROM db_patent_application_bibliographic.patent_cpc AS cpc
                            INNER JOIN db_uspto.documentid AS doc 
                                ON doc.appno_doc_num = cpc.application_number
                            WHERE cpc.type = 0 AND doc.appno_doc_num IN ({$clientOwnedAssetsImploded})

                            UNION ALL

                            SELECT cpc.section, YEAR(doc.appno_date) AS app_year
                            FROM db_patent_grant_bibliographic.application_cpc AS cpc
                            INNER JOIN db_uspto.documentid AS doc 
                                ON doc.appno_doc_num = cpc.application_number
                            WHERE cpc.type = 0 AND doc.appno_doc_num IN ({$clientOwnedAssetsImploded})
                        ) AS section_year_data
                        GROUP BY section
                        HAVING count_3_years = 0 OR count_5_years <= 5
                        ORDER BY section";

                        $resultUnnecessary = $con->query($queryUnnecessarySections);
                        if ($resultUnnecessary && $resultUnnecessary->num_rows > 0) {
                            while ($row = $resultUnnecessary->fetch_object()) {
                                $unNecessarySection[] = '"' . $row->section . '"';
                            }
                        }

                        if(count($unNecessarySection) > 0) {
                            $sectionList = implode(',', $unNecessarySection);
                            $totalAssets = count($clientOwnedAssets); 

                            $queryUnNecessaryPatents = "
                                INSERT IGNORE INTO {$dbApplication}.dashboard_items 
                                    (representative_id, assignor_id, type, patent, application, rf_id, total)
                                SELECT 
                                    {$companyID} AS representative_id,
                                    0 AS assignor_id,
                                    {$type} AS type,
                                    temp.grant_doc_num AS patent,
                                    temp.appno_doc_num AS application,
                                    '' AS rf_id,
                                    {$totalAssets} AS total
                                FROM (
                                    -- Patents with grant numbers (granted applications)
                                    SELECT 
                                        cpc.application_number,
                                        doc.grant_doc_num,
                                        doc.appno_doc_num
                                    FROM db_uspto.documentid AS doc
                                    INNER JOIN db_patent_application_bibliographic.patent_cpc AS cpc
                                        ON doc.appno_doc_num = cpc.application_number
                                    WHERE 
                                        doc.appno_date >= {$yearThreshold}
                                        AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
                                        AND doc.grant_doc_num <> ''
                                        AND cpc.type = 0
                                        AND cpc.section IN ({$sectionList})
                                    GROUP BY doc.appno_doc_num

                                    UNION

                                    -- Applications not yet granted (no grant_doc_num)
                                    SELECT 
                                        cpc.application_number,
                                        '' AS grant_doc_num,
                                        doc.appno_doc_num
                                    FROM db_uspto.documentid AS doc
                                    INNER JOIN db_patent_grant_bibliographic.application_cpc AS cpc
                                        ON doc.appno_doc_num = cpc.application_number
                                    WHERE 
                                        doc.appno_date >= {$yearThreshold}
                                        AND doc.appno_doc_num IN ({$implodedOWNEDAssetsLIST})
                                        AND doc.grant_doc_num = ''
                                        AND cpc.type = 0
                                        AND cpc.section IN ({$sectionList})
                                    GROUP BY doc.appno_doc_num
                                ) AS temp
                                GROUP BY temp.application_number
                            ";

                            $con->query($queryUnNecessaryPatents);
                        }

                        /**
                        * Late Maintainence (Owned Assets)  
                        */
                        $type = 23;
                        $queryLateMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", emf.grant_doc_num, tawb.appno_doc_num, '' AS rf_id, ".count($clientOwnedAssets)."                               
                        FROM db_new_application.assets as tawb
                        INNER JOIN db_patent_maintainence_fee.event_maintainence_fees AS emf ON emf.appno_doc_num = tawb.appno_doc_num
                        WHERE company_id  = ".$companyID."
                        AND tawb.appno_doc_num IN  (".$implodedOWNEDAssetsLIST.")
                        AND emf.event_code IN ('F176', 'M1554', 'M1555', 'M1556', 'M1557', 'M1558', 'M176', 'M177', 'M178', 'M181', 'M182', 'M186', 'M187', 'M188', 'M2554', 'M2555', 'M2556', 'M2558', 'M277', 'M281', 'M282', 'M286', 'M3554', 'M3555', 'M3556', 'M3557', 'M3558') GROUP BY tawb.appno_doc_num";
                        
                        $con->query($queryLateMaintainence);
                        
                    } /* condition of $clientOwnedAssets*/

                    /**
                     *  Assets Abandoned
                     */
                    if(count($expiredAssets) > 0) { 
                        $mergeFilledAndOTAAssets = array_merge($originalApplicantAssets, $ownedAfterSold); 


                        $grantApplications = array();

                        if(count($mergeFilledAndOTAAssets) > 0) {
                            $implodeAssets = implode(',', $mergeFilledAndOTAAssets);

                            $findPatentsAssets = "
                                SELECT MAX(appno_doc_num) AS appno_doc_num 
                                FROM db_uspto.documentid 
                                WHERE grant_doc_num <> '' 
                                  AND appno_doc_num IN ($implodeAssets)
                                GROUP BY appno_doc_num 

                                UNION

                                SELECT MAX(appno_doc_num) AS appno_doc_num 
                                FROM db_patent_application_bibliographic.application_grant 
                                WHERE appno_doc_num IN ($implodeAssets)
                                GROUP BY appno_doc_num
                            ";

                            $resultGrantApplications = $con->query($findPatentsAssets);

                            if ($resultGrantApplications && $resultGrantApplications->num_rows > 0) {
                                $grantApplications = [];
                                while ($rowAsset = $resultGrantApplications->fetch_object()) {
                                    $grantApplications[] = '"' . $rowAsset->appno_doc_num . '"';
                                }
                            }
                        }

                        $remainingAssets = array();
                        if(count($grantApplications) > 0) {
                            $queryExpiredStatusAssets = "SELECT DISTINCT appno_doc_num AS application FROM db_uspto.application_status  WHERE status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362') AND appno_doc_num IN (".implode(',', $grantApplications).") ";
    
                        
                            $resultAllExpiredAssetsList = $con->query($queryExpiredStatusAssets); 
                            if($resultAllExpiredAssetsList && $resultAllExpiredAssetsList->num_rows > 0) {
                                while($rowAsset = $resultAllExpiredAssetsList->fetch_object()) {
                                    array_push($remainingAssets, '"'.$rowAsset->application.'"');
                                }
                            }
                        } 

                        $queryAbandonedAssets = "SELECT appno_doc_num FROM (SELECT appno_doc_num, status, MAX(status_date) FROM ".$dbUSPTO.".application_status WHERE appno_doc_num IN (".implode(',', $mergeFilledAndOTAAssets).") ";

                        if(count($grantApplications) > 0) {
                            $queryAbandonedAssets .= " AND appno_doc_num NOT IN (".implode(',', $grantApplications).") ";
                        }
                        
                        
                        
                        $queryAbandonedAssets .= " AND status IN ('Patent Expired Due to NonPayment of Maintenance Fees Under 37 CFR 1.362', 
                        'Provisional Application Expired', 
                        'Final Rejection Mailed', 
                        'Expressly Abandoned  --  During Publication Process', 
                        'Expressly Abandoned  --  During Examination', 
                        'Abandoned  --  After Examiner\'s Answer or Board of Appeals Decision', 
                        'Abandoned  --  Failure to Pay Issue Fee', 
                        'Abandoned  --  File-Wrapper-Continuation Parent Application',
                        'Abandoned  --  Failure to Respond to an Office Action',  
                        'Abandoned  --  Incomplete (Filing Date Under Rule 53 (b) - PreExam)',
                        'Abandoned  --  Incomplete Application (Pre-examination)', 
                        
                        'Abandonment for Failure to Correct Drawings/Oath/NonPub Request') GROUP BY  appno_doc_num) AS temp GROUP BY  appno_doc_num";
                        echo "ABABABABABABABBAABABABABBAB";
                        echo $queryAbandonedAssets;
                        $resultRemainingAssetsList = $con->query($queryAbandonedAssets);
                        
                        if($resultRemainingAssetsList && $resultRemainingAssetsList->num_rows > 0) {
                            while($rowAsset = $resultRemainingAssetsList->fetch_object()) {
                                array_push($remainingAssets, '"'.$rowAsset->appno_doc_num.'"');
                            }
                        } 
                        
                        if(count($remainingAssets) > 0) {

                            $type = 36;
                            $remainingAssetsList = implode(',', $remainingAssets);
                            $queryPendingApplications = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (organisation_id, representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$organisationID.", ".$companyID.", 0, ".$type.", grant_doc_num, appno_doc_num, 0, 0  FROM ".$dbUSPTO.".documentid AS d INNER JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id WHERE d.appno_date >= ".$YEARThreshold."  AND d.appno_doc_num IN ({$remainingAssetsList}) AND company_id = ".$companyID." GROUP BY d.appno_doc_num ";
                            $con->query($queryPendingApplications);

                            $publicationAndGrantQuery = "
                                INSERT IGNORE INTO {$dbApplication}.dashboard_items 
                                    (representative_id, assignor_id, type, patent, application, rf_id, total)
                                SELECT 
                                   {$companyID}, 0, {$type}, grant_doc_num, appno_doc_num, 0, 0
                                FROM (
                                    SELECT grant_doc_num, appno_doc_num 
                                    FROM db_patent_application_bibliographic.application_grant 
                                    WHERE appno_date > {$YEARThreshold} 
                                      AND appno_doc_num IN ({$remainingAssetsList})
                                    UNION
                                    SELECT grant_doc_num, appno_doc_num 
                                    FROM db_patent_application_bibliographic.application_publication 
                                    WHERE appno_date > {$YEARThreshold} 
                                      AND appno_doc_num IN ({$remainingAssetsList})
                                ) AS combined
                                WHERE appno_doc_num NOT IN (
                                    SELECT application 
                                    FROM {$dbApplication}.dashboard_items 
                                    WHERE organisation_id = {$organisationID} 
                                      AND representative_id = {$companyID} 
                                      AND type = {$type}
                                )
                                GROUP BY appno_doc_num
                            ";
                            $con->query($publicationAndGrantQuery);
                        }
                    }
                    /**
                     * Top Proliferate Inventors
                     */
                    $type = 39;
                    $implodeOriginalAssets = implode(',', $originalApplicantAssets);
                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, name, assignor_id, type, patent, application ) SELECT ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id,  ".$type.", ag.grant_doc_num, ag.appno_doc_num FROM db_patent_application_bibliographic.inventor AS inv 
                        INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
                        INNER JOIN db_patent_application_bibliographic.application_grant AS ag ON BINARY ag.appno_doc_num = BINARY inv.appno_doc_num 
                        WHERE inv.appno_doc_num IN (".$implodeOriginalAssets.") GROUP BY aaa.name, ag.appno_doc_num ";
                         
                    $con->query($queryTopInventor);

                    $queryTopInventor ="INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, name, assignor_id, type, patent, application ) SELECT ".$companyID.", IF(representative_name <> '' , representative_name, aaa.name), aaa.assignor_and_assignee_id, ".$type.", '' AS patent, ap.appno_doc_num FROM db_patent_grant_bibliographic.inventor_new AS inv INNER JOIN db_patent_application_bibliographic.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = inv.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id
                    INNER JOIN db_patent_application_bibliographic.application_publication AS ap ON ap.appno_doc_num = inv.appno_doc_num
                    WHERE inv.appno_doc_num IN (".$implodeOriginalAssets.") AND inv.appno_doc_num NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY application) GROUP BY aaa.name, ap.appno_doc_num ";
                     
                    $con->query($queryTopInventor);

                    /**
                     * Top Law Firms
                     */
                    $type = 40;
                    $queryLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total, lawfirm, lawfirm_id) SELECT  ".$companyID.", 0, ".$type.", patent, application, rf_id, 0,
                    (CASE WHEN representative_name <> '' THEN representative_name 
                        WHEN lfName <> '' THEN lfName
                        ELSE cName END) AS lawfirm, law_firm_id FROM (
                        SELECT c.rf_id, c.cname as cName, lf.law_firm_id,  lf.name as lfName, rlf.representative_name, MAX(doc.grant_doc_num) AS patent, MAX(doc.appno_doc_num) AS application from db_new_application.activity_parties_transactions AS apt
                        INNER JOIN ".$dbUSPTO.".correspondent AS c ON c.rf_id = apt.rf_id
                        INNER JOIN ".$dbUSPTO.".assignee AS ass ON ass.rf_id = apt.rf_id
                        LEFT JOIN ".$dbUSPTO.".law_firm  as lf ON c.cname = lf.name
                        LEFT JOIN ".$dbUSPTO.".representative_law_firm AS rlf ON rlf.representative_id = lf.representative_id
                        INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id 
                        where ass.assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.") and apt.company_id = ".$companyID." AND apt.exec_dt >= '2000-01-01' AND c.cname <> ''
                        GROUP BY apt.rf_id) AS temp GROUP BY rf_id, lawfirm";
                    
                    $con->query($queryLawFirms);

                    /**
                     * Top Lenders
                     */
                    $type = 41;
                    $queryLenders = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID.", apt.assignor_and_assignee_id, ".$type.", d.grant_doc_num,  d.appno_doc_num, apt.rf_id, 0 FROM ".$dbUSPTO.".documentid AS d 
                            JOIN ".$dbApplication.".activity_parties_transactions AS apt ON apt.rf_id = d.rf_id
                            WHERE apt.recorded_assignor_and_assignee_id IN (".$implodeAssignorAndAssigneeIDs.") 
                            AND d.appno_doc_num IN (".$implodePatentedAssetsList.") AND d.appno_date >= ".$YEARThreshold."  AND apt.activity_id IN (5, 12) AND apt.company_id = ".$companyID."
                            GROUP BY apt.rf_id, apt.assignor_and_assignee_id";

                    $con->query($queryLenders);

                    $con->query("DELETE FROM  `db_uspto`.`temp_application_inventor_count` WHERE company_id = ".$companyID);
                    $con->query("DELETE FROM  `db_uspto`.`temp_application_employee_count` WHERE company_id = ".$companyID);
                    $con->query("DELETE FROM db_new_application.assets WHERE layout_id = 1 AND company_id = ".$companyID);
                    $con->query("DELETE FROM db_new_application.dashboard_items WHERE type = 1 AND representative_id = ".$companyID);


                    /**
                     * Addresses
                     */
                    $type = 19;
                    if(count($collaterializedAssets) > 0) {
                        $queryCollateralized =  "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.",  patent, application, '' AS rf_id, ".count($clientOwnedAssets)." FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." and type = 30 
                        and mode = 0 AND application NOT IN (SELECT application FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." and type = 34 AND mode = 0) "; 
                        //echo $queryCollateralized;
                        $con->query($queryCollateralized);
                    }

                    /**
                     * To Record
                     */
                    if(count($applicantAssets) > 0) {
                        $type = 22;
                        $queryApplicantAssets = "SELECT application, patent FROM ".$dbApplication.".dashboard_items WHERE type = 31 AND representative_id = ".$companyID." GROUP BY application";

                        $resultApplicant = $con->query($queryApplicantAssets);
                            
                        if($resultApplicant && $resultApplicant->num_rows > 0) { 
                            $applicantAssets = array();
                            $patent = array();
                            $assetRows = array();
                            while($rowApplicant = $resultApplicant->fetch_object()) {
                                array_push( $applicantAssets, '"'.$rowApplicant->application.'"');
                                array_push( $patent, '"'.$rowApplicant->patent.'"');
                                array_push( $assetRows, $rowApplicant);
                            }


                            $queryDocumenttAssets = "SELECT appno_doc_num FROM ".$dbUSPTO.".documentid WHERE appno_doc_num IN (".implode(',', $applicantAssets).") GROUP BY appno_doc_num";
                            

                            $resultDocument = $con->query($queryDocumenttAssets);

                            $documentAssets = array();
                            $remainingApplicantAssets = array();
                            
                            if($resultDocument && $resultDocument->num_rows > 0) { 
                                while($rowDoc = $resultDocument->fetch_object()) {
                                    array_push( $documentAssets, '"'.$rowDoc->appno_doc_num.'"');
                                }
                            }

                            $remainingApplicantAssets = array_diff($applicantAssets, $documentAssets);

                            if(count($remainingApplicantAssets) > 0) {

                                $queryDocumenttAssets = "SELECT grant_doc_num FROM ".$dbUSPTO.".documentid WHERE grant_doc_num IN (".implode(',', $patent).") AND appno_doc_num NOT IN (".implode(',', $documentAssets).") GROUP BY grant_doc_num";

                                $resultDocument = $con->query($queryDocumenttAssets);

                                $documentPatentAssets = array();
                                //$remainingApplicantAssets = array();
                                
                                if($resultDocument && $resultDocument->num_rows > 0) { 
                                    while($rowDoc = $resultDocument->fetch_object()) {
                                        array_push( $documentPatentAssets, '"'.$rowDoc->grant_doc_num.'"');
                                    }
                                }


                                if(count($documentPatentAssets) > 0) {
                                    foreach($documentPatentAssets as $pAsset) {
                                        foreach($assetRows as $aRows) {
                                            if($pAsset == '"'.$aRows->patent.'"') {
                                                array_push($documentAssets, '"'.$aRows->application.'"');
                                                break;
                                            }
                                        }
                                    }
                                }

                                $remainingApplicantAssets = array_diff($applicantAssets, $documentAssets);

                                if(count($expiredAssets) > 0 && count($remainingApplicantAssets) > 0) { 
                                    $remainingApplicantAssets = array_diff($remainingApplicantAssets, $expiredAssets);
                                }
                                

                                if(count($remainingApplicantAssets) > 0) {
                                    $queryUnAssignedAssets = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID." AS representative_id, assignor_id, ".$type.",  patent, application,  rf_id, ".count($applicantAssets)."  FROM (SELECT * FROM ".$dbApplication.".dashboard_items  WHERE type = 31 AND representative_id = ".$companyID." AND application IN  (".implode(',', $remainingApplicantAssets).") GROUP BY application) AS temp";
                                    $con->query($queryUnAssignedAssets);

                                    /**
                                     * Delete To record assets from broken chain of title
                                    */

                                     $queryToRecordFromDelete = "DELETE FROM ".$dbApplication.".dashboard_items WHERE type = 1 AND representative_id = ".$companyID." AND application IN (SELECT * FROM ( SELECT application FROM ".$dbApplication.".dashboard_items WHERE type = ".$type." AND representative_id = ".$companyID." ) AS temp )";

                                    echo $queryToRecordFromDelete."chain delete to record";

                                    $con->query($queryToRecordFromDelete); 
                                }
                            }
                        }
                    }
                    /**
                     * To be Monetized
                     * The list of Divest assets, identify those with over 10 F. Citations  
                     * From the list of Owned assets identify those with filing year over 12 years ago, AND have over 10 F. Citations
                     */
                    $type = 20;
                    $currentDate = new DateTime('now');
                    $pastYear12 = $currentDate->modify('-12 year')->format('Y');

                    $queryDivestFCitation = " SELECT cp.patent_number, COUNT(cp.assignee_id) AS counter FROM ".$dbApplication.".citing_patents_with_assignee AS cp WHERE cp.patent_number IN (SELECT patent FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND patent <> '' AND representative_id = ".$companyID." GROUP BY patent) GROUP BY cp.patent_number HAVING counter > 10 ";

                    echo "DIVESTED QUEREYYYYY: ".$queryDivestFCitation."<br/>";
                    $resultCitations = $con->query($queryDivestFCitation);
                    $divestAssets = array();
                        
                    if($resultCitations && $resultCitations->num_rows > 0) { 
                        while($rowDoc = $resultCitations->fetch_object()) {
                            array_push( $divestAssets, '"'.$rowDoc->patent_number.'"');
                        }
                    }

                    $clientOwnedQuery = "SELECT patent FROM db_new_application.dashboard_items WHERE representative_id = ".$companyID." AND type = 30 AND patent <> '' GROUP BY patent";
                    
                    $clientOwnedPatents = array();
                    $resultClientOwned = $con->query($clientOwnedQuery);
                    if($resultClientOwned && $resultClientOwned->num_rows > 0) {
                        while($rowAsset = $resultClientOwned->fetch_object()) {
                            array_push($clientOwnedPatents, '"'.$rowAsset->patent.'"');
                        }
                    } 
                    

                    $queryCitationAssets = "SELECT cp.patent_number, COUNT(DISTINCT cp.assignee_id) AS counter FROM ".$dbApplication.".citing_patents_with_assignee AS cp WHERE cp.patent_number IN (".implode(',', $clientOwnedPatents).") 
                        GROUP BY cp.patent_number
                        HAVING counter > 10 ";

                    echo "queryCitationAssets QUEREYYYYY: ".$queryCitationAssets."<br/>";

                    $resultCitations = $con->query($queryCitationAssets);
                    
                    $citationAssets = array();
                    if($resultCitations && $resultCitations->num_rows > 0) { 
                        while($rowDoc = $resultCitations->fetch_object()) {
                            array_push( $citationAssets, '"'.$rowDoc->patent_number.'"');
                        }
                    }


                    echo "DIVESTED: ".count($divestAssets)."<br/>";
                    echo "citationAssets: ".count($citationAssets)."<br/>";

                    $divestedCounter = "SELECT patent FROM ".$dbApplication.".dashboard_items AS di WHERE di.type = 21 AND patent <> '' AND representative_id = ".$companyID." GROUP BY patent";
                    $resultDivestedCounter = $con->query($divestedCounter);

                    $totalDivested = 0;
                    if($resultDivestedCounter && $resultDivestedCounter->num_rows > 0) { 
                        $totalDivested = $resultDivestedCounter->num_rows;
                    }

                    $countQuery = count($clientOwnedPatents) + $totalDivested;

                    if(count($divestAssets) > 0) {
                        
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application,  total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  ".$countQuery."  FROM ( SELECT grant_doc_num, appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num IN  (".implode(',', $divestAssets).") GROUP BY appno_doc_num ) AS temp GROUP BY appno_doc_num";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application,  total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", patent, application, ".$countQuery."  FROM ( SELECT MAX(grant_doc_num) AS patent, MAX(appno_doc_num) AS application FROM db_uspto.documentid WHERE grant_doc_num IN (".implode(',', $divestAssets).") AND grant_doc_num NOT IN ( SELECT patent FROM db_new_application.dashboard_item WHERE type = ".$type." AND representative_id = ".$companyID." GROUP BY patent ) GROUP BY appno_doc_num ) AS temp GROUP BY application";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";
                    }

                    if(count($citationAssets) > 0) {
                         
                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application,   total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", grant_doc_num, appno_doc_num,  ".$countQuery."  FROM ( SELECT grant_doc_num, appno_doc_num FROM db_patent_application_bibliographic.application_grant WHERE grant_doc_num IN  (".implode(',', $citationAssets).") AND  date_format(appno_date, '%Y') <= ".$pastYear12." ) AS temp GROUP BY appno_doc_num";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>";

                        $queryMonetized = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, total) SELECT ".$companyID." AS representative_id, 0 AS assignor_id, ".$type.", patent, application, ".$countQuery."  FROM ( SELECT MAX(grant_doc_num) AS patent, MAX(appno_doc_num) AS application FROM db_uspto.documentid WHERE grant_doc_num IN (".implode(',', $citationAssets).") AND  date_format(appno_date, '%Y') <= ".$pastYear12."  AND grant_doc_num NOT IN ( SELECT patent FROM db_new_application.dashboard_item WHERE type = ".$type." AND representative_id = ".$companyID." GROUP BY patent ) GROUP BY appno_doc_num ) AS temp GROUP BY application";
                        $con->query($queryMonetized);

                        echo $queryMonetized."<br/>"; 
                    } 

                     /**
                    * Incorrect Recordings
                    */
                    $type = 24;
                     $queryIncorrectRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID." AS representative_id, assignor_id, ".$type.",   patent,  application, rf_id, ".count($companyAllAssets)."  AS total FROM ( SELECT rac.rf_id AS rf_id, doc.grant_doc_num AS patent, doc.appno_doc_num AS application, 0 AS assignor_id
                    FROM ".$dbApplication.".activity_parties_transactions AS apt 
                    INNER JOIN ".$dbUSPTO.".documentid AS doc ON doc.rf_id = apt.rf_id
                    INNER JOIN ".$dbUSPTO.".assignment_conveyance AS rac ON rac.rf_id = apt.rf_id
                    INNER JOIN ".$dbUSPTO.".assignee AS ee ON ee.rf_id = doc.rf_id AND ee.assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.")
                    WHERE apt.company_id = ".$companyID." 
                    AND doc.appno_doc_num IN (".implode(',', $companyAllAssets).")
                    AND rac.convey_ty = 'correct'
                    GROUP BY doc.appno_doc_num, rac.rf_id) AS temp";
                    $con->query($queryIncorrectRecording); 

                    /**
                    * Late Recordings
                    */  
                    $days = 90;
                    $type = 25; 
                    $queryLateRecording = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items (representative_id, assignor_id, type, patent, application, rf_id, total) SELECT ".$companyID." AS representative_id, 0, ".$type.", patent, application, rf_id,  ".count($companyAllAssets)." AS total FROM ( SELECT d.rf_id, d.appno_doc_num AS application, d.grant_doc_num AS patent FROM ".$dbUSPTO.".documentid AS d INNER JOIN (SELECT temp_exec_dt.rf_id, DATEDIFF(ass.record_dt, temp_exec_dt.exec_dt) AS noOfDays   
                    FROM db_new_application.assets as tawb
                    INNER JOIN (
                        SELECT appno_doc_num, rf_id FROM ".$dbUSPTO.".documentid
                        WHERE appno_doc_num IN (".implode(',', $companyAllAssets).")
                        GROUP BY appno_doc_num, rf_id
                    ) AS doc ON doc.appno_doc_num = tawb.appno_doc_num
                    INNER JOIN ".$dbUSPTO.".assignment AS ass ON ass.rf_id = doc.rf_id
                    INNER JOIN LATERAL (
                        SELECT aor.rf_id, MAX(aor.exec_dt) as exec_dt FROM ".$dbUSPTO.".assignor AS aor
                        INNER JOIN (
                            SELECT appno_doc_num, rf_id FROM ".$dbUSPTO.".documentid
                            WHERE appno_doc_num IN (".implode(',', $companyAllAssets).")
                            GROUP BY appno_doc_num, rf_id
                        ) AS doc1 ON doc1.rf_id = aor.rf_id
                        INNER JOIN db_new_application.assets AS tawb1 ON tawb1.appno_doc_num = doc1.appno_doc_num
                        WHERE company_id = ".$companyID." 
                        GROUP BY aor.rf_id
                    ) AS temp_exec_dt ON  temp_exec_dt.rf_id = ass.rf_id
                    INNER JOIN ".$dbUSPTO.".assignee AS ee ON ee.rf_id = ass.rf_id AND ee.assignor_and_assignee_id IN (".$companyAssignorAndAssigneeIDsImploded.")
                    WHERE company_id = ".$companyID." 
                    AND temp_exec_dt.exec_dt >= '".$year."-01-01''
                    GROUP BY temp_exec_dt.rf_id
                    HAVING noOfDays > ".$days.") AS temp ON temp.rf_id = d.rf_id  GROUP BY d.appno_doc_num) AS temp1";  

                    $con->query($queryLateRecording);  

                    /**
                     * Deflated Collateral
                     */
                    $type = 26;
                    $currentDate = new DateTime();
                    $totalAssets = count($expiredAssets);

                    $queryTotalDivested = "SELECT application FROM db_new_application.dashboard_items WHERE type = 33   AND representative_id = ".$companyID." GROUP BY application";

                    $resultDivested = $con->query($queryTotalDivested);

                    if($resultDivested) {
                        $totalAssets += $resultDivested->num_rows;
                    }

                    $queryDeflatedCollateral = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items ( representative_id, assignor_id, type, patent, application, rf_id, total) SELECT  ".$companyID." AS representative_id, 0, ".$type.",  patent, application, rfID, ".$totalAssets." AS total FROM ( SELECT apt.rf_id as rfID, doc.appno_doc_num AS application, MAX(doc.grant_doc_num) AS patent, doc.appno_date, release_rf_id,  timestampdiff(YEAR, doc.appno_date, '".$currentDate->format('Y-m-d')."' ) as yearDiffer,  1 AS expiredStatus, COUNT(*) OVER() AS total FROM db_new_application.activity_parties_transactions AS apt INNER JOIN db_uspto.documentid AS doc ON doc.rf_id = apt.rf_id  WHERE ( ";
                    
                     
                    if( count($expiredAssets) > 0 ){
                        $queryDeflatedCollateral .= " doc.appno_doc_num IN (".implode(',', $expiredAssets).") OR ";
                    }
                    
                    $queryDeflatedCollateral .= " doc.appno_doc_num IN (SELECT application FROM db_new_application.dashboard_items WHERE type = 33   AND representative_id = ".$companyID." GROUP BY application )) AND apt.activity_id IN (5, 12) AND (release_rf_id IS NULL OR apt.release_rf_id = 0)  AND company_id = ".$companyID."  GROUP BY apt.rf_id, doc.appno_doc_num) AS temp  GROUP BY application ";
                    echo "DEFLATED COLLATERAL";
                    echo $queryDeflatedCollateral;
                    $con->query($queryDeflatedCollateral);

                    foreach($allTypes as $type) {
                        if($type == 35) {   
                            /**
                             * Maintainence
                             */

                            $queryInsertMaintainence = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, representative_id, assignor_id, type,  total) SELECT SUM(total) AS number,  ".$companyID." AS representative_id, 0, ".$type.", COUNT(IF(patent <> '', patent, null)) AS total FROM ".$dbApplication.".dashboard_items WHERE  representative_id = ".$companyID." AND type = ".$type;
                            echo $queryInsertMaintainence;
                            $con->query($queryInsertMaintainence);  

                        } else if($type == 38) {
                            /**
                             * Family
                             */
                            $queryFamily = "SELECT cwc.name AS name, COUNT(application_country) AS number FROM (
                                    SELECT grant_doc_num, application_number, application_country FROM db_uspto.assets_family AS af WHERE grant_doc_num IN (
                                        SELECT patent FROM ".$dbApplication.".dashboard_items AS di WHERE type = 30 AND representative_id = ".$companyID." AND patent <> '' GROUP BY patent
                                    ) AND application_country NOT IN ('WO', 'US', 'EP') GROUP BY application_number) AS temp 
                                    INNER JOIN db_uspto.country_with_codes AS cwc ON cwc.country_code = temp.application_country 
                                    GROUP BY application_country ORDER BY number DESC, name ASC ";
                            $patentFamily = array();
                            $resultFamily = $con->query($queryFamily);
                            if($resultFamily && $resultFamily->num_rows > 0) {
                                while($rowAsset = $resultFamily->fetch_object()) {
                                    array_push($patentFamily, $rowAsset);
                                }
                            }
                            echo "FAMILYYYYYYYYYYYYYYYYYYYYY";
                            echo $queryFamily ;
                            $queryInsertFamily = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$companyID.", 0, ".$type.", 0, '".json_encode($patentFamily)."')";
                             echo $queryInsertFamily;
                            $con->query($queryInsertFamily); 

                        }  else if( $type == 39 ) {
                            /**
                             * Inventor
                             */

                            $queryInventor = "SELECT name, COUNT(application) AS number FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY name ORDER BY number DESC, name ASC LIMIT 20"; 

                            $allInventor = array();
                            
                            $resultInventor = $con->query($queryInventor);
                            if($resultInventor && $resultInventor->num_rows > 0) {
                                while($rowAsset = $resultInventor->fetch_object()) {
                                    $name = addslashes($rowAsset->name);
                                    array_push($allInventor, array('name'=>$name, 'number'=>$rowAsset->number)); 
                                }
                            }   

                            $queryInsertParties = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$companyID.", 0, ".$type.", 0, '".json_encode($allInventor)."')";
                            
                            $con->query($queryInsertParties); 

                        } else if($type == 41) {
                            /**
                             * Inventor, Lender
                             */

                            $queryLI = "SELECT inventorName AS name, COUNT(rf_id) AS number FROM (SELECT  IF(aaa.representative_id <> '', r.representative_name, aaa.name) AS inventorName, rf_id FROM ".$dbApplication.".dashboard_items AS di INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = di.assignor_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id WHERE di.type = ".$type." AND di.representative_id = ".$companyID." ) AS temp GROUP BY inventorName ORDER BY number DESC, name ASC  LIMIT 20"; 

                            
                            $parties = array();
                            $resultParties = $con->query($queryLI);
                            if($resultParties && $resultParties->num_rows > 0) {
                                while($rowAsset = $resultParties->fetch_object()) {
                                    $name = addslashes($rowAsset->name);
                                    array_push($parties, array('name'=>$name, 'number'=>$rowAsset->number));
                                }
                            }   
                           
                            $queryInsertParties = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$companyID.", 0, ".$type.", 0, '".json_encode($parties)."')";
                            
                            $con->query($queryInsertParties); 

                        }  else if($type == 40) {
                            /**
                             * LawFirm
                             */
                            $queryLawFirm = "SELECT lawfirm AS name, COUNT(rf_id) AS number FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY lawfirm ORDER BY number DESC, name ASC  LIMIT 20"; 

                            $allLawfirms = array();
                            
                            $resultLawFirm = $con->query($queryLawFirm);
                            if($resultLawFirm && $resultLawFirm->num_rows > 0) {
                                while($rowAsset = $resultLawFirm->fetch_object()) {
                                    $name = addslashes($rowAsset->name);
                                    array_push($allLawfirms, array('name'=>$name, 'number'=>$rowAsset->number)); 
                                }
                            }  
                            
                            $queryInsertLawFirms = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, representative_id, assignor_id, type,  total, other) VALUES ( 0, ".$companyID.", 0, ".$type.", 0, '".json_encode($allLawfirms)."')";
                            
                            $con->query($queryInsertLawFirms); 
                            
                        } else if ($type < 28 && $type != 20) {
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, representative_id, assignor_id, type) SELECT SUM(number + other_number) AS num, 0, total,  representative_id, assignor_id, type FROM (SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$companyID." AS representative_id, 0 AS assignor_id, ".$type." AS type FROM ( SELECT * FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp2 ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        }  else if($type == 34) {
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total,  ".$companyID." AS representative_id, 0, ".$type." FROM ( SELECT application, patent, total FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        } else if($type != 37) {
                            /* $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, organisation_id, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$organisationID." AS organisation_id, ".$companyID." AS representative_id, 0, ".$type." FROM ".$dbApplication.".dashboard_items WHERE organisation_id = ".$organisationID."  AND representative_id = ".$companyID." AND type = ".$type;
                            
                            $con->query($queryInsertCounter);   */
                            $queryInsertCounter = "INSERT IGNORE INTO ".$dbApplication.".dashboard_items_count (number, other_number,  total, representative_id, assignor_id, type) SELECT COUNT(IF(patent <> '', patent, null)) AS number, COUNT(IF(patent = '', application, null)) AS other_number,  total, ".$companyID." AS representative_id, 0, ".$type." FROM ( SELECT application, patent, total FROM ".$dbApplication.".dashboard_items WHERE representative_id = ".$companyID." AND type = ".$type." GROUP BY application ) AS temp";
                            
                            $con->query($queryInsertCounter);  
                        }
                    }

                } /* end of condition $originalList, $expiredAssets, $soldAssets, $applicantAssets, $companyAllTransactionAssets */
            } /* Foreach loop companiesData*/
        } /* End of CompaniesData*/ 
    } /* Second last*/
} /* Last*/