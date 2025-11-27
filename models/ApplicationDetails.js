const Sequelize = require("sequelize");

const connection = require("../config/index");

const ApplicationDetails = connection.resources.define('application_details',{
    id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    }, 
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    grant_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    appno_date:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    grant_date:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    title:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    abstract:{
        type: Sequelize.STRING,
        allowNull: false,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_details'
});

module.exports = ApplicationDetails;