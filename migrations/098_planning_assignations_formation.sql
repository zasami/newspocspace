-- ═══════════════════════════════════════════════════════════════
-- Migration 098 — Ajout du statut 'formation' à planning_assignations
-- ═══════════════════════════════════════════════════════════════
-- Permet à la génération de planning de marquer comme 'formation' les
-- jours où un collaborateur est inscrit à une formation. Les heures de
-- formation comptent comme heures travaillées et bloquent la génération.

ALTER TABLE `planning_assignations`
  MODIFY COLUMN `statut`
  ENUM('present','absent','remplace','interim','entraide','repos','vacant','formation')
  DEFAULT 'present';
