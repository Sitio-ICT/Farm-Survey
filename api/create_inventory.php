<?php
// POST: submit one input_inventory record (JSON object) or several (JSON array). Requires the take_survey right.
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$user = requireApiUser();
requireApiRight($user, 'take_survey');

$fields = [
    'Timestamp',
    'Name_of_Needle_Technology_Staff',
    'Contact_of_Needle_Technology_Staff',
    'Name_of_State',
    'Anambra_LGA',
    'Bayelsa_LGAs',
    'Cross_River_LGAs',
    'Delta_LGAs',
    'Ebonyi_LGAs',
    'Ekiti_LGAs',
    'Gombe_LGAs',
    'Imo_LGAs',
    'Ogun_LGAs',
    'Ondo_LGAs',
    'Oyo_LGAs',
    'Taraba_LGAs',
    'Yobe_LGAs',
    'Osun_LGAs',
    'Which_type_of_input_is_being_distributed',
    'Total_Number_of_Input_Received_Kg_Bags',
    'Location_of_Warehouse',
    'Name_of_Warehouse_Manager',
    'Contact_of_Warehouse_Manager',
    'Total_Number_of_Fertilizer_Received_Kg_Bags',
    'Type_of_Fertilizer',
    'If_others_please_specify_the_type_of_fertilizer',
    'Name_of_Banks_Representative',
    'Contact_of_Banks_Representative',
    'Name_of_Driver',
    'Vehicle_Registration_Number',
    'Date_of_Distribution',
    'Does_the_Bank_have_Joint_Custody_of_the_Warehouse',
    'Is_there_any_other_information_we_should_know',
    'Please_upload_any_images_from_the_distribution',
];

createRecords('input_inventory', $fields, readJsonBody());
