const request = require('request'),
    process = require('process'),
    connection = require("./config/index"),
    CitedPatents = require("./models/CitedPatents"),
    AssigneeOrganizations = require("./models/AssigneeOrganizations");

    const { exec } = require('child_process');

const { createLogger, format, transports } = require("winston");

const Pusher = require("pusher");

const clearbit = require('clearbit')('sk_d89c1b8d6af056c47526bc5efa4ae544');
const RITEKIT_CLIENTID = `9e44da1127bae5aee46bb12723f7dada36d3ae76916d`
const UPLEAD_CLIENTID = `977c4d4eaa39794b7ee53b4d8da026b1`




const logger = createLogger({
    format: format.combine(format.timestamp(), format.json()),
    transports: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api.log" })],
    exceptionHandlers: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api_exceptions.log" })],
    rejectionHandlers: [new transports.File({ filename: "/var/www/html/trash/name_to_domain_api_rejections.log" })],
});

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
console.log(argumentSlice)
const organisationID = argumentSlice[0], apiName = argumentSlice[1];
let assigneeIDs = argumentSlice[2], typeRetreival = typeof argumentSlice[3] !== 'undefined' ? argumentSlice[3] : null, companyID = typeof argumentSlice[4] !== 'undefined' ? argumentSlice[4] : null, all = typeof argumentSlice[5] !== 'undefined' ? argumentSlice[5] : 0, ownedAssets = typeof argumentSlice[6] !== 'undefined' ? argumentSlice[6] : 2, source_data = typeof argumentSlice[7] !== 'undefined' ? argumentSlice[7] : 0;



let getAssigneeList = [], errorCodes = [], currentIndex = -1, previousIndex = -1


const startInterval = () => {
    setInterval(() => {
        if(currentIndex !== previousIndex) {
            previousIndex = currentIndex
            sendNotification(`Logo fetch ${currentIndex} / ${getAssigneeList.length}`)
        }
    }, 60000) 
} 

const sendNotification = (message) => {
    pusher.trigger(CHANNEL, EVENT, message)
}

const NameToDomain  = clearbit.NameToDomain;

const retrieveCitedPatentAssignee = async(startIndex) => {
    let name = getAssigneeList[startIndex].assignee_query
    
    console.log('getAssigneeList[startIndex].assignee_organization', name, apiName)
    switch (apiName) {
        case 'clearbit':
            clearbitAPI(name, startIndex)
            break;
        case 'uplead':
            upleadAPI(name, startIndex)
        break;
        case 'rapidapi':
            //rapidAPI(startIndex)
            startInterval()
            rapidAPIUpdate()
        break;
        case 'ritekit':
            typeRetreival == 0 ? ritekitAPI(name, startIndex) : runRitekitLogo(startIndex)
        break;
    }
    
}

const updateAssigneeData = async (params, assignee_id) => {
    console.log(`UPDATE START ${assignee_id}`)
    await AssigneeOrganizations.update(params, {
        where: {
            assignee_id
        }
    })
    console.log(`UPDATE END ${assignee_id}`)
    
    sendNotification(`IMAGES_RETRIEVED: ${assignee_id}`)
}


const sendRequest = (options) => {
    //console.log(`REQUESTED URL: `, options)
    const promise = new Promise((resolve, reject) => {
        const  request = require('request');
        request(options, function (error, response) {
            if (!error){
                resolve(response)
            } else {
                console.log(`Send Request ${error}`)
                reject(error)
            }
        })
    })
    return promise
}


