const Sequelize = require("sequelize");

const connection = require("../config/index");


const ApplicationStatus  = connection.resourcesRaw.define('application_status',{
    id:{
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    status:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
	status_date:{
        type: Sequelize.DATE,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_status'
});

module.exports = ApplicationStatus;
