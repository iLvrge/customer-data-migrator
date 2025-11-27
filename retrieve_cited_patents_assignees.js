const request = require('request'),
    exec = require("child_process").exec,
    moment = require("moment"),
    connection = require("./config/index"),
    CitedPatents = require("./models/CitedPatents"),
    CitingPatentWithAssignee = require("./models/CitingPatentWithAssignee"),
    AssigneeOrganizations = require("./models/AssigneeOrganizations");

const Pusher = require("pusher")

const APPID='938985'
const KEY='3252bb191d77e92ddb3c'
const SECRET='2a3dd823cd1abcd45c71'
const CLUSTER='us3'
const USETLS=true
const CHANNEL='patentrack-channel'
const EVENT='patentrack-event'

const pusher = new Pusher({
    appId: APPID,
    key: KEY,
    secret: SECRET,
    cluster: CLUSTER, 
    useTLS: USETLS,
    keepAlive: true,
})
const argumentSlice = process.argv.slice(2)   
const organisationID = argumentSlice[0]
let companies = argumentSlice[1]
const ownedAssets = argumentSlice[2]

let getAssetsList = [], errorCodes = []


const sendNotification = (message) => {
    pusher.trigger(CHANNEL, EVENT, message)
}

const optimizeAssigneeName = (name) => {
    const regex = /\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|cie)\b/ig

    name = name.replace(regex, '')
    name = name.replace(/\s+/, ' ')
    name = name.replace(/[,]/g, ' ')
    name = name.replace(/[.]/g, ' ')
    name = name.replace(/[!]/g, ' ')
    return name.trim();
}

const makeRequest = (url) => {
    return new Promise((resolve, reject) => {
        const headers = {
            'X-Api-Key': process.env.PATENTS_VIEW_API_KEYS
        };
        console.log("headers", headers);
        request({ url, headers }, (error, response, body) => {
            if (error || response.statusCode !== 200) {
                return reject(error || new Error(`Status Code: ${response.statusCode}`));
            }
            try {
                return resolve(JSON.parse(body));
            } catch (e) {
                return reject(e);
            }
        });
    });
};
 
