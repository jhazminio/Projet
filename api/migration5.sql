-- Migration SkillSwap #5 : nombre d'heures prévues/réelles par session
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté migration.sql, migration2.sql, migration3.sql et migration4.sql.

ALTER TABLE SESSION_ECHANGE ADD COLUMN nbHeures INT NOT NULL DEFAULT 1;
ALTER TABLE SESSION_ECHANGE ADD COLUMN heuresReelles INT NULL;
