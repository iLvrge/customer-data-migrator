const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicationCorrespondence = connection.applicationPED.define('application_correspondence',{ 
    application_correspondence_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    name:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    address:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    city:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    state:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    country:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    law_firm_id:{
        type: Sequelize.DATE,
        allowNull: true,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_correspondence'
});


module.exports = ApplicationCorrespondence;