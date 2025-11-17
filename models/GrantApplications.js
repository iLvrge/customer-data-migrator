const Sequelize = require("sequelize");

const connection = require("../config/index");


const GrantApplications  = connection.applicationGrant.define('application_grant',{
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    appno_date:{
        type: Sequelize.DATE,
        allowNull: false,
    },
    grant_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    grant_date:{
        type: Sequelize.DATE,
        allowNull: true,
    },
    title:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    file_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_grant'
});

GrantApplications.removeAttribute('id');

module.exports = GrantApplications;
