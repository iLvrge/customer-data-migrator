const Sequelize = require("sequelize");

const connection = require("../config/index");


const ApplicationPublication = connection.applicationBibliograhic.define('application_publication',{
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    appno_date:{
        type: Sequelize.DATE,
        allowNull: false,
    },
    pgpub_doc_num:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    pgpub_date:{
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
    tableName: 'application_publication'
});

ApplicationPublication.removeAttribute('id');

module.exports = ApplicationPublication;