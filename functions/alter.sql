ALTER TABLE `survey_data`  ADD `status` INT NOT NULL  AFTER `longitude`;
ALTER TABLE `farmer` ADD `status` INT NOT NULL AFTER `education_grade`;

CREATE TABLE `cluster` ( `id` INT NOT NULL AUTO_INCREMENT , 
`cluster_name` VARCHAR(100) NOT NULL , 
`cluster_head` VARCHAR(100) NOT NULL , `phone_no` VARCHAR(13) NOT NULL , 
`image_link` LONGTEXT NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB;

CREATE TABLE `cluster_lga` ( `id` INT NOT NULL AUTO_INCREMENT , 
`cluster_id` INT NOT NULL , `state` VARCHAR(60) NOT NULL , 
`lga` VARCHAR(60) NOT NULL , PRIMARY KEY (`id`)) ENGINE = InnoDB;

ALTER TABLE `farmer`  ADD `cluster_id` INT NOT NULL  AFTER `status`;

ALTER TABLE `warehouse` ADD `longitude` VARCHAR(200) NOT NULL AFTER `location`, 
ADD `latitude` VARCHAR(200) NOT NULL AFTER `longitude`, ADD `size` DECIMAL NOT NULL AFTER `latitude`;

ALTER TABLE `farm`  ADD `longitude` VARCHAR(200) NOT NULL  AFTER `cluster_community`,  
ADD `latitude` VARCHAR(200) NOT NULL  AFTER `longitude`,  ADD `size` DECIMAL NOT NULL  AFTER `latitude`;


-- temporal data structure and input
-- The 2021 form responses that used to be seeded here (input_distrubution,
-- input_inventory, input_cluster) were removed because they hold personal data.
CREATE TABLE IF NOT EXISTS farm_survey.input_distrubution(
   Timestamp                                                                VARCHAR(28) NOT NULL
  ,Name_of_Needle_Technology_Staff                                          VARCHAR(25) NOT NULL
  ,Contact_of_Needle_Technology_Staff                                       INTEGER  NOT NULL
  ,Name_of_State                                                            VARCHAR(6) NOT NULL
  ,Anambra_LGA                                                              VARCHAR(30)
  ,Bayelsa_LGAs                                                             VARCHAR(30)
  ,Cross_River_LGAs                                                         VARCHAR(30)
  ,Delta_LGAs                                                               VARCHAR(30)
  ,Ebonyi_LGAs                                                              VARCHAR(26)
  ,Ekiti_LGAs                                                               VARCHAR(17)
  ,Gombe_LGAs                                                               VARCHAR(11)
  ,Imo_LGAs                                                                 VARCHAR(30)
  ,Ogun_LGAs                                                                VARCHAR(30)
  ,Ondo_LGAs                                                                VARCHAR(30)
  ,Oyo_LGAs                                                                 VARCHAR(20)
  ,Taraba_LGAs                                                              VARCHAR(30)
  ,Yobe_LGAs                                                                VARCHAR(30)
  ,Osun_LGAs                                                                VARCHAR(14)
  ,Which_type_of_input_is_being_distributed                                 VARCHAR(30) NOT NULL
  ,Total_amount_of_Input_allocatedReceived_KG_bags                          VARCHAR(16)
  ,Total_Number_of_Fertilizer_Received_Kg_Bags                              INTEGER 
  ,Type_of_Fertilizer                                                       VARCHAR(15)
  ,Location_of_Warehouse                                                    VARCHAR(69) NOT NULL
  ,Name_of_Warehouse_Manager                                                VARCHAR(20) NOT NULL
  ,Contact_of_Warehouse_Manager                                             INTEGER  NOT NULL
  ,Input_given_to VARCHAR(22) NOT NULL
  ,If_others_please_specify                                                 VARCHAR(6)
  ,If_others_please_specify_the_type_of_fertilizer                          VARCHAR(11)
  ,Name_of_Cluster_Head                                                     VARCHAR(20)
  ,Contact_details_of_cluster_head_Phone_Number                             INTEGER 
  ,Name_of_Banks_Representative                                             VARCHAR(39)
  ,Contact_of_Banks_Representative                                          INTEGER 
  ,Date_of_Distribution                                                     DATE  NOT NULL
  ,Name_of_Merchanisation_Company                                           VARCHAR(21)
  ,Name_of_Merchanisation_companys_Representative                           VARCHAR(19)
  ,Contact_of_Merchanisation_companys_Representative                        INTEGER 
  ,Is_there_any_other_information_we_should_know                            VARCHAR(313)
  ,Please_upload_any_images_from_the_distribution                           VARCHAR(339)
);


ALTER TABLE `input_distrubution` ADD `id` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`); 

CREATE TABLE IF NOT EXISTS farm_survey.input_inventory(
   Timestamp                                         VARCHAR(28) NOT NULL
  ,Name_of_Needle_Technology_Staff                   VARCHAR(25)
  ,Contact_of_Needle_Technology_Staff                INTEGER 
  ,Name_of_State                                     VARCHAR(7) NOT NULL
  ,Anambra_LGA                                       VARCHAR(14)
  ,Bayelsa_LGAs                                      VARCHAR(30)
  ,Cross_River_LGAs                                  VARCHAR(30)
  ,Delta_LGAs                                        VARCHAR(29)
  ,Ebonyi_LGAs                                       VARCHAR(26)
  ,Ekiti_LGAs                                        VARCHAR(17)
  ,Gombe_LGAs                                        VARCHAR(25)
  ,Imo_LGAs                                          VARCHAR(25)
  ,Ogun_LGAs                                         VARCHAR(13)
  ,Ondo_LGAs                                         VARCHAR(30)
  ,Oyo_LGAs                                          VARCHAR(20)
  ,Taraba_LGAs                                       VARCHAR(12)
  ,Yobe_LGAs                                         VARCHAR(30)
  ,Osun_LGAs                                         VARCHAR(14)
  ,Which_type_of_input_is_being_distributed          VARCHAR(30)
  ,Total_Number_of_Input_Received_Kg_Bags            NUMERIC(6,1)
  ,Location_of_Warehouse                             VARCHAR(54) NOT NULL
  ,Name_of_Warehouse_Manager                         VARCHAR(20) NOT NULL
  ,Contact_of_Warehouse_Manager                      VARCHAR(49) NOT NULL
  ,Total_Number_of_Fertilizer_Received_Kg_Bags       VARCHAR(10)
  ,Type_of_Fertilizer                                VARCHAR(15)
  ,If_others_please_specify_the_type_of_fertilizer   VARCHAR(30)
  ,Name_of_Banks_Representative                      VARCHAR(21)
  ,Contact_of_Banks_Representative                   INTEGER 
  ,Name_of_Driver                                    VARCHAR(22)
  ,Vehicle_Registration_Number                       VARCHAR(16)
  ,Date_of_Distribution                              VARCHAR(10) NOT NULL
  ,Does_the_Bank_have_Joint_Custody_of_the_Warehouse VARCHAR(17) NOT NULL
  ,Is_there_any_other_information_we_should_know     VARCHAR(361)
  ,Please_upload_any_images_from_the_distribution    VARCHAR(424)
  ,FIELD35                                           VARCHAR(10)
);

ALTER TABLE `input_inventory` ADD `id` INT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);

CREATE TABLE IF NOT EXISTS farm_survey.input_cluster(
   id                                                      INTEGER  NOT NULL PRIMARY KEY 
  ,Timestamp                                               VARCHAR(18)
  ,Name_of_Enumerator                                      VARCHAR(27)
  ,ContactPhone_Number_of_Enumerator                       INTEGER 
  ,State                                                   VARCHAR(11)
  ,LGA_RAW                                                 VARCHAR(49)
  ,LGA                                                     VARCHAR(44)
  ,Taraba_LGAs                                             VARCHAR(23)
  ,Town                                                    VARCHAR(36)
  ,Name_Of_Cluster_Head                                    VARCHAR(66)
  ,Contact_Number_Of_Cluster_Head                          VARCHAR(13)
  ,Cluster_Head_Image                                      VARCHAR(66)
  ,Name_of_cluster_Area                                    VARCHAR(81)
  ,Number_of_Farmer_in_the_Cluster                         NUMERIC(8,3)
  ,Size_of_Cluster_Farm_LandHa                             NUMERIC(9,3)
  ,Upload_Document_From_Fields_Area_Measure_Application    VARCHAR(66)
  ,Has_the_farm_Land_been_planted_On                       VARCHAR(3)
  ,If_Yes_Upload_Farm_Image                                VARCHAR(66)
  ,Latitude                                                VARCHAR(19)
  ,Longitude                                               VARCHAR(19)
  ,Oyo_LGAs                                                VARCHAR(49)
  ,Anambra_LGA                                             VARCHAR(9)
  ,Bayelsa_LGAs                                            VARCHAR(30)
  ,Cross_River_LGAs                                        VARCHAR(11)
  ,Delta_LGAs                                              VARCHAR(29)
  ,Ebonyi_LGAs                                             VARCHAR(21)
  ,Ekiti_LGAs                                              VARCHAR(17)
  ,Gombe_LGAs                                              VARCHAR(25)
  ,Imo_LGAs                                                VARCHAR(30)
  ,Ogun_LGAs                                               VARCHAR(17)
  ,Ondo_LGAs                                               VARCHAR(17)
  ,Yobe_LGAs                                               VARCHAR(15)
  ,Osun_LGAs                                               VARCHAR(13)
  ,Comment_Pls_indicate_if_Redo_if_work_is_been_done_again VARCHAR(154)
  ,which_section_of_MAAN_is_this                           VARCHAR(19)
);

ALTER TABLE `input_cluster` CHANGE `id` `id` INT(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `input_cluster` ADD `hectares` INT AS (Size_of_Cluster_Farm_LandHa + 0) STORED AFTER `Size_of_Cluster_Farm_LandHa`;

ALTER TABLE `states` ADD `simple_name` VARCHAR(100) AS (REPLACE(name, 'state', '')) STORED AFTER `name`;
ALTER TABLE `states` CHANGE `simple_name` `simple_name` VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;  
UPDATE `states` SET simple_name = REPLACE(simple_name, 'State', '');

ALTER TABLE `input_cluster` CHANGE `Latitude` `Latitude` DECIMAL NULL DEFAULT NULL, CHANGE `Longitude` `Longitude` DECIMAL NULL DEFAULT NULL;

ALTER TABLE `input_cluster` ADD `status` VARCHAR(13) NOT NULL DEFAULT 'NOT VERIFIED' AFTER `which_section_of_MAAN_is_this`;
ALTER TABLE `input_distrubution` ADD `status` VARCHAR(13) NOT NULL DEFAULT 'NOT VERIFIED' AFTER `Please_upload_any_images_from_the_distribution`;
ALTER TABLE `input_inventory` DROP `FIELD35`;
ALTER TABLE `input_inventory` ADD `status` VARCHAR(13) NOT NULL DEFAULT 'NOT VERIFIED' AFTER `Please_upload_any_images_from_the_distribution`;

-- Password reset tokens and forced password change on first login.
ALTER TABLE `users` ADD `must_change_password` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_password_resets_token` (`token_hash`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;


-- Bearer tokens for the field app API. Only a SHA-256 hash of each token is stored.
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `last_used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_api_tokens_hash` (`token_hash`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB;

-- Farm photos belong to a farm, not a warehouse, so warehouse_id must be optional.
ALTER TABLE `images` MODIFY `warehouse_id` INT NULL;
