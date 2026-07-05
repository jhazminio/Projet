<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

function erreur(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erreur' => $message]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'laisser':
        laisser($pdo);
        break;
    case 'pourUtilisateur':
        pourUtilisateur($pdo);
        break;
    case 'statsUtilisateur':
        statsUtilisateur($pdo);
        break;
    case 'sessionsAEvaluer':
        sessionsAEvaluer($pdo);
        break;
    case 'modifier':
        modifier($pdo);
        break;
    case 'supprimer':
        supprimer($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

// Force le typage des champs numériques/booléens (PDO renvoie des chaînes avec MySQL)
function normaliserAvis(array $a): array
{
    foreach (['idAvis', 'idSession', 'idAuteur', 'idAutre', 'note', 'autreEstEnseignant'] as $champ) {
        if (isset($a[$champ])) {
            $a[$champ] = (int) $a[$champ];
        }
    }
    return $a;
}

function normaliserAvisListe(array $liste): array
{
    return array_map('normaliserAvis', $liste);
}

function chargerSessionValidee(PDO $pdo, int $idSession): array
{
    $stmt = $pdo->prepare(
        'SELECT s.idSession, s.statut, s.idApprenant, e.idUser AS idEnseignant
         FROM SESSION_ECHANGE s
         JOIN ECHANGE e ON e.idEchange = s.idEchange
         WHERE s.idSession = ?'
    );
    $stmt->execute([$idSession]);
    $session = $stmt->fetch();

    if (!$session) {
        erreur(404, 'Session introuvable.');
    }
    if ($session['statut'] !== 'validee') {
        erreur(409, "Tu ne peux laisser un avis qu'après une session validée.");
    }

    return $session;
}

// Laisse un avis sur l'autre participant d'une session validée
function laisser(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data       = json_decode(file_get_contents('php://input'), true) ?? [];
    $idSession  = (int) ($data['idSession'] ?? 0);
    $idAuteur   = (int) ($data['idAuteur'] ?? 0);
    $note       = (int) ($data['note'] ?? 0);
    $commentaire = trim((string) ($data['commentaire'] ?? ''));

    if ($idSession <= 0 || $idAuteur <= 0) {
        erreur(400, 'Paramètres invalides.');
    }
    if ($note < 1 || $note > 5) {
        erreur(400, 'La note doit être comprise entre 1 et 5.');
    }

    $session = chargerSessionValidee($pdo, $idSession);

    if ($idAuteur === (int) $session['idEnseignant']) {
        $idCible = (int) $session['idApprenant'];
    } elseif ($idAuteur === (int) $session['idApprenant']) {
        $idCible = (int) $session['idEnseignant'];
    } else {
        erreur(403, 'Tu ne fais pas partie de cette session.');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO AVIS (idSession, idAuteur, idCible, note, commentaire) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$idSession, $idAuteur, $idCible, $note, $commentaire ?: null]);
    } catch (PDOException $e) {
        erreur(409, 'Tu as déjà laissé un avis pour cette session.');
    }

    http_response_code(201);
    echo json_encode(['ok' => true]);
}

// Avis reçus par un utilisateur
function pourUtilisateur(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT a.idAvis, a.note, a.commentaire, a.dateAvis,
                u.idUser AS idAuteur, u.nomUser, u.prenomUser,
                c.nom AS competence
         FROM AVIS a
         JOIN USER u ON u.idUser = a.idAuteur
         JOIN SESSION_ECHANGE s ON s.idSession = a.idSession
         JOIN ECHANGE e ON e.idEchange = s.idEchange
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         WHERE a.idCible = ?
         ORDER BY a.dateAvis DESC'
    );
    $stmt->execute([$idUser]);
    echo json_encode(normaliserAvisListe($stmt->fetchAll()));
}

