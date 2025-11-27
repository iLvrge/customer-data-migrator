const Sequelize = require("sequelize");

const connection = require("../config/index");


const CitedPatents = connection.application.define('cited_patents',{
    cited_patent_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    }, 
    patent_number:{
        type: Sequelize.STRING,
        allowNull: false,
    }, 
    assignee_id:{
        type: Sequelize.INTEGER,
        allowNull: false,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'cited_patents'
});

module.exports = CitedPatents;