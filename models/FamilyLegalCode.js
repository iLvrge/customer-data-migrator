const Sequelize = require("sequelize");

const connection = require("../config/index");


const FamilyLegalCode = connection.resourcesRaw.define('family_legal_codes',{
    legal_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    country_code:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    legal_code:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    description:{
        type: Sequelize.STRING,
        allowNull: false,
    },  
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'family_legal_codes'
});


module.exports = FamilyLegalCode;