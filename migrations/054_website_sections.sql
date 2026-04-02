-- Website CMS: sections éditables du site vitrine
CREATE TABLE IF NOT EXISTS website_sections (
    id CHAR(36) PRIMARY KEY,
    page VARCHAR(50) NOT NULL DEFAULT 'index',
    section_key VARCHAR(100) NOT NULL,
    section_type ENUM('hero','cards','timeline','services','team','values','contact','quote','text','custom') NOT NULL DEFAULT 'text',
    title VARCHAR(500) DEFAULT NULL,
    subtitle VARCHAR(1000) DEFAULT NULL,
    badge_icon VARCHAR(100) DEFAULT NULL,
    badge_text VARCHAR(200) DEFAULT NULL,
    content JSON DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by CHAR(36) DEFAULT NULL,
    UNIQUE KEY uk_page_section (page, section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: toutes les sections actuelles de index.php
INSERT INTO website_sections (id, page, section_key, section_type, title, subtitle, badge_icon, badge_text, content, sort_order) VALUES
(UUID(), 'index', 'hero', 'hero', 'Un lieu de vie<br>chaleureux <span class="ws-accent-light">au centre de Genève</span>', 'Depuis plus de 30 ans, l''EMS La Terrassière SA accompagne les personnes âgées avec respect, dignité et professionnalisme au cœur de Genève.', NULL, NULL, JSON_OBJECT(
    'stats', JSON_ARRAY(
        JSON_OBJECT('num','98','label','Collaborateurs'),
        JSON_OBJECT('num','4','label','Modules de soins'),
        JSON_OBJECT('num','24/7','label','Présence continue'),
        JSON_OBJECT('num','30+','label','Années d''expérience')
    ),
    'cta_primary', JSON_OBJECT('text','Nous contacter','href','#contact','icon','bi-telephone'),
    'cta_secondary', JSON_OBJECT('text','Découvrir','href','#services','icon','bi-arrow-down'),
    'videos', JSON_ARRAY('assets/video/6096_medium.mp4','assets/video/229069_medium.mp4','assets/video/229071_medium.mp4')
), 1),

(UUID(), 'index', 'about', 'cards', 'Une mission centrée sur <span class="ws-accent">l''humain</span>', 'L''EMS La Terrassière offre un accompagnement personnalisé dans un environnement chaleureux et sécurisant au centre de Genève, favorisant l''autonomie et le bien-être de chaque résident.', 'bi-feather', 'Notre engagement', JSON_OBJECT(
    'cards', JSON_ARRAY(
        JSON_OBJECT('icon','bi-heart-pulse','title','Soins personnalisés','text','Chaque résident bénéficie d''un plan de soins adapté à ses besoins, élaboré en concertation avec l''équipe médicale et la famille.'),
        JSON_OBJECT('icon','bi-people-fill','title','Équipe qualifiée','text','98 collaborateurs formés et passionnés — infirmières, aides-soignants, accompagnants — assurent une présence continue 7j/7.'),
        JSON_OBJECT('icon','bi-geo-alt','title','Au centre de Genève','text','Un emplacement idéal en plein cœur de la ville, facilitant les visites des proches et l''accès aux services urbains.')
    )
), 2),

(UUID(), 'index', 'quote1', 'quote', NULL, NULL, 'bi-quote', NULL, JSON_OBJECT(
    'text', 'Prendre soin, c''est offrir un regard bienveillant sur chaque instant de vie.',
    'video', 'assets/video/229069_medium.mp4'
), 3),

(UUID(), 'index', 'services', 'services', 'Des soins <span class="ws-accent">complets</span> et adaptés', NULL, 'bi-clipboard2-pulse', 'Nos prestations', JSON_OBJECT(
    'cards', JSON_ARRAY(
        JSON_OBJECT('icon','bi-clipboard2-pulse','title','Soins infirmiers','text','Administration des traitements, surveillance clinique, soins techniques et accompagnement médical quotidien par notre équipe d''infirmières diplômées.'),
        JSON_OBJECT('icon','bi-person-hearts','title','Accompagnement quotidien','text','Aide à la toilette, aux repas, aux déplacements. Nos aides-soignants qualifiés accompagnent chaque geste du quotidien avec bienveillance.'),
        JSON_OBJECT('icon','bi-emoji-smile','title','Animation & loisirs','text','Activités variées : ateliers créatifs, gymnastique douce, sorties, musique, jeux de société — pour stimuler et maintenir le lien social.'),
        JSON_OBJECT('icon','bi-moon-stars','title','Veille de nuit','text','Équipe de nuit dédiée de 20h15 à 7h15, garantissant sécurité et sérénité pour tous les résidents, avec rondes régulières.'),
        JSON_OBJECT('icon','bi-capsule','title','Suivi médical','text','Collaboration étroite avec les médecins traitants, spécialistes et pharmaciens. Gestion rigoureuse des traitements et dossiers médicaux.'),
        JSON_OBJECT('icon','bi-chat-heart','title','Soutien aux familles','text','Écoute, conseil et accompagnement des proches. Des entretiens réguliers pour maintenir le lien et informer sur l''évolution des soins.')
    )
), 4),

(UUID(), 'index', 'life', 'timeline', 'Une journée à <span class="ws-accent">La Terrassière</span>', 'Chaque journée est rythmée par des moments de soins, de partage et de détente, dans le respect du rythme de chacun.', 'bi-sun', 'Au quotidien', JSON_OBJECT(
    'items', JSON_ARRAY(
        JSON_OBJECT('time','7h00','title','Réveil en douceur','text','L''équipe de jour prend le relais. Aide au lever, toilette, habillage selon les besoins de chaque résident.'),
        JSON_OBJECT('time','8h00','title','Petit-déjeuner','text','Repas servi en salle commune ou en chambre. Menus adaptés aux régimes et préférences alimentaires.'),
        JSON_OBJECT('time','9h30','title','Soins & activités du matin','text','Soins infirmiers, visites médicales, kiné. En parallèle : ateliers mémoire, gymnastique douce, lecture.'),
        JSON_OBJECT('time','12h00','title','Déjeuner','text','Repas équilibrés préparés sur place. Moment convivial de partage entre résidents.'),
        JSON_OBJECT('time','14h00','title','Animations de l''après-midi','text','Ateliers créatifs, musique, jeux, sorties au jardin. Temps libre pour les visites des proches.'),
        JSON_OBJECT('time','18h30','title','Dîner & soirée','text','Repas du soir suivi d''un moment de détente. Préparation au coucher selon le rythme de chacun.'),
        JSON_OBJECT('time','20h15','title','Équipe de nuit','text','Relève de l''équipe de nuit. Rondes de surveillance, disponibilité continue jusqu''au matin.')
    )
), 5),

(UUID(), 'index', 'quote2', 'quote', NULL, NULL, 'bi-heart-pulse', NULL, JSON_OBJECT(
    'text', 'Chaque jour, nous cultivons le bien-être et la dignité de nos résidents.',
    'video', 'assets/video/229071_medium.mp4'
), 6),

(UUID(), 'index', 'values', 'values', 'Ce qui nous <span class="ws-accent">guide</span>', NULL, 'bi-award', 'Nos valeurs', JSON_OBJECT(
    'cards', JSON_ARRAY(
        JSON_OBJECT('icon','bi-shield-heart','title','Respect','text','Chaque résident est unique. Nous respectons sa dignité, ses choix et son rythme de vie.'),
        JSON_OBJECT('icon','bi-brightness-high','title','Bienveillance','text','Un accompagnement chaleureux et attentionné, dans un climat de confiance et de sécurité.'),
        JSON_OBJECT('icon','bi-graph-up-arrow','title','Excellence','text','Formation continue, protocoles actualisés et amélioration constante de nos pratiques.'),
        JSON_OBJECT('icon','bi-puzzle','title','Collaboration','text','Travail d''équipe entre soignants, familles et médecins pour un accompagnement global.')
    )
), 9),

(UUID(), 'index', 'contact', 'contact', 'Nous sommes à votre <span class="ws-accent">écoute</span>', 'Pour toute question, demande de visite ou renseignement sur nos prestations, n''hésitez pas à nous contacter.', 'bi-chat-dots', 'Contactez-nous', JSON_OBJECT(
    'address', 'E.M.S. La Terrassière SA\nGenève, Suisse',
    'phone', '+41 22 XXX XX XX',
    'email', 'contact@ems-la-terrassiere.ch',
    'hours', 'Tous les jours : 10h – 12h / 14h – 19h',
    'hours_note', 'Horaires flexibles sur demande'
), 10);