// Statistiques agrégées (note moyenne, nb d'avis, nb d'échanges validés) pour un profil
function statsUtilisateur(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare('SELECT AVG(note) AS moyenne, COUNT(*) AS total FROM AVIS WHERE idCible = ?');
    $stmt->execute([$idUser]);
    $avis = $stmt->fetch();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM SESSION_ECHANGE s
         JOIN ECHANGE e ON e.idEchange = s.idEchange
         WHERE s.statut = \'validee\' AND (e.idUser = ? OR s.idApprenant = ?)'
    );
    $stmt->execute([$idUser, $idUser]);
    $echanges = $stmt->fetch();

    echo json_encode([
        'moyenne'        => $avis['moyenne'] !== null ? round((float) $avis['moyenne'], 1) : null,
        'totalAvis'      => (int) $avis['total'],
        'totalEchanges'  => (int) $echanges['total'],
    ]);
}

// Sessions validées de l'utilisateur pour lesquelles il n'a pas encore laissé d'avis
function sessionsAEvaluer(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    $stmt = $pdo->prepare(
        'SELECT s.idSession, s.titre, c.nom AS competence,
                CASE WHEN e.idUser = ? THEN s.idApprenant ELSE e.idUser END AS idAutre,
                CASE WHEN e.idUser = ? THEN 0 ELSE 1 END AS autreEstEnseignant,
                autre.nomUser AS autreNom, autre.prenomUser AS autrePrenom
         FROM SESSION_ECHANGE s
         JOIN ECHANGE e ON e.idEchange = s.idEchange
         JOIN COMPETENCES c ON c.idCompetences = e.idComp
         JOIN USER autre ON autre.idUser = CASE WHEN e.idUser = ? THEN s.idApprenant ELSE e.idUser END
         WHERE s.statut = \'validee\'
           AND (e.idUser = ? OR s.idApprenant = ?)
           AND NOT EXISTS (SELECT 1 FROM AVIS a WHERE a.idSession = s.idSession AND a.idAuteur = ?)
         ORDER BY s.dateCreation DESC'
    );
    $stmt->execute([$idUser, $idUser, $idUser, $idUser, $idUser, $idUser]);
    echo json_encode(normaliserAvisListe($stmt->fetchAll()));
}

// Modifie un avis déjà laissé (seul l'auteur peut le faire)
function modifier(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data        = json_decode(file_get_contents('php://input'), true) ?? [];
    $idAvis      = (int) ($data['idAvis'] ?? 0);
    $idAuteur    = (int) ($data['idAuteur'] ?? 0);
    $note        = (int) ($data['note'] ?? 0);
    $commentaire = trim((string) ($data['commentaire'] ?? ''));

    if ($idAvis <= 0 || $idAuteur <= 0) {
        erreur(400, 'Paramètres invalides.');
    }
    if ($note < 1 || $note > 5) {
        erreur(400, 'La note doit être comprise entre 1 et 5.');
    }

    $stmt = $pdo->prepare('SELECT idAuteur FROM AVIS WHERE idAvis = ?');
    $stmt->execute([$idAvis]);
    $avis = $stmt->fetch();
    if (!$avis) {
        erreur(404, 'Avis introuvable.');
    }
    if ((int) $avis['idAuteur'] !== $idAuteur) {
        erreur(403, "Tu ne peux modifier que tes propres avis.");
    }

    $stmt = $pdo->prepare('UPDATE AVIS SET note = ?, commentaire = ? WHERE idAvis = ?');
    $stmt->execute([$note, $commentaire ?: null, $idAvis]);

    echo json_encode(['ok' => true]);
}

// Supprime un avis déjà laissé (seul l'auteur peut le faire)
function supprimer(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $idAvis   = (int) ($data['idAvis'] ?? 0);
    $idAuteur = (int) ($data['idAuteur'] ?? 0);

    if ($idAvis <= 0 || $idAuteur <= 0) {
        erreur(400, 'Paramètres invalides.');
    }

    $stmt = $pdo->prepare('SELECT idAuteur FROM AVIS WHERE idAvis = ?');
    $stmt->execute([$idAvis]);
    $avis = $stmt->fetch();
    if (!$avis) {
        erreur(404, 'Avis introuvable.');
    }
    if ((int) $avis['idAuteur'] !== $idAuteur) {
        erreur(403, "Tu ne peux supprimer que tes propres avis.");
    }

    $pdo->prepare('DELETE FROM AVIS WHERE idAvis = ?')->execute([$idAvis]);

    echo json_encode(['ok' => true]);
}
