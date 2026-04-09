<?php
return [
    'dashboard' => [
        'admin_get_dashboard_stats', 'admin_get_system_info', 'admin_get_session_ping',
    ],
    'users' => [
        'admin_get_users', 'admin_get_user', 'admin_search_users', 'admin_create_user',
        'admin_update_user', 'admin_toggle_user', 'admin_reset_user_password',
        'admin_upload_user_avatar', 'admin_delete_user_avatar', 'admin_flag_photo',
        'admin_delete_user_permanent',
        'admin_get_user_permissions', 'admin_save_user_permissions',
    ],
    'planning' => [
        'admin_get_planning', 'admin_save_assignation', 'admin_delete_assignation',
        'admin_create_planning', 'admin_finalize_planning',
        'admin_generate_planning', 'admin_get_planning_stats',
        'admin_get_planning_refs', 'admin_clear_planning',
        'admin_send_planning_email', 'admin_get_planning_archives',
    ],
    'desirs' => [
        'admin_get_desirs', 'admin_validate_desir', 'admin_get_user_permanents',
        'admin_get_permanents_pending', 'admin_validate_permanent',
    ],
    'absences' => [
        'admin_get_absences', 'admin_validate_absence', 'admin_set_remplacement',
        'admin_upload_justificatif', 'admin_delete_justificatif',
    ],
    'modules' => [
        'admin_get_modules', 'admin_create_module', 'admin_update_module', 'admin_delete_module',
        'admin_get_etages', 'admin_get_groupes',
        'admin_create_groupe', 'admin_update_groupe', 'admin_delete_groupe',
    ],
    'horaires' => [
        'admin_get_horaires', 'admin_create_horaire', 'admin_update_horaire', 'admin_toggle_horaire', 'admin_delete_horaire',
    ],
    'votes' => [
        'admin_get_proposals', 'admin_create_proposal', 'admin_toggle_vote_status',
        'admin_validate_proposal', 'admin_delete_proposal', 'admin_get_proposal_votes',
    ],
    'messages_internes' => [
        'admin_get_all_messages', 'admin_get_message_detail', 'admin_get_message_contacts',
        'admin_send_message', 'admin_delete_message', 'admin_restore_message',
        'admin_purge_message', 'admin_purge_all_trash', 'admin_get_message_stats',
        'admin_upload_message_attachment', 'admin_download_message_attachment',
        'admin_get_unread_counts',
    ],
    'config' => [
        'admin_get_config', 'admin_save_config', 'admin_assign_module_responsable',
        'admin_generate_structure', 'admin_update_module_config', 'admin_get_ia_usage',
        'admin_get_ia_rules', 'admin_create_ia_rule', 'admin_update_ia_rule',
        'admin_delete_ia_rule', 'admin_toggle_ia_rule',
        'admin_upload_logo', 'admin_upload_pinned_image',
    ],
    'besoins' => [
        'admin_get_besoins', 'admin_save_besoin', 'admin_delete_besoin',
        'admin_reset_module_besoins', 'admin_copy_module_besoins',
    ],
    'repartition' => [
        'admin_get_repartition',
        'admin_save_repartition_cell',
        'admin_mark_absent_repartition',
        'admin_delete_repartition_cell',
        'admin_get_repartition_modifications',
    ],
    'vacances' => [
        'admin_get_vacances', 'admin_validate_vacances', 'admin_bulk_validate_vacances',
        'admin_get_periodes_bloquees', 'admin_add_periode_bloquee', 'admin_update_periode_bloquee', 'admin_delete_periode_bloquee',
    ],
    'pv' => [
        'admin_get_pv_list', 'admin_create_pv', 'admin_get_pv', 'admin_update_pv',
        'admin_finalize_pv', 'admin_delete_pv', 'admin_restore_pv', 'admin_purge_pv',
        'admin_archive_pv', 'admin_unarchive_pv',
        'admin_validate_pv', 'admin_reject_pv', 'admin_send_pv_email',
        'admin_get_pv_refs',
        'admin_upload_pv_audio', 'admin_serve_pv_audio',
        'admin_transcribe_external', 'admin_structure_pv_external',
    ],
    'sondages' => [
        'admin_get_sondages', 'admin_get_sondage', 'admin_create_sondage',
        'admin_update_sondage', 'admin_toggle_sondage', 'admin_delete_sondage',
        'admin_generate_sondage_questions',
    ],
    'documents' => [
        'admin_get_documents', 'admin_get_document_services', 'admin_upload_document',
        'admin_update_document', 'admin_delete_document', 'admin_toggle_document_visibility',
        'admin_set_document_access', 'admin_get_document_access',
        'admin_create_service', 'admin_update_service', 'admin_serve_document',
        'admin_archive_document', 'admin_restore_document',
        'admin_get_document_versions', 'admin_restore_document_version', 'admin_serve_document_version',
        'admin_convert_document_pdf',
    ],
    'changements' => [
        'admin_get_changements', 'admin_valider_changement', 'admin_get_changement_detail',
    ],
    'fiches_salaire' => [
        'admin_get_fiches_salaire', 'admin_upload_fiche_salaire', 'admin_bulk_upload_fiches',
        'admin_delete_fiche_salaire', 'admin_serve_fiche_salaire',
    ],
    'import_export' => [
        'admin_export_planning', 'admin_export_users', 'admin_export_absences',
        'admin_import_users', 'admin_import_polypoint',
    ],
    'alerts' => [
        'admin_get_alerts', 'admin_get_alert_reads', 'admin_create_alert', 'admin_toggle_alert', 'admin_delete_alert',
    ],
    'todos' => [
        'admin_get_todos', 'admin_create_todo', 'admin_update_todo', 'admin_delete_todo',
        'admin_get_todo_users',
    ],
    'notes' => [
        'admin_get_notes', 'admin_create_note', 'admin_update_note', 'admin_delete_note',
        'admin_toggle_pin_note',
    ],
    'connexions' => [
        'admin_get_connexions',
    ],
    'hygiene' => [
        'admin_get_hygiene_produits', 'admin_save_hygiene_produit', 'admin_delete_hygiene_produit',
        'admin_get_hygiene_commandes', 'admin_create_hygiene_commande',
        'admin_prepare_hygiene_commandes', 'admin_deliver_hygiene_commandes',
        'admin_get_hygiene_historique', 'admin_delete_hygiene_commande',
    ],
    'protection' => [
        'admin_get_protection_produits', 'admin_save_protection_produit', 'admin_delete_protection_produit',
        'admin_get_protection_attributions', 'admin_save_protection_attribution', 'admin_delete_protection_attribution',
        'admin_get_protection_comptages', 'admin_save_protection_comptage',
        'admin_validate_protection_comptages', 'admin_deliver_protection_comptages',
        'admin_get_protection_dashboard',
    ],
    'agenda' => [
        'admin_get_agenda_events', 'admin_create_agenda_event', 'admin_update_agenda_event',
        'admin_move_agenda_event', 'admin_delete_agenda_event',
        'admin_search_agenda', 'admin_get_agenda_contacts',
    ],
    'roadmap' => [
        'admin_roadmap_toggle', 'admin_roadmap_create', 'admin_roadmap_update', 'admin_roadmap_delete',
    ],
    'mur' => [
        'admin_get_mur_config', 'admin_save_mur_config', 'admin_upload_mur_hero',
        'admin_get_mur_posts', 'admin_moderate_mur_post', 'admin_delete_mur_post',
        'admin_pin_mur_post', 'admin_delete_mur_comment', 'admin_get_mur_stats',
        'admin_search_pixabay', 'admin_save_pixabay_image',
    ],
    'residents' => [
        'admin_get_residents', 'admin_create_resident', 'admin_update_resident', 'admin_toggle_resident',
        'admin_upload_resident_photo', 'admin_serve_resident_photo', 'admin_delete_resident_photo',
    ],
    'marquage' => [
        'admin_get_marquages', 'admin_create_marquage', 'admin_upload_marquage_photo',
        'admin_serve_marquage_photo', 'admin_update_marquage_statut', 'admin_delete_marquage',
        'admin_get_marquage_history',
    ],
    'menus' => [
        'admin_get_menus', 'admin_save_menu', 'admin_delete_menu',
        'admin_get_menu_reservations', 'admin_get_reservations_jour',
        'admin_get_reservations_famille',
    ],
    'famille' => [
        'admin_famille_setup_key', 'admin_famille_get_key', 'admin_famille_get_residents',
        'admin_famille_get_activites', 'admin_famille_save_activite', 'admin_famille_delete_activite',
        'admin_famille_upload_activite_photo', 'admin_famille_delete_photo',
        'admin_famille_get_medical', 'admin_famille_save_medical', 'admin_famille_delete_medical',
        'admin_famille_upload_medical_fichier',
        'admin_famille_get_galerie', 'admin_famille_save_album', 'admin_famille_delete_album',
        'admin_famille_upload_galerie_photo', 'admin_famille_set_cover',
        'admin_famille_serve_galerie_photo',
    ],
    'stats' => [
        'admin_get_absence_stats',
    ],
    'recrutement' => [
        'admin_get_offres', 'admin_create_offre', 'admin_update_offre', 'admin_delete_offre',
        'admin_get_candidatures', 'admin_get_candidature_detail', 'admin_update_candidature_status',
        'admin_download_candidature_doc', 'admin_delete_candidature',
        'admin_get_formations', 'admin_create_formation', 'admin_update_formation', 'admin_delete_formation',
        'admin_get_formation_detail', 'admin_add_formation_participant', 'admin_remove_formation_participant',
        'admin_update_formation_participant',
        'admin_import_fegems_formations', 'admin_save_imported_formations', 'admin_import_formations_file',
    ],
    'email_externe' => [
        'admin_email_ext_get_providers', 'admin_email_ext_get_config', 'admin_email_ext_save_config', 'admin_email_ext_test',
        'admin_email_ext_get_folders', 'admin_email_ext_fetch_list', 'admin_email_ext_fetch_email',
        'admin_email_ext_download_attachment', 'admin_email_ext_delete', 'admin_email_ext_empty_trash', 'admin_email_ext_send',
        'admin_email_ext_get_contacts', 'admin_email_ext_save_contact', 'admin_email_ext_delete_contact',
        'admin_email_ext_import_contacts', 'admin_email_ext_extract_contacts',
    ],
    'care_search' => [
        'admin_care_global_search',
    ],
    'annonces' => [
        'admin_get_annonces', 'admin_get_annonce', 'admin_create_annonce',
        'admin_update_annonce', 'admin_delete_annonce', 'admin_upload_annonce_image',
        'admin_save_pixabay_annonce',
    ],
    'wiki' => [
        'admin_get_wiki_categories', 'admin_create_wiki_category', 'admin_update_wiki_category', 'admin_delete_wiki_category',
        'admin_get_wiki_pages', 'admin_get_wiki_page', 'admin_create_wiki_page',
        'admin_update_wiki_page', 'admin_delete_wiki_page', 'admin_restore_wiki_page',
        'admin_get_wiki_versions', 'admin_restore_wiki_version', 'admin_toggle_wiki_page',
        'admin_get_wiki_tags', 'admin_create_wiki_tag', 'admin_delete_wiki_tag', 'admin_set_wiki_page_tags',
        'admin_assign_wiki_expert', 'admin_verify_wiki_page', 'admin_get_wiki_expired',
        'admin_toggle_wiki_favori',
        'admin_get_wiki_page_permissions', 'admin_set_wiki_page_permissions',
        'admin_get_wiki_suggestions', 'admin_dismiss_wiki_suggestion', 'admin_get_wiki_ai_suggest',
    ],
];
