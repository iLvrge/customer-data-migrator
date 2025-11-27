const Sequelize = require("sequelize");

const connection = require("../config/index");


const AssetsFamily  = connection.resourcesRaw.define('assets_family',{
    id:{
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    grant_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
	appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },	
    family_id:{
        type: Sequelize.INTEGER,
        allowNull: false,
    },
    application_number:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    application_country:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    application_kind:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    patent_number:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    publication_country:{
        type: Sequelize.STRING,
        allowNull: true,
    },     
    publication_kind:{
        type: Sequelize.STRING,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'assets_family'
});

module.exports = AssetsFamily;