const retrieveCitedPatentAssignee = async (startIndex) => {
    try {
        const asset = getAssetsList[startIndex];
        const citationUrl = `https://search.patentsview.org/api/v1/patent/us_patent_citation/?q=${encodeURIComponent(JSON.stringify({ patent_id: asset.grant_doc_num }))}&o=${encodeURIComponent(JSON.stringify({ page: 1, per_page: 10000 }))}`;
        
        console.log('Citation URL:', citationUrl);
        
        const citationData = await makeRequest(citationUrl);
        console.log("citationData", citationData);
        const { us_patent_citations, count } = citationData;

        if (count === 0 || !us_patent_citations || us_patent_citations.length === 0) {
            console.log('No citations found.');
            return checkNextRow(startIndex);
        }

        const citationIds = us_patent_citations.map(c => c.citation_patent_id);
        const detailQuery = { patent_id: citationIds };
        const fields = [
            "inventors.inventor_name_first", "inventors.inventor_name_last",
            "assignees.assignee_id", "assignees.assignee_organization",
            "assignees.assignee_individual_name_first", "assignees.assignee_individual_name_last",
            "applicants.applicant_name_first", "applicants.applicant_name_last",
            "application.filing_date", "patent_id", "patent_date", "patent_title"
        ];
        
        const detailUrl = `https://search.patentsview.org/api/v1/patent/?q=${encodeURIComponent(JSON.stringify(detailQuery))}&f=${encodeURIComponent(JSON.stringify(fields))}`;
        
        console.log('Detail URL:', detailUrl);
        
        const detailData = await makeRequest(detailUrl);
        
        if (!detailData || detailData.count === 0 || !Array.isArray(detailData.patents)) {
            console.log('No detailed patents found.');
            return checkNextRow(startIndex);
        }

        const allAssignees = [];
        const allAssigneeWithPatentNumber = [];
        console.log("detailData", JSON.stringify(detailData));
        detailData.patents.forEach(patent => {
            if (patent.assignees && patent.assignees.length > 0) {
                patent.assignees.forEach(assignee => {
                    if (assignee.assignee_organization) {
                        let appDate = '0000-00-00';
        
                        // Check if application is an array and has filing_date
                        if (Array.isArray(patent.application) && patent.application.length > 0 && patent.application[0].filing_date) {
                            appDate = patent.application[0].filing_date;
                        } else if (patent.applications && patent.applications.length > 0 && patent.applications[0].app_date) {
                            appDate = patent.applications[0].app_date;
                        }
        
                        const year = moment(new Date(appDate)).format('YYYY');
                        
                        if (year > 1999) {
                            allAssignees.push(assignee.assignee_organization);
                            allAssigneeWithPatentNumber.push({
                                patent_number: asset.grant_doc_num,
                                citing_patent_number: patent.patent_id,
                                assignee_organization: assignee.assignee_organization,
                                app_date: appDate,
                                assignee_id: 0
                            });
                        }
                    }
                });
            }
        });

        if (allAssignees.length === 0) {
            console.log('No assignees found after filtering.');
            return checkNextRow(startIndex);
        }

        const uniqueAssignees = [...new Set(allAssignees)];
        
        console.log('Filtered Assignees:', uniqueAssignees.length);

        // Find existing assignees in DB
        let existingAssignees = await AssigneeOrganizations.findAll({
            attributes: ["assignee_id", "assignee_organization"],
            where: { assignee_organization: uniqueAssignees },
            group: ["assignee_organization"]
        });

        const existingNames = existingAssignees.map(row => row.assignee_organization.toLowerCase());
        const newAssignees = uniqueAssignees.filter(name => !existingNames.includes(name.toLowerCase()));

        // Insert new assignees
        if (newAssignees.length > 0) {
            const insertData = newAssignees.map(name => ({
                assignee_organization: name,
                assignee_query: name
            }));
            await AssigneeOrganizations.bulkCreate(insertData, { ignoreDuplicates: true });
            console.log('Inserted new assignees:', insertData.length);
        }

        // Re-fetch all assignees to ensure we have IDs
        existingAssignees = await AssigneeOrganizations.findAll({
            attributes: ["assignee_id", "assignee_organization"],
            where: { assignee_organization: uniqueAssignees },
            group: ["assignee_organization"]
        });

        const nameToIdMap = {};
        existingAssignees.forEach(row => {
            nameToIdMap[row.assignee_organization.toLowerCase()] = row.assignee_id;
        });

        // Update allAssigneeWithPatentNumber with IDs
        allAssigneeWithPatentNumber.forEach(assigneeEntry => {
            const id = nameToIdMap[assigneeEntry.assignee_organization.toLowerCase()];
            if (id) {
                assigneeEntry.assignee_id = id;
            }
        });

        // Prepare data for insertion
        const citedPatentEntries = [];
        allAssigneeWithPatentNumber.forEach(entry => {
            if (entry.assignee_id) {
                citedPatentEntries.push({
                    patent_number: entry.patent_number,
                    assignee_id: entry.assignee_id
                });
            }
        });

        console.log('Cited Patent Entries:', citedPatentEntries.length);

        if (citedPatentEntries.length > 0) {
            await CitedPatents.bulkCreate(citedPatentEntries, { ignoreDuplicates: true });
            await CitingPatentWithAssignee.bulkCreate(allAssigneeWithPatentNumber, { ignoreDuplicates: true });
        }

        console.log('Assignee data processed successfully.');
        return checkNextRow(startIndex);

    } catch (error) {
        console.error('Error in retrieveCitedPatentAssignee:', error.message || error);
        errorCodes.push(getAssetsList[startIndex].grant_doc_num);
        return checkNextRow(startIndex);
    }
};

