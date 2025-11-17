const Sequelize = require("sequelize");

const connection = require("../config/index");


const GrantExtension = connection.applicationGrant.define('grant_extension',{
    id: {
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
        allowNull: true,
    } 
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'grant_extension'
});


module.exports = GrantExtension;