const Sequelize = require("sequelize");

const connection = require("../../config/index");


const ApplicantPublicationGrant = connection.applicationPED.define('application_publication_grant',{
    application_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    appno_date:{
        type: Sequelize.DATE,
        allowNull: true,
    },
    pgpub_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    pgpub_date:{
        type: Sequelize.DATE,
        allowNull: true,
    },
    grant_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    grant_date:{
        type: Sequelize.DATE,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_publication_grant'
});


module.exports = ApplicantPublicationGrant;