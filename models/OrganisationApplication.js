const Sequelize = require("sequelize");

const connection = require("../config/index");


const OrganisationApplication = connection.application.define('organisations',{
    organisation_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },        
    organisation_name:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    logo_optimize:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    original_logo:{
        type: Sequelize.STRING,
        allowNull: true,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true
});

module.exports = OrganisationApplication;