const checkNextRow = (startIndex) => {
    startIndex += 1
    if(startIndex < getAssetsList.length) {
        retrieveCitedPatentAssignee(startIndex)
    } else {
        if(errorCodes.length > 0) {
            sendNotification(`Find error from API in these patents: ${errorCodes.join(', ')}`)
        }
        sendNotification(`Cited Patents finished.`)
    }
}

const getAssetsLists = async (organisationID, companies, ownedAssets) => {
   
    try {
        
        let queryAllPatents = `SELECT patent AS grant_doc_num FROM db_new_application.dashboard_items WHERE patent <> '' AND organisation_id = :organisationID `
        const replacements = {organisationID: 0}
        if(companies !== '' && companies !== null && companies !== undefined) {
            companies = JSON.parse(companies)
            replacements.companies = companies
            if( companies.length > 0 ) {
                queryAllPatents += ` AND representative_id IN (:companies)`
            }
        }
        console.log(typeof ownedAssets, ownedAssets)
        if(typeof ownedAssets != 'undefined' && ownedAssets == 1) {
            let queryOwnedAssets = `SELECT application FROM db_new_application.dashboard_items WHERE type IN (30, 21, 36) AND organisation_id = :organisationID `

            if(typeof companies !== 'undefined' && companies.length > 0) {
                queryOwnedAssets += ` AND representative_id IN (:companies) `
            }

            queryOwnedAssets += ` GROUP BY application`

            const getList = await connection.application.query(queryOwnedAssets,{
                    type: connection.Sequelize.QueryTypes.SELECT,
                    raw: true,
                    replacements,
                    logging: console.log,
                }
            );
            if(getList.length > 0) {
                const allOwnedAssets = []

                const promise = getList.map(asset => {
                    allOwnedAssets.push(`${asset.application}`)
                })

                await Promise.all(promise)

                if(allOwnedAssets.length > 0) {
                    replacements.assets = allOwnedAssets
                    queryAllPatents += ` AND application IN (:assets)`
                }
            }
        }
        /*queryAllPatents += ` AND grant_doc_num NOT IN (SELECT patent_number FROM cited_patents GROUP BY patent_number) GROUP BY grant_doc_num`*/
        queryAllPatents += `  GROUP BY patent`
        
        //console.log('connection', connection)
        let list = await connection.application.query(queryAllPatents,{
            type: connection.Sequelize.QueryTypes.SELECT,
            raw: true,
            logging: console.log,
            replacements
            }
        );

        console.log('list', list)
        return list

    } catch (err) {
        return []
    }
    
}


( async () => {     
    console.log('organisationID, companies, ownedAssets', organisationID, companies, ownedAssets)
    getAssetsList = await getAssetsLists(organisationID, companies, ownedAssets)
    console.log('getAssetsList.length', getAssetsList.length)
    if( getAssetsList.length > 0 ) {
        //sendNotification(`Total patents: ${getAssetsList.length}`)
        retrieveCitedPatentAssignee(0)
    }  else {
        if( typeof ownedAssets != 'undefined' && ownedAssets == '1') {
            let companyIDs = []
            if(companies !== '' && companies !== null && companies !== undefined) {
                companyIDs = JSON.parse(companies) 
            }
            exec(`php -f /var/www/html/trash/dashboard_with_company.php ${companyIDs.length > 0 ? companyIDs[0] : ''} ${organisationID}`, async(err, stdout, stderr) => {
                console.log(`php -f /var/www/html/trash/dashboard_with_company.php ${companyIDs.length > 0 ? companyIDs[0] : ''} ${organisationID}`)
                console.log('stdout', stdout)
                console.log('stderr', stderr)
                if (err) {
                    console.error(`exec error: ${err}`);
                    return;
                }
                getAssetsList = await getAssetsLists(organisationID, companies, ownedAssets) 
                console.log('getAssetsList.length', getAssetsList.length)
                if( getAssetsList.length > 0 ) {
                    retrieveCitedPatentAssignee(0) 
                }
            });
         }
    }
})();