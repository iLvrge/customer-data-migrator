const Sequelize = require("sequelize");

const connection = require("../config/index");


const ApplicationFigures = connection.resources.define('application_figures',{
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
    figure_id:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    file:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    width:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    height:{
        type: Sequelize.STRING,
        allowNull: true,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'application_figures'
});

module.exports = ApplicationFigures;