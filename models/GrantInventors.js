const Sequelize = require("sequelize");

const connection = require("../config/index");


const Inventors = connection.applicationGrant.define('inventor',{
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    name:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    given_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    middle_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    family_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    file_name:{
        type: Sequelize.STRING,
        allowNull: true,
    },
    full_path:{
        type: Sequelize.STRING,
        allowNull: true,
    }
},
{
    underscored: true,
    timestamps: false,
    freezeTableName: true,
    tableName: 'inventor'
});

Inventors.removeAttribute('id');

module.exports = Inventors;