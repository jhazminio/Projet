-- Migration SkillSwap #6 : trueque réel (1h = 1h) entre deux sessions jumelées
-- À exécuter dans phpMyAdmin, sur la base "skillswap", onglet SQL,
-- APRÈS avoir déjà exécuté migration.sql à migration5.sql.

ALTER TABLE SESSION_ECHANGE ADD COLUMN idSessionMiroir INT NULL;
ALTER TABLE SESSION_ECHANGE ADD CONSTRAINT fk_session_miroir FOREIGN KEY (idSessionMiroir) REFERENCES SESSION_ECHANGE(idSession);
