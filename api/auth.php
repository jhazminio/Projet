<?php
require_once 'config.php';

$methode = $_SERVER['REQUEST_METHOD'];
$action  = $_GET['action'] ?? '';
$data    = json_decode(file_get_contents('php://input'), true);

if ($methode === 'POST' && $action === 'inscription') {
    $prenom     = trim($data['prenomUser'] ?? '');
    $nom        = trim($data['nomUser']    ?? '');
    $email      = trim($data['email']      ?? '');
    $universite = trim($data['universite'] ?? '');
    $mdp        = $data['motDePasse']      ?? '';

    if (!$prenom || !$nom || !$email || !$mdp) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Tous les champs sont obligatoires.']);
        exit;
    }

    $conn  = getConnexion();
    $verif = $conn->prepare('SELECT idUser FROM USER WHERE email = ?');
    $verif->bind_param('s', $email);
    $verif->execute();
    if ($verif->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['erreur' => 'Cet email est déjà utilisé.']);
        exit;
    }

    $hash = password_hash($mdp, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO USER (prenomUser, nomUser, email, universite, motDePasse) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $prenom, $nom, $email, $universite, $hash);

    if ($stmt->execute()) {
        echo json_encode(['succes' => true, 'idUser' => $conn->insert_id, 'prenomUser' => $prenom, 'nomUser' => $nom, 'universite' => $universite]);
    } else {
        http_response_code(500);
        echo json_encode(['erreur' => 'Erreur lors de la création du compte.']);
    }
    $conn->close();
    exit;
}

if ($methode === 'POST' && $action === 'connexion') {
    $email = trim($data['email']    ?? '');
    $mdp   = $data['motDePasse']    ?? '';

    if (!$email || !$mdp) {
        http_response_code(400);
        echo json_encode(['erreur' => 'Email et mot de passe requis.']);
        exit;
    }

    $conn = getConnexion();
    $stmt = $conn->prepare('SELECT idUser, prenomUser, nomUser, universite, motDePasse FROM USER WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($mdp, $user['motDePasse'])) {
        http_response_code(401);
        echo json_encode(['erreur' => 'Email ou mot de passe incorrect.']);
        exit;
    }

    unset($user['motDePasse']);
    echo json_encode(['succes' => true, 'user' => $user]);
    $conn->close();
    exit;
}

http_response_code(404);
echo json_encode(['erreur' => 'Action inconnue.']);