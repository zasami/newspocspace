<?php
return [
    'auth' => [
        'login', 'logout', 'me', 'request_reset', 'reset_password',
        'update_profile', 'update_password', 'upload_avatar',
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
    'emails' => [
        'get_inbox', 'get_sent', 'get_email_detail', 'send_email',
        'upload_email_attachment', 'download_attachment', 'delete_email',
        'get_unread_count', 'get_email_contacts',
        'save_draft', 'delete_draft',
    ],
    'votes' => [
        'get_proposals_ouvertes', 'get_proposal_planning', 'submit_vote',
    ],
    'pv' => [
        'get_pv_list', 'get_pv', 'get_pv_refs', 'get_recent_pv', 'rate_pv', 'comment_pv'
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
    'cuisine' => [
        'cuisine_get_menus_semaine', 'cuisine_save_menu', 'cuisine_delete_menu',
        'cuisine_get_reservations_collab',
        'cuisine_get_residents', 'cuisine_search_visiteurs', 'cuisine_save_visiteur',
        'cuisine_get_reservations_famille', 'cuisine_save_reservation_famille', 'cuisine_delete_reservation_famille',
        'cuisine_get_vip', 'cuisine_save_vip',
    ],
];
