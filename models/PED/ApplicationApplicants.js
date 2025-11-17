const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicationApplicant = connection.applicationPED.define('application_applicant',{ 
    applicant_id: {
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
    applicant_inventor_id:{
        type: Sequelize.INTEGER,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_applicant'
});


module.exports = ApplicationApplicant; 