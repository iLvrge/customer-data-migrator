const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicationInventor = connection.applicationPED.define('application_inventor',{ 
    inventor_id: {
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
    first_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    middle_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    last_name:{
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
        type: Sequelize.STRING,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_inventor'
});


module.exports = ApplicationInventor; 