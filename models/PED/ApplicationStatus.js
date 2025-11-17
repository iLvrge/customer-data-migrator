const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicationStatus = connection.applicationPED.define('application_status',{ 
    application_status_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    status:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    status_date:{
        type: Sequelize.DATE,
        allowNull: false,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_status'
});


module.exports = ApplicationStatus;