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
        $stmtLinkComp   = $pdo->prepare('INSERT INTO ECHANGE (idUser, idComp) VALUES (?, ?)');

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

            $stmtLinkComp->execute([$idUser, $idComp]);
        }

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