const requestRapid = async (searchItem, type) => {
    const patternMatch = '\\b(?:inc|llc|corporation|corp|llp|gmbh|lp|sas|na|co|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|ab|sa)\\b'; 
            
    let searchString = searchItem
    /* console.log("requestRapid", searchString, type) */
    if(type == 1) {
        const regex = new RegExp(patternMatch,"gi")
        searchString = searchString.replace(regex, "")  
        searchString = searchString.replace(/\//g, ' ')  
        searchString = searchString.replace(/\./g, '')  
        searchString = searchString.replace(/\,/g, '') 
    }  
    searchString = searchString.replace(/-/g, ' ') 
    searchString = searchString.replace(/—/g, ' ') 
    searchString = searchString.trim() 
    searchString = searchString.replace(/ /g,"%20") 
    searchString = searchString.replace(/&/g, 'and') 
   /*  console.log('searchString', searchString) */
    /*  let query = `%22${searchString}%22`; */
    let query = `${decodeURIComponent(searchString.trim())}+logo `; 
    const options = {
        method: 'GET',
        url: 'https://google-search72.p.rapidapi.com/imagesearch',
        qs: {
          q: query,
          gl: 'us',
          lr: 'lang_en',
          num: '10',
          start: '0',
          sort: 'relevance'
        },
        headers: {
          'X-RapidAPI-Key': 'c36ea8ed3amsh5c830c27ba35b85p1e57acjsnfadf5dbc6779',
          'X-RapidAPI-Host': 'google-search72.p.rapidapi.com',
          useQueryString: true
        }
    };
    console.log(options)
    /*logger.info(logoURL) */
    const response = await sendRequest(options)
    return response
}
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const rapidAPIUpdate = async() => {
    let started = 0;
    await getAssigneeList.reduce(async (promise, row, currentIndex) => {
        
        await promise;
        try{
            console.log(started)
            let searchString = row.assignee_query ;
            const response = await requestRapid(searchString, 2);
            //console.log(response)
            /* logger.info(response)
            console.log(response) */
            
            if(response !== null) {
                try{ 
                    console.log(`response with body ${response.body}`)
                    const {items, status} = JSON.parse(response.body);
                    console.log('FIRST', items, status)
                    if(typeof items !== 'undefined') {
                        if( items.length > 0) {
                            const updateFields = {}
                            for(let i = 0; i < 5; i++) {
                                if(typeof items[i] !== 'undefined' && typeof items[i].thumbnailImageUrl !== 'undefined') {
                                    updateFields[`api_logo${i > 0 ? i : ''}`] = items[i].thumbnailImageUrl
                                }
                            }
                            await sleep(10000);    
                            const responseSecond = await requestRapid(searchString, 1);


                            if(responseSecond !== null) {
                                let {items, status} = JSON.parse(responseSecond.body);
                                console.log('SECOND',  items)
                                if( items.length > 0) { 
                                    console.log('In second request')
                                    let inc = 5;
                                    for(let i = 0; i < 5; i++) {
                                        if(typeof items[inc] !== 'undefined' && typeof items[i].thumbnailImageUrl !== 'undefined') {
                                            updateFields[`api_logo${inc > 0 ? inc : ''}`] = items[i].thumbnailImageUrl 
                                        }
                                        inc++;
                                    }
                                }
                            }   

                            console.log(`UPDATE INDEX: ${currentIndex}`, updateFields)
                            await updateAssigneeData(updateFields, row.assignee_id)
                            console.log(console.log(`UPDATE FINISHED ${row.assignee_id}`))
                        }                    
                    } else if (typeof message !== 'undefined') {
                        console.log(`error from API: ${message}`)
                        sendNotification(message)
                    }  
                    await sleep(1000);                   
                } catch (e) {
                    console.log('Error in rapidAPI', e)
                    sendNotification(e.message)
                }
            } else {
                console.log('No response from API')
                sendNotification('No response from rapid API')
            }
            await sleep(10000);     
        } catch (e) {
            console.log(e.message)
        }        
        started++
    }, Promise.resolve());
    console.log('DONE')
	sendNotification(`Cited Patents finished.`)
    process.kill(process.pid, 'SIGINT');
	/*setTimeout(() => {
		process.exit(0);
	}, 2000)*/
}


const rapidAPI = async(startIndex) => {
    try {
        if(getAssigneeList[startIndex].assignee_query !== '' && getAssigneeList[startIndex].assignee_query !== null) {
			currentIndex = startIndex
			console.log(`Current index running ${currentIndex}`)
            let assigneeName = getAssigneeList[startIndex].assignee_query 
            assigneeName = assigneeName.replace(/&/g, 'and')
            let query = `%22${encodeURIComponent(assigneeName)}%22+logo`;
            const logoURL = `https://google-search3.p.rapidapi.com/api/v1/images/q=${query}`
            console.log('logoURL', logoURL)
            const {response} = await sendRequest(logoURL, {
                'x-user-agent': 'desktop',
                'x-proxy-location': 'US',
                'x-rapidapi-host': 'google-search3.p.rapidapi.com',
                'x-rapidapi-key': 'e9431999femshbd54071785cc02bp151695jsnc5d122698e5d'
            })

            if(response !== null) {
                try{ 
                    const {image_results, message} = JSON.parse(response.body);
                    if(typeof image_results !== 'undefined') {
                        if( image_results.length > 0) {
                            const updateFields = {}
                            for(let i = 0; i < 10; i++) {
                                if(typeof image_results[i] !== 'undefined' && typeof image_results[i].image !== 'undefined') {
                                    updateFields[`api_logo${i > 0 ? i : ''}`] = image_results[i].image.src
                                }
                            }
                            console.log(`UPDATE INDEX: ${startIndex}`)
                            await updateAssigneeData(updateFields, getAssigneeList[startIndex].assignee_id)
                        }     
                        checkNextRow(startIndex)                   
                    } else if (typeof message !== 'undefined') {
                        console.log(`error from API: ${message}`)
                        sendNotification(message)
                    } else {
                        console.log(`no image_results no message`)
                        checkNextRow(startIndex)         
                    }                    
                } catch (e) {
                    console.log('Error in rapidAPI', e)
                    checkNextRow(startIndex)
                }
            }
        }
    } catch (e) {
        console.log(e.message)
        sendNotification(e.message)
        checkNextRow(startIndex)
    }
}


const checkNextRow = (startIndex) => {
    startIndex += 1
	console.log(`checkNextRow : ${startIndex} - ${getAssigneeList.length}`)
    if(startIndex < getAssigneeList.length) {
        retrieveCitedPatentAssignee(startIndex)
    } else {
        if(errorCodes.length > 0) {
            sendNotification(`Find error from API in these patents: ${errorCodes.join(', ')}`)
        }
        sendNotification(`Cited Patents finished.`)
    }
}

const optimizeAssigneeName = (name) => {
    const regex = /\b(?:inc|llc|corporation|corp|systems|system|llp|industries|gmbh|lp|agent|sas|na|bank|co|states|ltd|kk|a\/s|aktiebolag|kigyo|kaisha|university|kabushiki|company|plc|gesellschaft|gesmbh|société|societe|mbh|aktiengesellschaft|haftung|vennootschap|bv|bvba|aktien|limitata|srl|sarl|kommanditgesellschaft|kg|gesellschaft|gbr|ohg|handelsgesellschaft|compagnie|privatstiftung|foundation|sa)\b/ig

    name = name.replace(regex, '')
    name = name.replace(/\s+/, ' ')
    name = name.replace(/[,]/g, ' ')
    name = name.replace(/[.]/g, ' ')
    name = name.replace(/[!]/g, ' ')
    return name.trim();
}

const getPartiesLists = async (organisationID, companyID, assigneeIDs) => {
    try {
        const replacements = {organisationID: 0, activityID: 10, assigneeIDs: []}
        let getList = []
        let queryParties = `Select partyName FROM (SELECT IF(r.representative_name <> '', r.representative_name, aaa.name) AS partyName FROM (  SELECT apt.assignor_and_assignee_id FROM db_new_application.activity_parties_transactions AS apt WHERE activity_id <> :activityID AND (organisation_id = :organisationID OR organisation_id IS NULL) `;
 
        let companyIDs = []
        if(companyID !== null && companyID != '') {
            companyIDs = JSON.parse(companyID)
            if(companyIDs.length > 0) {
                replacements.companyIDs = companyIDs
                queryParties += ` AND company_id IN (:companyIDs)  `
            }
        }

        queryParties += `  GROUP BY apt.assignor_and_assignee_id ) AS temp INNER JOIN db_uspto.assignor_and_assignee AS aaa ON aaa.assignor_and_assignee_id = temp.assignor_and_assignee_id LEFT JOIN db_uspto.representative AS r ON r.representative_id = aaa.representative_id ) AS temp GROUP BY partyName`; 
        console.log(queryParties)
        const partiesResult = await connection.application.query( queryParties,{
            type: connection.Sequelize.QueryTypes.SELECT,
            raw: true,
            replacements ,
            logging: console.log,
        });

        if(partiesResult.length > 0) {
            const allParties = [] 
            const promise =  partiesResult.map( row => {
                allParties.push(row.partyName) 
            })
            await Promise.all(promise)
            replacements.allParties = allParties
            let queryCitingAssignees = `SELECT COUNT(ao.assignee_id) AS occurences, ao.assignee_id, ao.assignee_organization, ao.assignee_query, ao.domain, ao.domain2, ao.domain3, ao.api_logo FROM assignee_organizations AS ao
            WHERE  organisation_id = 0  AND ao.assignee_organization IN (:allParties)`
            if(assigneeIDs !== '' && assigneeIDs !== null && assigneeIDs !== undefined) {
                assigneeIDs = JSON.parse(assigneeIDs)
                replacements.assigneeIDs = assigneeIDs
                if(assigneeIDs.length > 0) {
                    queryCitingAssignees += ` AND ao.assignee_id IN (:assigneeIDs) `
                }
            }
            if(replacements.assigneeIDs.length == 0) {
                queryCitingAssignees += `  AND (ao.api_logo IS NULL OR ao.api_logo = '' ) `
            }

            queryCitingAssignees += ` GROUP BY ao.assignee_organization  ORDER BY occurences DESC `
    
            console.log(replacements)
            getList = await connection.application.query(queryCitingAssignees,{
                    type: connection.Sequelize.QueryTypes.SELECT,
                    raw: true,
                    logging: console.log,
                    replacements
                }
            ); 
        }
        console.log(getList)
        return getList

    } catch (err) {
        console.log(err)
        return []
    }
}

const getAssetsLists = async (organisationID, companyID, ownedAssets, assigneeIDs) => {
    
    try {
        const replacements = {organisationID: 0, assigneeIDs: []}
        let queryCitingAssignees = `SELECT COUNT(ao.assignee_id) AS occurences, ao.assignee_id, ao.assignee_organization, ao.assignee_query, ao.domain, ao.domain2, ao.domain3, ao.api_logo FROM assignee_organizations AS ao
                                INNER JOIN cited_patents AS cp ON cp.assignee_id = ao.assignee_id
                                WHERE cp.patent_number IN (SELECT grant_doc_num COLLATE utf8mb4_general_ci FROM assets WHERE organisation_id = :organisationID `
        if(organisationID == 0) {
            queryCitingAssignees = `SELECT COUNT(ao.assignee_id) AS occurences, ao.assignee_id, ao.assignee_organization, ao.assignee_query, ao.domain, ao.domain2, ao.domain3, ao.api_logo FROM assignee_organizations AS ao
            WHERE  organisation_id = 0  `
        }
        let companyIDs = []
        if(companyID !== null && companyID != '') {
            companyIDs = JSON.parse(companyID)
            if(companyIDs.length > 0) {
                replacements.companyIDs = companyIDs
                queryCitingAssignees += ` AND company_id IN (:companyIDs)  `
            }
        }
        console.log(replacements, ownedAssets)
    
        if(typeof ownedAssets != 'undefined' && ownedAssets == '1') {
            let queryOwnedAssets = `SELECT application FROM db_new_application.dashboard_items WHERE type IN (30, 21, 36) AND (organisation_id = :organisationID OR organisation_id IS NULL)`
    
            if(typeof replacements.companyIDs != 'undefined') {
                queryOwnedAssets += ` AND representative_id IN (:companyIDs) `
            }
    
            queryOwnedAssets += ` GROUP BY application`
            console.log(queryOwnedAssets)
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
                    queryCitingAssignees += ` AND appno_doc_num IN (:assets)`
                }
            }
        }
        console.log(replacements)
        if(organisationID != 0) {
            queryCitingAssignees += ` AND grant_doc_num <> '' GROUP BY grant_doc_num) AND organisation_id = 0`
        }
        //console.log('connection', connection)
    
    
        if(assigneeIDs !== '' && assigneeIDs !== null && assigneeIDs !== undefined) {
            assigneeIDs = JSON.parse(assigneeIDs)
            replacements.assigneeIDs = assigneeIDs
            if(assigneeIDs.length > 0) {
                queryCitingAssignees += ` AND ao.assignee_id IN (:assigneeIDs) `
            }
        }
        if(replacements.assigneeIDs.length == 0 && all == 0) {
            queryCitingAssignees += `  AND (ao.api_logo IS NULL OR ao.api_logo = '' ) `
        }
    
    
        queryCitingAssignees += ` GROUP BY ao.assignee_organization  ORDER BY occurences DESC`
    
        console.log(replacements)
        let getList = await connection.application.query(queryCitingAssignees,{
                type: connection.Sequelize.QueryTypes.SELECT,
                raw: true,
                logging: console.log,
                replacements: replacements,
            }
        ); 
        return getList
    } catch (err) {
        console.log(err)
        return []
    }
    
}

