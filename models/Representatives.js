const Sequelize = require("sequelize");

const connection = require("../config/index");


const Representatives = connection.resourcesRaw.define('representative',{
    representative_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },        
    representative_name:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    created_at:{
        type: Sequelize.DATE,
        allowNull: true,
    },
    updated_at:{
        type: Sequelize.DATE,
        allowNull: true,
    },
},
{
    underscored: true,
    timestamps: true,
    freezeTableName: true,
    tableName: 'representative'
});




module.exports = Representatives;
