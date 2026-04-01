<?php
return [
    'residents' => [
        'care_get_residents', 'care_create_resident', 'care_update_resident', 'care_toggle_resident',
        'care_upload_resident_photo', 'care_serve_resident_photo', 'care_delete_resident_photo',
    ],
    'marquage' => [
        'care_get_marquages', 'care_create_marquage', 'care_upload_marquage_photo',
        'care_serve_marquage_photo', 'care_update_marquage_statut', 'care_delete_marquage',
        'care_get_marquage_history',
    ],
    'famille' => [
        'care_famille_get_residents', 'care_famille_get_activites', 'care_famille_save_activite',
        'care_famille_delete_activite', 'care_famille_upload_activite_photo', 'care_famille_delete_photo',
        'care_famille_get_medical', 'care_famille_save_medical', 'care_famille_delete_medical',
        'care_famille_upload_medical_fichier',
        'care_famille_get_galerie', 'care_famille_save_album', 'care_famille_delete_album',
        'care_famille_upload_galerie_photo', 'care_famille_set_cover',
    ],
    'menus' => [
        'care_get_menus', 'care_save_menu', 'care_delete_menu',
        'care_get_menu_reservations', 'care_get_reservations_jour',
        'care_get_reservations_famille',
    ],
    'protection' => [
        'care_get_protections', 'care_create_protection', 'care_update_protection',
        'care_delete_protection', 'care_get_protection_history',
    ],
];
