<?php
// POST: submit one input_cluster record (JSON object) or several (JSON array). Requires the take_survey right.
require __DIR__ . '/_bootstrap.php';

requireMethod('POST');
$user = requireApiUser();
requireApiRight($user, 'take_survey');

$fields = [
    'Timestamp',
    'Name_of_Enumerator',
    'ContactPhone_Number_of_Enumerator',
    'State',
    'LGA_RAW',
    'LGA',
    'Taraba_LGAs',
    'Town',
    'Name_Of_Cluster_Head',
    'Contact_Number_Of_Cluster_Head',
    'Cluster_Head_Image',
    'Name_of_cluster_Area',
    'Number_of_Farmer_in_the_Cluster',
    'Size_of_Cluster_Farm_LandHa',
    'Upload_Document_From_Fields_Area_Measure_Application',
    'Has_the_farm_Land_been_planted_On',
    'If_Yes_Upload_Farm_Image',
    'Latitude',
    'Longitude',
    'Oyo_LGAs',
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
    'Yobe_LGAs',
    'Osun_LGAs',
    'Comment_Pls_indicate_if_Redo_if_work_is_been_done_again',
    'which_section_of_MAAN_is_this',
];

createRecords('input_cluster', $fields, readJsonBody());
