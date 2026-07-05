-- Migration SkillSwap #7 : image personnalisée par compétence publiée
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté migration.sql à migration6.sql.

ALTER TABLE ECHANGE ADD COLUMN imageEchange LONGTEXT NULL;
