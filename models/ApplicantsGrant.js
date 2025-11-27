const Sequelize = require("sequelize");

const connection = require("../config/index");


const Applicants = connection.applicationGrant.define('applicant',{
    applicant_id: {
        type: Sequelize.INTEGER,
        primaryKey: true,
        autoIncrement: true
    },
    appno_doc_num:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    publication_number:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    original_name:{
        type: Sequelize.STRING,
        allowNull: false,
    }, 
    name:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    family_name:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    given_name:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    middle_name:{
        type: Sequelize.STRING,
        allowNull: true,
    }, 
    address_1:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    address_2:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    city:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    state:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    postcode:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    country:{
        type: Sequelize.STRING,
        allowNull: false,
    },
    type:{
        type: Sequelize.INTEGER,
        allowNull: false,
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
    tableName: 'applicant'
});


module.exports = Applicants;