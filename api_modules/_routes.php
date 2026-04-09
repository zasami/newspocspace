<?php
return [
    'auth' => [
        'login', 'logout', 'me', 'request_reset', 'reset_password',
        'update_profile', 'update_password', 'upload_avatar',
    ],
    'sync' => [
        'sync_delta',
    ],
    'planning' => [
        'get_planning_hebdo', 'get_planning_mois',
        'get_mon_planning', 'get_modules_list',
    ],
    'vacances' => [
        'get_vacances_annee', 'submit_vacances', 'annuler_vacances', 'modifier_vacances',
    ],
    'desirs' => [
        'get_mes_desirs', 'submit_desir', 'update_desir', 'delete_desir',
        'get_mes_permanents', 'submit_desir_permanent', 'update_desir_permanent', 'delete_desir_permanent',
        'get_horaires_types',
    ],
    'absences' => [
        'get_mes_absences', 'submit_absence', 'upload_absence_justificatif',
        'get_absences_collegues',
    ],
    'messages' => [
        'get_mes_messages', 'send_message', 'mark_message_read',
    ],
    'messages_internes' => [
        'get_inbox', 'get_sent', 'get_message_detail', 'send_message',
        'upload_message_attachment', 'download_attachment', 'delete_message',
        'get_unread_count', 'get_message_contacts',
        'save_draft', 'delete_draft',
    ],
    'votes' => [
        'get_proposals_ouvertes', 'get_proposal_planning', 'submit_vote',
    ],
    'pv' => [
        'get_pv_list', 'get_pv', 'get_pv_refs', 'get_recent_pv', 'rate_pv', 'comment_pv', 'toggle_pv_comment_like'
    ],
    'sondages' => [
        'get_sondages_ouverts', 'get_sondage_detail', 'submit_sondage_reponses',
    ],
    'documents' => [
        'get_documents', 'get_document_services', 'serve_document',
    ],
    'notifications' => [
        'get_notifications', 'get_notifications_count',
        'mark_notification_read', 'mark_all_notifications_read',
    ],
    'changements' => [
        'get_collegues', 'get_mes_changements', 'get_collegues_planning', 'submit_changement',
        'confirmer_changement', 'refuser_changement', 'annuler_changement', 'modifier_changement',
        'get_collegue_planning_mois', 'get_mon_planning_mois',
    ],
    'fiches_salaire' => [
        'get_mes_fiches_salaire', 'serve_fiche_salaire',
    ],
    'covoiturage' => [
        'get_covoiturage_matches', 'get_covoiturage_semaine',
        'get_covoiturage_buddies', 'add_covoiturage_buddy',
        'remove_covoiturage_buddy', 'search_covoiturage_users',
    ],
    'alerts' => [
        'get_pending_alerts', 'mark_alert_read',
    ],
    'menus' => [
        'get_menus_semaine', 'reserver_menu', 'annuler_reservation_menu',
    ],
    'repartition' => [
        'get_repartition',
    ],
    'wiki' => [
        'get_wiki_categories', 'get_wiki_pages', 'get_wiki_page',
        'get_annonces_list', 'get_annonce_detail',
    ],
    'mur' => [
        'get_mur_config', 'get_mur_feed', 'create_mur_post', 'update_mur_post', 'delete_mur_post',
        'toggle_mur_like', 'get_mur_comments', 'add_mur_comment', 'delete_mur_comment',
        'get_mur_stats', 'upload_mur_media', 'get_mur_gallery', 'search_mur_users',
        'update_mur_comment',
    ],
    'cuisine' => [
        'cuisine_get_menus_semaine', 'cuisine_save_menu', 'cuisine_delete_menu',
        'cuisine_get_reservations_collab',
        'cuisine_add_commande', 'cuisine_delete_commande', 'cuisine_search_users',
        'cuisine_get_residents', 'cuisine_search_visiteurs', 'cuisine_save_visiteur',
        'cuisine_get_reservations_famille', 'cuisine_save_reservation_famille', 'cuisine_delete_reservation_famille',
        'cuisine_get_vip', 'cuisine_save_vip',
        'cuisine_get_vip_session', 'cuisine_create_vip_session', 'cuisine_save_vip_session_menu',
        'cuisine_add_vip_resident', 'cuisine_remove_vip_resident',
        'cuisine_add_vip_accompagnateur', 'cuisine_remove_vip_accompagnateur',
        'cuisine_close_vip_session',
    ],
];
