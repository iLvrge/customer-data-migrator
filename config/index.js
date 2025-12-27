const Sequelize = require('sequelize');
const moment = require('moment');
const Op = Sequelize.Op;


const business = new Sequelize(process.env.DATABASE_BUSINESS, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});


const resourcesRaw = new Sequelize(process.env.DATABASE_RAW, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});


const resources = new Sequelize(process.env.DATABASE_APPLICATION_BIBLIO_NEW, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});

const application = new Sequelize(process.env.DATABASE_APPLICATION_NEW, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});


const applicationBibliograhic = new Sequelize(process.env.DATABASE_APPLICATION_BIBLIO, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});

const applicationGrant = new Sequelize(process.env.DATABASE_GRANT_BIBLIO, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});

const applicationPED = new Sequelize(process.env.DATABASE_PATENT_EXAMINER_DATA, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  pool: {
    max: 300000,
    min: 0,
    acquire: 600000000,
    idle: 50000000
  }
});


const inventorDB = new Sequelize('db_inventor', 'db_user_inventor', process.env.PASSWORD, {
  host: '165.232.146.68',
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});



const biblioGrant = new Sequelize(process.env.DATABASE_GRANT_BIBLIO, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000, 
     idle: 10000
   }*/
});

const biblioApplication = new Sequelize(process.env.DATABASE_APPLICATION_BIBLIO, process.env.USER, process.env.PASSWORD, {
  host: process.env.DB_HOST,
  dialect: 'mysql',
  operatorsAliases: Op,
  /*
   pool: {
     max: 1000,
     min: 0,
     acquire: 1000000,
     idle: 10000
   }*/
});


const bucketConfig = {
  bucketName: process.env.BUCKET_NAME,
  dirName: process.env.BUCKET_PHOTO_DIR, /* optional */
  region: process.env.BUCKET_REGION,
  accessKeyId: process.env.BUCKET_ACCESS_KEY,
  secretAccessKey: process.env.BUCKET_SECRET_KEY,
  s3Url: process.env.BUCKET_URL, /* optional */
  documentDir: process.env.BUCKET_DOCUMENT_DIR,
  figuresDir: process.env.BUCKET_FIGURES_DIR,
}


const DEFAULT_LIMIT = 100

const DEFAULT_YEAR = moment(new Date()).subtract(24, 'year').format('YYYY')

const db = { Sequelize, Op, resourcesRaw, resources, business, inventorDB, bucketConfig, application, applicationGrant, applicationBibliograhic, applicationPED, biblioApplication, biblioGrant, DEFAULT_LIMIT, DEFAULT_YEAR }

module.exports = db;