( async () => {    
    try {
        let companyIDs = []
        if(companyID !== null && companyID != '') {
            companyIDs = JSON.parse(companyID) 
        }
        if(source_data == 1) {
            getAssigneeList = await getPartiesLists(organisationID, companyID, assigneeIDs) 
            if( getAssigneeList.length > 0 ) { 
                retrieveCitedPatentAssignee(0)   
            }  
        } else { 
            getAssigneeList = await getAssetsLists(organisationID, companyID, ownedAssets, assigneeIDs)  
            if(getAssigneeList.length == 0 && typeof ownedAssets != 'undefined' && ownedAssets == 1) {
               exec(`php -f /var/www/html/scripts/dashboard_with_company.php ${companyIDs.length > 0 ? JSON.stringify(companyIDs) : ''} ${organisationID}`, async(err, stdout, stderr) => {
                    if (err) {
                        console.error(`exec error: ${err}`);
                        return;
                    }
                    getAssigneeList = await getAssetsLists(organisationID, companyID, ownedAssets, assigneeIDs) 
                    console.log('getAssignees.length', getAssigneeList.length)
                    if( getAssigneeList.length > 0 ) {
                        retrieveCitedPatentAssignee(0) 
                    }
               });
            } else {
                console.log('getAssignees.length', getAssigneeList.length)
                if( getAssigneeList.length > 0 ) { 
                    retrieveCitedPatentAssignee(0)   
                }       
            }
        } 
    } catch (err) {
        console.log(err)
    }
})();



