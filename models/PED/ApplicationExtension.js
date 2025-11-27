const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicationExtension = connection.applicationPED.define('application_extension',{ 
    application_extension_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    extension:{
        type: Sequelize.INTEGER,
        allowNull: false,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_extension'
});


module.exports = ApplicationExtension;