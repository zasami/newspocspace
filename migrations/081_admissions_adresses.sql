-- Migration 081 : Découper les adresses postales en champs structurés
-- rue / complément / code postal / ville

ALTER TABLE admissions_candidats
  -- Personne concernée
  ADD COLUMN adresse_rue VARCHAR(200) NULL AFTER adresse_postale,
  ADD COLUMN adresse_complement VARCHAR(200) NULL AFTER adresse_rue,
  ADD COLUMN adresse_cp VARCHAR(10) NULL AFTER adresse_complement,
  ADD COLUMN adresse_ville VARCHAR(100) NULL AFTER adresse_cp,
  -- Personne de référence
  ADD COLUMN ref_adresse_rue VARCHAR(200) NULL AFTER ref_adresse_postale,
  ADD COLUMN ref_adresse_complement VARCHAR(200) NULL AFTER ref_adresse_rue,
  ADD COLUMN ref_adresse_cp VARCHAR(10) NULL AFTER ref_adresse_complement,
  ADD COLUMN ref_adresse_ville VARCHAR(100) NULL AFTER ref_adresse_cp,
  -- Médecin
  ADD COLUMN med_adresse_rue VARCHAR(200) NULL AFTER med_adresse_postale,
  ADD COLUMN med_adresse_complement VARCHAR(200) NULL AFTER med_adresse_rue,
  ADD COLUMN med_adresse_cp VARCHAR(10) NULL AFTER med_adresse_complement,
  ADD COLUMN med_adresse_ville VARCHAR(100) NULL AFTER med_adresse_cp;

-- Note : les anciennes colonnes `adresse_postale`, `ref_adresse_postale`, `med_adresse_postale`
-- sont conservées nullables (legacy). Les nouvelles soumissions remplissent uniquement les colonnes structurées.
