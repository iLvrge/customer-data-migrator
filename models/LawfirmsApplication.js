const Sequelize = require("sequelize");

const connection = require("../config/index");


const LawfirmsApplication = connection.applicationBibliograhic.define('lawfirm',{
    id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    name:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    law_firm_id:{
        type: Sequelize.INTEGER,
        allowNull: true,
    }, 
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'lawfirm'
});


module.exports = LawfirmsApplication;