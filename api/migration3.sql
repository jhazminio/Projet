-- Migration SkillSwap #3 : format et support inclus par compétence publiée
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté migration.sql et migration2.sql.

ALTER TABLE ECHANGE ADD COLUMN format VARCHAR(20) NOT NULL DEFAULT 'virtuel';
ALTER TABLE ECHANGE ADD COLUMN support VARCHAR(150) NULL;
