const Sequelize = require("sequelize");

const connection = require("../config/index");

const ApplicationClaims = connection.resources.define('application_claims',{
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
    claim_id:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    claim_num:{
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
    tableName: 'application_claims'
});

module.exports = ApplicationClaims;