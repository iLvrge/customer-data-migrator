const Sequelize = require("sequelize");

const connection = require("../config/index");


const ApplicationSpecifications = connection.resources.define('application_specifications',{
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
    heading_id:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    text:{
        type: Sequelize.STRING,
        allowNull: false,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_specifications'
});

module.exports = ApplicationSpecifications;