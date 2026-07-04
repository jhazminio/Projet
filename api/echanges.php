<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

function erreur(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erreur' => $message]);
    exit;
}

const SELECT_SESSION = '
    SELECT s.idSession, s.idEchange, s.idApprenant, s.titre, s.dateSession, s.heureSession,
           s.format, s.statut, s.confirmeEnseignant, s.confirmeApprenant, s.qcmValide, s.qcmScore,
           e.idUser AS idEnseignant, e.categorie, e.coutHeure,
           c.nom AS competence,
           ens.nomUser AS ensNom, ens.prenomUser AS ensPrenom,
           app.nomUser AS appNom, app.prenomUser AS appPrenom
    FROM SESSION_ECHANGE s
    JOIN ECHANGE e ON e.idEchange = s.idEchange
    JOIN COMPETENCES c ON c.idCompetences = e.idComp
    JOIN USER ens ON ens.idUser = e.idUser
    JOIN USER app ON app.idUser = s.idApprenant
';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'definir':
        definir($pdo);
        break;
    case 'repondre':
        repondre($pdo);
        break;
    case 'confirmerFin':
        confirmerFin($pdo);
        break;
    case 'pourConversation':
        pourConversation($pdo);
        break;
    case 'mesSessionsApprenant':
        mesSessionsApprenant($pdo);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

// Le propriétaire de la compétence (l'enseignant) définit une session pour un apprenant
function definir(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data        = json_decode(file_get_contents('php://input'), true) ?? [];
    $idEchange   = (int) ($data['idEchange'] ?? 0);
    $idEnseignant = (int) ($data['idUser'] ?? 0);
    $idApprenant = (int) ($data['idApprenant'] ?? 0);
    $titre       = trim((string) ($data['titre'] ?? ''));
    $date        = trim((string) ($data['date'] ?? ''));
    $heure       = trim((string) ($data['heure'] ?? ''));
    $format      = ($data['format'] ?? 'virtuel') === 'presentiel' ? 'presentiel' : 'virtuel';

    if ($idEchange <= 0 || $idEnseignant <= 0 || $idApprenant <= 0 || $titre === '' || $date === '' || $heure === '') {
        erreur(400, 'Tous les champs sont requis.');
    }
    if ($idEnseignant === $idApprenant) {
        erreur(400, 'Tu ne peux pas définir une session avec toi-même.');
    }

    $stmt = $pdo->prepare('SELECT idUser FROM ECHANGE WHERE idEchange = ?');
    $stmt->execute([$idEchange]);
    $echange = $stmt->fetch();

    if (!$echange) {
        erreur(404, 'Compétence introuvable.');
    }
    if ((int) $echange['idUser'] !== $idEnseignant) {
        erreur(403, 'Seul·e la personne qui propose cette compétence peut définir une session.');
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO SESSION_ECHANGE (idEchange, idApprenant, titre, dateSession, heureSession, format, statut)
             VALUES (?, ?, ?, ?, ?, ?, \'proposee\')'
        );
        $stmt->execute([$idEchange, $idApprenant, $titre, $date, $heure, $format]);
    } catch (PDOException $e) {
        erreur(500, 'Erreur base de données (definir) : ' . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode(['idSession' => (int) $pdo->lastInsertId()]);
}

// L'apprenant accepte ou refuse la session proposée
function repondre(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idSession = (int) ($data['idSession'] ?? 0);
    $idUser    = (int) ($data['idUser'] ?? 0);
    $reponse   = $data['reponse'] ?? '';

    $session = chargerSession($pdo, $idSession);
    if ((int) $session['idApprenant'] !== $idUser) {
        erreur(403, "Seul·e l'apprenant·e peut répondre à cette proposition.");
    }
    if ($session['statut'] !== 'proposee') {
        erreur(409, 'Cette session a déjà reçu une réponse.');
    }

    $nouveauStatut = $reponse === 'accepter' ? 'en_cours' : 'annulee';
    $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET statut = ? WHERE idSession = ?');
    $stmt->execute([$nouveauStatut, $idSession]);

    echo json_encode(['statut' => $nouveauStatut]);
}

// L'enseignant ou l'apprenant confirme que le cours a bien eu lieu
function confirmerFin(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erreur(405, 'Méthode non autorisée.');
    }

    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $idSession = (int) ($data['idSession'] ?? 0);
    $idUser    = (int) ($data['idUser'] ?? 0);

    $session = chargerSession($pdo, $idSession);
    if ($session['statut'] !== 'en_cours') {
        erreur(409, 'Cette session n\'est pas en cours.');
    }

    if ((int) $session['idEnseignant'] === $idUser) {
        $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET confirmeEnseignant = 1 WHERE idSession = ?');
        $stmt->execute([$idSession]);
    } elseif ((int) $session['idApprenant'] === $idUser) {
        $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET confirmeApprenant = 1 WHERE idSession = ?');
        $stmt->execute([$idSession]);
    } else {
        erreur(403, "Tu ne fais pas partie de cette session.");
    }

    $session = chargerSession($pdo, $idSession);
    if ($session['confirmeEnseignant'] && $session['confirmeApprenant']) {
        $stmt = $pdo->prepare('UPDATE SESSION_ECHANGE SET statut = \'terminee\' WHERE idSession = ?');
        $stmt->execute([$idSession]);
    }

    echo json_encode(chargerSession($pdo, $idSession));
}

// La session la plus récente (non annulée) entre l'utilisateur connecté et un autre utilisateur
function pourConversation(PDO $pdo): void
{
    $idUser  = (int) ($_GET['idUser'] ?? 0);
    $idAutre = (int) ($_GET['idAutre'] ?? 0);

    if ($idUser <= 0 || $idAutre <= 0) {
        erreur(400, 'Paramètres invalides.');
    }

    try {
        $stmt = $pdo->prepare(
            SELECT_SESSION . '
            WHERE ((e.idUser = ? AND s.idApprenant = ?) OR (e.idUser = ? AND s.idApprenant = ?))
              AND s.statut != \'annulee\'
            ORDER BY s.dateCreation DESC
            LIMIT 1'
        );
        $stmt->execute([$idUser, $idAutre, $idAutre, $idUser]);
        $session = $stmt->fetch();

        echo json_encode($session ?: null);
    } catch (PDOException $e) {
        erreur(500, 'Erreur base de données (pourConversation) : ' . $e->getMessage());
    }
}

// Les sessions où l'utilisateur connecté est l'apprenant ("Ce que je reçois")
function mesSessionsApprenant(PDO $pdo): void
{
    $idUser = (int) ($_GET['idUser'] ?? 0);
    if ($idUser <= 0) {
        erreur(400, 'idUser requis.');
    }

    try {
        $stmt = $pdo->prepare(SELECT_SESSION . ' WHERE s.idApprenant = ? ORDER BY s.dateCreation DESC');
        $stmt->execute([$idUser]);
        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        erreur(500, 'Erreur base de données (mesSessionsApprenant) : ' . $e->getMessage());
    }
}

function chargerSession(PDO $pdo, int $idSession): array
{
    if ($idSession <= 0) {
        erreur(400, 'idSession requis.');
    }
    try {
        $stmt = $pdo->prepare(SELECT_SESSION . ' WHERE s.idSession = ?');
        $stmt->execute([$idSession]);
    } catch (PDOException $e) {
        erreur(500, 'Erreur base de données (session) : ' . $e->getMessage());
    }
    $session = $stmt->fetch();

    if (!$session) {
        erreur(404, 'Session introuvable.');
    }
    return $session;
}
