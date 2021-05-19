<?php
session_start();

$servername = "localhost";
$port = "3306";
$username = "root";
$password = "mdp";
$database = "forma";

// Connexion en BDD
try {
    $link = new PDO('mysql:host=' . $servername . ';port=' . $port . ';dbname=' . $database, $username, $password);
    $link->exec('SET NAMES utf8');
} catch (Exception $e) {
    echo 'Erreur : ' . $e->getMessage() . '<br />';
    echo 'N° : ' . $e->getCode();
    die();
}

//Déconnexion
if (isset($_GET["logout"])) {
    session_destroy();
    header('location: index.php');
    exit();
}

// Inscription
if (isset($_POST["reg_name"]) && isset($_POST["reg_password"]) && isset($_POST["reg_verify_password"])) {
    $name = sanitize($_POST["reg_name"]);
    $password = trim($_POST["reg_password"]);
    $retyped_password = trim($_POST["reg_verify_password"]);

    //Ajouter des vérifs en preg_match?

    if (empty($name)) {
        $error = "Merci de spécifier un pseudo!";
    } else if (empty($password)) {
        $error = "Merci de spécifier un mot de passe!";
    } else if (empty($retyped_password)) {
        $error = "Merci de retaper le mot de passe!";
    } else if (strlen($name) < 4) {
        $error = "Le pseudo doit comporter 4 caractères minimum!";
    } else if (usernameExists($name)) {
        $error = "Le compte " . $name . " existe déjà! Merci de choisir un autre pseudo!";
    } else if (strlen($password) < 4) {
        $error = "Le mot de passe doit comporter 4 caractères minimum!";
    } else if ($password !== $retyped_password) {
        $error = "Les mots de passe ne correspondent pas!";
    } else {
        $options = [
            'cost' => 12,
        ];
        $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
        $stmt = $link->prepare("INSERT INTO users (name, password) VALUES (:name, :password)");
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':password', $hashed_password, PDO::PARAM_STR);
        $stmt->execute();
        $id = $link->lastInsertId();
        $_SESSION["logged"] = true;
        $_SESSION["username"] = $name;
        $_SESSION["user_id"] = $id;
        header('location: index.php');
    }
}

// Identification
if (isset($_POST["name"]) && isset($_POST["password"])) {
    $name = sanitize($_POST["name"]);
    $password = trim($_POST["password"]);

    if (empty($name)) {
        $error = "Merci d'entrer votre pseudo.";
    } else if (empty($password)) {
        $error = "Merci d'entrer votre mot de passe.";
    } else if (strlen($name) < 4) {
        $error = "Le pseudo doit comporter 4 caractères minimum!";
    } else {
        $stmt = $link->prepare('SELECT id, name, password FROM users WHERE name = :name LIMIT 1');
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            $error = "Compte introuvable!";
        } else {
            if (password_verify($password, $result["password"])) {
                $_SESSION["logged"] = true;
                $_SESSION["user_id"] = $result["id"];
                $_SESSION["username"] = $result["name"];
                header('location: index.php');
            } else {
                $error = "Mot de passe incorrect!";
            }
        }
    }
}

// DarkMode
$darkmode = false;
if (!isset($_SESSION["logged"])) {
    $darkmode = false;
} else if (getUserDarkMode($_SESSION["user_id"]) == "1") {
    $darkmode = true;
}

if (isset($_POST['dark_mode'])) {
    if (getUserDarkMode($_SESSION["user_id"]) == "1") {
        setUserDarkMode("0");
        $darkmode = false;
    } else {
        setUserDarkMode("1");
        $darkmode = true;
    }
    header('location: index.php');
}

//Ajout d'une note en BDD
if (isset($_POST["card_name"]) && isset($_POST["card_date"]) && isset($_POST["card_desc"])) {
    $title = sanitize($_POST["card_name"]);
    $date = sanitize($_POST["card_date"]);
    $desc = sanitize($_POST["card_desc"]);

    if (empty($title) || empty($date) || empty($desc)) {
        $error = "Il y a une erreur dans votre formulaire.";
    } else {
        $stmt = $link->prepare("INSERT INTO cards (title, user_id, date, description) VALUES (:title, :user_id, :date, :description)");
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
        $stmt->bindValue(':date', $date, PDO::PARAM_STR);
        $stmt->bindValue(':description', $desc, PDO::PARAM_STR);
        $stmt->execute();
        $success = "Vous avez ajouté une nouvelle note!";
    }
}

