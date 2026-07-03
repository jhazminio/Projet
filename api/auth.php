<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';

function erreur(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erreur' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    erreur(405, 'Méthode non autorisée.');
}

$action = $_GET['action'] ?? '';
$data   = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

switch ($action) {
    case 'inscription':
        inscription($pdo, $data);
        break;
    case 'connexion':
        connexion($pdo, $data);
        break;
    case 'modifierProfil':
        modifierProfil($pdo, $data);
        break;
    case 'changerMotDePasse':
        changerMotDePasse($pdo, $data);
        break;
    default:
        erreur(404, 'Action inconnue.');
}

function inscription(PDO $pdo, array $data): void
{
    $nomUser     = trim($data['nomUser'] ?? '');
    $prenomUser  = trim($data['prenomUser'] ?? '');
    $email       = trim($data['email'] ?? '');
    $universite  = trim($data['universite'] ?? '');
    $motDePasse  = (string) ($data['motDePasse'] ?? '');
    $competences = is_array($data['competences'] ?? null) ? $data['competences'] : [];

    if ($nomUser === '' || $prenomUser === '' || $email === '' || $motDePasse === '') {
        erreur(400, 'Tous les champs obligatoires doivent être remplis.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        erreur(400, "L'adresse email n'est pas valide.");
    }
    if (count($competences) === 0) {
        erreur(400, 'Sélectionne au moins une compétence.');
    }

    $stmt = $pdo->prepare('SELECT idUser FROM USER WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        erreur(409, 'Un compte existe déjà avec cet email.');
    }

    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO USER (nomUser, prenomUser, email, universite, motDePasse) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nomUser, $prenomUser, $email, $universite ?: null, $hash]);
        $idUser = (int) $pdo->lastInsertId();

        $stmtFindComp   = $pdo->prepare('SELECT idCompetences FROM COMPETENCES WHERE nom = ?');
        $stmtCreateComp = $pdo->prepare('INSERT INTO COMPETENCES (nom) VALUES (?)');
        $stmtLinkComp   = $pdo->prepare(
            'INSERT INTO ECHANGE (idUser, idComp, categorie, coutHeure) VALUES (?, ?, ?, ?)'
        );

        foreach ($competences as $nomComp) {
            $nomComp = trim((string) $nomComp);
            if ($nomComp === '') {
                continue;
            }

            $stmtFindComp->execute([$nomComp]);
            $comp = $stmtFindComp->fetch();

            if ($comp) {
                $idComp = $comp['idCompetences'];
            } else {
                $stmtCreateComp->execute([$nomComp]);
                $idComp = $pdo->lastInsertId();
            }

            $stmtLinkComp->execute([$idUser, $idComp, 'Autre', 1]);
        }

        // Bonus de bienvenue
        $stmt = $pdo->prepare(
            'INSERT INTO JETON_HISTORIQUE (idUser, montant, motif) VALUES (?, 3, ?)'
        );
        $stmt->execute([$idUser, 'Bonus de bienvenue']);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        erreur(500, 'Erreur lors de la création du compte.');
    }

    http_response_code(201);
    echo json_encode([
        'idUser'      => $idUser,
        'nomUser'     => $nomUser,
        'prenomUser'  => $prenomUser,
        'email'       => $email,
        'universite'  => $universite,
        'photo'       => null,
        'competences' => array_values($competences),
    ]);
}

function connexion(PDO $pdo, array $data): void
{
    $email      = trim($data['email'] ?? '');
    $motDePasse = (string) ($data['motDePasse'] ?? '');

    if ($email === '' || $motDePasse === '') {
        erreur(400, 'Email et mot de passe requis.');
    }

    $stmt = $pdo->prepare('SELECT * FROM USER WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($motDePasse, $user['motDePasse'])) {
        erreur(401, 'Email ou mot de passe incorrect.');
    }

    unset($user['motDePasse']);
    echo json_encode(['user' => $user]);
}

function modifierProfil(PDO $pdo, array $data): void
{
    $idUser     = (int) ($data['idUser'] ?? 0);
    $nomUser    = trim((string) ($data['nomUser'] ?? ''));
    $prenomUser = trim((string) ($data['prenomUser'] ?? ''));
    $universite = trim((string) ($data['universite'] ?? ''));
    $photo      = array_key_exists('photo', $data) ? $data['photo'] : false;

    if ($idUser <= 0) {
        erreur(400, 'Utilisateur invalide.');
    }
    if ($nomUser === '' || $prenomUser === '') {
        erreur(400, 'Le nom et le prénom sont obligatoires.');
    }
    if ($photo !== false && $photo !== null && strlen((string) $photo) > 3000000) {
        erreur(400, 'Photo trop volumineuse (3 Mo max).');
    }

    if ($photo === false) {
        $stmt = $pdo->prepare('UPDATE USER SET nomUser = ?, prenomUser = ?, universite = ? WHERE idUser = ?');
        $stmt->execute([$nomUser, $prenomUser, $universite ?: null, $idUser]);
    } else {
        $stmt = $pdo->prepare('UPDATE USER SET nomUser = ?, prenomUser = ?, universite = ?, photo = ? WHERE idUser = ?');
        $stmt->execute([$nomUser, $prenomUser, $universite ?: null, $photo ?: null, $idUser]);
    }

    $stmt = $pdo->prepare('SELECT idUser, nomUser, prenomUser, email, universite, photo FROM USER WHERE idUser = ?');
    $stmt->execute([$idUser]);
    $user = $stmt->fetch();

    if (!$user) {
        erreur(404, 'Utilisateur introuvable.');
    }

    echo json_encode(['user' => $user]);
}

function changerMotDePasse(PDO $pdo, array $data): void
{
    $idUser         = (int) ($data['idUser'] ?? 0);
    $ancienMdp      = (string) ($data['ancienMotDePasse'] ?? '');
    $nouveauMdp     = (string) ($data['nouveauMotDePasse'] ?? '');

    if ($idUser <= 0 || $ancienMdp === '' || $nouveauMdp === '') {
        erreur(400, 'Tous les champs sont requis.');
    }
    if (strlen($nouveauMdp) < 6) {
        erreur(400, 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
    }

    $stmt = $pdo->prepare('SELECT motDePasse FROM USER WHERE idUser = ?');
    $stmt->execute([$idUser]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($ancienMdp, $user['motDePasse'])) {
        erreur(401, 'Mot de passe actuel incorrect.');
    }

    $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE USER SET motDePasse = ? WHERE idUser = ?');
    $stmt->execute([$hash, $idUser]);

    echo json_encode(['ok' => true]);
}