//Supression d'une note
if (isset($_GET["delete"])) {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
        $card_id = (int) $_GET["delete"];
        if (!deleteNote($card_id)) {
            $error = "Impossible de supprimer cette note!";
        } else {
            $success = "La note a bien été supprimée!";
        }
    } else {
        header('location: index.php');
        exit();
    }
}

//Modification d'une note
if (isset($_GET["edit"])) {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
        $card_id = (int) $_GET["edit"];
        if (isset($_POST["edit_card_name"]) && isset($_POST["edit_card_date"]) && isset($_POST["edit_card_desc"])) {
            if (!editNote($card_id)) {
                $error = "Impossible d'éditer cette note!";
            } else {
                $success = "La note a bien été éditée!";
            }
        }
    } else {
        header('location: index.php');
        exit();
    }
}

// Fonctions
function getPaginatedData()
{
    global $cards;

    $data = '';
    if ($cards) {
        foreach ($cards as $row => $card) {
            $data .= '<div class="card" style="width: 18rem; margin-top: 15px;">
       <div class="card-header text-center">' . $card["id"] . ' - <b>' . $card["title"] . '</b></div>
       <div class="card-body">
       <h6 class="card-subtitle mb-2 text-muted">' . $card["date"] . '</h6>
       <p class="card-text">' . nl2br($card["description"]) . '</p>
       </div>
       <div class="card-footer d-flex justify-content-end">
       <a href="#" data-bs-toggle="modal" data-bs-target="#editModal-' . $card["id"] . '" class="card-link">Modifier</a>
       <a href="#" data-bs-toggle="modal" data-bs-target="#deleteModal-' . $card["id"] . '" class="card-link text-danger">Supprimer</a>
       </div>
       </div>
       <div class="modal fade" id="editModal-' . $card["id"] . '" tabindex="' . $card["id"] . '" aria-labelledby="editModalLabel" aria-hidden="true">
       <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Éditer la note "<i>' . $card["title"] . '</i>"</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="index.php?edit=' . $card["id"] . '" method="post">
                <div class="modal-body">
                <div class="form-group">
                    <label for="edit_card_name">Titre de la note</label>
                    <input type="text" class="form-control" id="edit_card_name" name="edit_card_name" value="' . $card["title"] . '" required>
                </div>
                <div class="form-group">
                    <label for="edit_card_date">Date de la note</label>
                    <input type="text" class="form-control" id="edit_card_date" name="edit_card_date" value="' . $card["date"] . '">
                </div>
                <div class="form-group">
                    <label for="edit_card_desc">Description de la note</label>
                    <textarea class="form-control" id="edit_card_desc" name="edit_card_desc" rows="3">' . $card["description"] . '</textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
                </div>
                </form>
            </div>
        </div>
    </div>
       <div class="modal fade" id="deleteModal-' . $card["id"] . '" tabindex="' . $card["id"] . '" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Suppression de la note "<i>' . $card["title"] . '</i>"</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  Voulez-vous vraiment supprimer la note ayant pour titre "<i>' . $card["title"] . '</i>" ?<br />Note: Cette action est irréversible!
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <a href="./index.php?delete=' . $card["id"] . '"><button type="button" class="btn btn-danger">Supprimer</button></a>
                </div>
            </div>
        </div>
    </div>';
        }
    } else {
        $data = '<div class="alert alert-warning" role="alert">Vous n\'avez aucune note.</div>';
    }
    return $data;
}

function getSearchedData($search)
{
    $data = '';
    if (empty($search)) {
        $data = '<div class="alert alert-danger" role="alert" style="margin-top: 15px;">La recherche est vide! Merci de spécifier un mot clé!</div>';
    } else {
        global $link;
        $stmt = $link->prepare("SELECT * FROM cards WHERE title LIKE :search");
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result) {
            foreach ($result as $row => $card) {
                $data .= '<div class="card" style="width: 18rem; margin-top: 15px;">
       <div class="card-header text-center">' . $card['id'] . ' - <b>' . $card["title"] . '</b></div>
       <div class="card-body">
       <h6 class="card-subtitle mb-2 text-muted">' . $card["date"] . '</h6>
       <p class="card-text">' . nl2br($card["description"]) . '</p>
       </div>
       <div class="card-footer">
       <small>Créée par ' . getUsername($card["user_id"]) . ' le ' . createFromFormat($card["created_at"]) . '.</small>
       </div>
       </div>';
            }
        } else {
            $data = '<div class="alert alert-warning" role="alert">Aucun résultat trouvé!</div>';
        }
        return $data;
    }
}

function getUsername($id)
{
    global $link;
    $stmt = $link->prepare("SELECT name FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = "Inconnu";
    if ($result) {
        $username = $result["name"];
    }
    return $username;
}

function usernameExists($name)
{
    global $link;
    $stmt = $link->prepare("SELECT name FROM users WHERE name = :name LIMIT 1");
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        return true;
    }
    return false;
}

function getUserDarkMode($id)
{
    global $link;
    $stmt = $link->prepare("SELECT dark_mode FROM users WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':user_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result["dark_mode"];
}

function setUserDarkMode($value)
{
    global $link;
    $stmt = $link->prepare("UPDATE users SET dark_mode = :dark_mode WHERE id = :user_id LIMIT 1");
    $stmt->bindValue(':dark_mode', $value, PDO::PARAM_STR);
    $stmt->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $stmt->execute();
    //$stmt->debugDumpParams();
}

function deleteNote($id)
{
    global $link;
    $stmt = $link->prepare("SELECT * FROM cards WHERE id = :card_id AND user_id = :user_id LIMIT 1");
    $stmt->bindValue(':card_id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $delete_stmt = $link->prepare("DELETE FROM cards WHERE id = :card_id AND user_id = :user_id LIMIT 1");
        $delete_stmt->bindValue(':card_id', $result["id"], PDO::PARAM_INT);
        $delete_stmt->bindValue(':user_id', $result["user_id"], PDO::PARAM_INT);
        $delete_stmt->execute();
        return true;
    }
    return false;
}

function editNote($id)
{
    global $link;
    $stmt = $link->prepare("SELECT * FROM cards WHERE id = :card_id AND user_id = :user_id LIMIT 1");
    $stmt->bindValue(':card_id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $title = sanitize($_POST["edit_card_name"]);
        $date = sanitize($_POST["edit_card_date"]);
        $desc = sanitize($_POST["edit_card_desc"]);

        $update_stmt = $link->prepare("UPDATE cards SET title = :title, date = :date, description = :description WHERE id = :card_id AND user_id = :user_id LIMIT 1");
        $update_stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $update_stmt->bindValue(':date', $date, PDO::PARAM_STR);
        $update_stmt->bindValue(':description', $desc, PDO::PARAM_STR);
        $update_stmt->bindValue(':card_id', $result["id"], PDO::PARAM_INT);
        $update_stmt->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
        $update_stmt->execute();
        return true;
    }
    return false;
}

function createFromFormat($date)
{
    return DateTime::createFromFormat('Y-m-d H:i:s', $date)->format('d-m-Y à H:i:s');
}

function sanitize($input)
{
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.ico" />
    <?php
if ($darkmode) {?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootswatch@5.0.0/dist/darkly/bootstrap.min.css">
    <?php } else {?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-+0n0xVW2eSR5OomGNYDnhzAbDsOXxcvSN1TPprVMTNDbiYZCxYbOOl7+AMvyTG2x" crossorigin="anonymous">
    <?php }?>
    <title>Online Bloc-Note</title>
</head>

<body>
    <?php if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
    $query = $link->prepare('SELECT COUNT(*) AS nb_cards FROM cards WHERE user_id = :user_id;');
    $query->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $query->execute();
    $result = $query->fetch();
    $totalCards = (int) $result["nb_cards"];
    $perPage = 3;
    $pages = ceil($totalCards / $perPage);

    if (isset($_GET["page"]) && !empty($_GET["page"])) {
        $currentPage = sanitize($_GET["page"]);
        if (!is_numeric($currentPage)) {
            $currentPage = 1;
        } else if ($currentPage > $pages) {
            header('location: index.php');
        }
    } else {
        $currentPage = 1;
    }

    $first = ($currentPage * $perPage) - $perPage;
    $query = $link->prepare('SELECT * FROM cards WHERE user_id = :user_id ORDER BY created_at ASC LIMIT :premier, :parpage;');
    $query->bindValue(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $query->bindValue(':premier', $first, PDO::PARAM_INT);
    $query->bindValue(':parpage', $perPage, PDO::PARAM_INT);
    $query->execute();
    $cards = $query->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="navbarColor01">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="./">Accueil</a>
                        </li>
                        <?php if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="./index.php?logout">Déconnexion</a>
                        </li>
                        <?php }?>
                    </ul>
                    <form class="d-flex">
                        <input class="form-control me-sm-2" type="text" name="search" placeholder="Rechercher">
                        <button class="btn btn-primary my-2 my-sm-0" type="submit">Rechercher</button>
                    </form>
                </div>
            </div>
        </nav>
    </div>
    <div class="container" style="margin-top: 15px;">
        <p>Salut <?=$_SESSION["username"]?>, Tu peux commencer à ajouter des notes!</p>
        <?php if (isset($error)) {
        echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';}?>
        <?php if (isset($success)) {
        echo '<div class="alert alert-success" role="alert">' . $success . '</div>';}?>
        <form action="index.php" method="post">
            <div class="form-group">
                <label for="card_name">Titre de la note</label>
                <input type="text" class="form-control" id="card_name" name="card_name" required>
            </div>
            <div class="form-group">
                <label for="card_date">Date de la note</label>
                <input type="text" class="form-control" id="card_date" name="card_date">
            </div>
            <div class="form-group">
                <label for="card_desc">Description de la note</label>
                <textarea class="form-control" id="card_desc" name="card_desc" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 15px;">Enregistrer</button>
        </form>
    </div>
    <div class="container d-flex justify-content-evenly" style="margin-top: 10px;">
        <?php if (isset($_GET["search"])) {
        $search = sanitize($_GET["search"]);
        echo getSearchedData($search);
    } else {
        echo getPaginatedData();?>
    </div>
    <?php if ($totalCards > 0) {?>
    <div class="container" style="margin-top: 25px;">
        <nav>
            <ul class="pagination">
                <li class="page-item <?=($currentPage == 1) ? "disabled" : ""?>">
                    <a class="page-link" href="index.php?page=<?=$currentPage - 1?>">Précedent</a>
                </li>
                <?php for ($page = 1; $page <= $pages; $page++) {?>
                <li class="page-item <?=($currentPage == $page) ? "active" : ""?>">
                    <a class="page-link" href="index.php?page=<?=$page?>"><?=$page?></a>
                </li>
                <?php }?>
                <li class="page-item <?=($currentPage == $pages) ? "disabled" : ""?>">
                    <a class="page-link" href="index.php?page=<?=$currentPage + 1?>">Suivant</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php } ?>
    <div class="container" style="margin-top: 25px;">
        <footer>
            <p class="text-end">By Math' - Project Github: <a href="https://github.com/MathDuck/StickyPHP"
                    target="_blank">StickyPHP</a></p>
        </footer>
    </div>
    <?php } ?>
    <div class="box" style="position:absolute;right:2%;bottom:5%;">
        <form action="index.php" method="post">
            <?php if ($darkmode) {?>
            <button type="submit" class="btn btn-light btn-lg" id="dark_mode" name="dark_mode">Light Mode</button>
            <?php } else {?>
            <button type="submit" class="btn btn-dark btn-lg" id="dark_mode" name="dark_mode">Dark Mode</button>
            <?php }?>
        </form>
    </div>
    <?php } else if (isset($_GET["register"])) {?>
    <div class="container" style="margin-top:15px;">
        <?php if (isset($error)) {echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';}?>
        <form action="index.php?register" method="post">
            <div class="form-group">
                <label for="reg_name">Pseudo</label>
                <input type="text" class="form-control" id="reg_name" name="reg_name"
                    value="<?=(isset($_POST["reg_name"])) ? $_POST["reg_name"] : ""?>" required>
            </div>
            <div class="form-group">
                <label for="reg_password">Mot de passe</label>
                <input type="password" class="form-control" id="reg_password" name="reg_password" required>
            </div>
            <div class="form-group">
                <label for="reg_verify_password">Retapez votre mot de passe</label>
                <input type="password" class="form-control" id="reg_verify_password" name="reg_verify_password"
                    required>
            </div>
            <button type="submit" class="btn btn-warning" style="margin-top: 15px;">Inscription</button>
            <div class="form-group">
                <p style="margin-top: 15px;"><a href="./index.php">
                        << Retour à l'accueil</a>
                </p>
            </div>
        </form>
    </div>
    <?php } else {?>
    <div class="container" style="margin-top:15px;">
        <?php if (isset($error)) {
    echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
}?>
        <form action="index.php" method="post">
            <div class="form-group">
                <label for="name">Pseudo (Test)</label>
                <input type="text" class="form-control" id="name" name="name">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe (test)</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>
            <div class="form-group">
                <p><a href="./index.php?register">Pas de compte?</a></p>
            </div>
            <button type="submit" class="btn btn-success">Connexion</button>
        </form>
    </div>
    <?php }?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>
</body>

</html>