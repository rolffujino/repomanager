<?php
require_once(ROOT.'/functions/common-functions.php');

class Login extends Model {
    public $db;
    protected $username;
    protected $password;
    protected $role;

    public function __construct()
    {
        /**
         *  Ouverture de la base de données
         */
        $this->getConnection('main', 'rw');
    }

    private function setUsername(string $username)
    {
        $this->username = validateData($username);
    }

    private function setPassword(string $password)
    {
        $this->password = validateData($password);
    }

    private function setName(string $name)
    {
        $this->name = validateData($name);
    }

    private function setRole(string $role)
    {
        $this->role = validateData($role);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getRole()
    {
        return $this->role;
    }

    private function db_getHashedPassword(string $username)
    {
        try {
            $stmt = $this->db->prepare("SELECT Password FROM users WHERE username = :username AND State = 'active'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return;
        }

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $password = $row['Password'];

        return $password;
    }

    private function generateRandomPassword()
    {
        $combinaison = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@$+-=%|{}[]&";
        $shuffle = str_shuffle($combinaison);

        return substr($shuffle,0,16);
    }

    /**
     *  Récupère toutes les informations d'un utilisateur en base de données
     */
    public function getAll(string $username)
    {
        try {
            $stmt = $this->db->prepare("SELECT users.Username, users.First_name, user_role.Name as Role_name FROM users JOIN user_role ON users.Role = user_role.Id WHERE Username = :username AND State = 'active'");
            $stmt->bindValue(':username', validateData($username));
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->setName($row['First_name']);
            $this->setRole($row['Role_name']);
        }

        return true;
    }

    /**
     *  Renvoie la liste des utilisateurs en base de données
     */
    public function getUsers()
    {
        try {
            $result = $this->db->query("SELECT users.Username, users.First_name, users.Last_name, users.Email, users.Type, user_role.Name as Role_name FROM users JOIN user_role ON users.Role = user_role.Id WHERE State = 'active' ORDER BY Username ASC");
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return;
        }

        $datas = array();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $datas[] = $row;

        return $datas;
    }

    /**
     *  Ajoute un nouvel utilisateur en base de données
     */
    public function addUser(string $username)
    {
        $username = validateData($username);

        /**
         *  On vérifie que le nom d'utilisateur n'est pas déjà utilisé
         */
        try {
            $stmt = $this->db->prepare("SELECT Id FROM users WHERE Username = :username AND State = 'active'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        if ($this->db->isempty($result) === false) {
            printAlert("Le nom d'utilisateur <b>$username</b> est déjà utilisé", 'error');
            return false;
        }

        /**
         *  Génération d'un nouveau mot de passe aléatoire
         */
        $password = $this->generateRandomPassword();

        /**
         *  Hashage du mot de passe avec un salt généré automatiquement
         */
        $password_hashed = password_hash($password, PASSWORD_BCRYPT);
        if ($password_hashed === false) {
            printAlert("Erreur lors de la création de l'utilisateur", 'error');
            return false;
        }

        /**
         *  Insertion de l'username, du mdp hashé et son salt en base de données
         */
        try {
            $stmt = $this->db->prepare("INSERT INTO users ('Username', 'Password', 'First_name', 'Role', 'State', 'Type') VALUES (:username, :password, :first_name, '3', 'active', 'local')");
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':password', $password_hashed);
            $stmt->bindValue(':first_name', $username);
            $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        printAlert("L'utilisateur <b>$username</b> a été créé", 'success');

        History::set($_SESSION['username'], "Création de l'utilisateur $username", 'success');

        /**
         *  On retourne le mot de passe temporaire généré afin que l'utilisateur puisse le récupérer
         */
        return $password;
    }

    /**
     *  Vérification en base de données que le couple username / password renseigné est valide (existe en base de données)
     */
    public function checkUsernamePwd(string $username, string $password)
    {
        $username = validateData($username);

        /**
         *  On récupère le username et le mot de passe haché en base de données correspondant à l'username fourni
         */
        try {
            $stmt = $this->db->prepare("SELECT Username, Password FROM users WHERE Username = :username AND State = 'active' AND Type = 'local'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        /**
         *  Si le résultat est vide, cela signifie que l'username n'existe pas en BDD
         */
        if ($this->db->isempty($result)) return false;

        /**
         *  Si le résultat est non-vide alors on vérifie que le mot de passe fourni correspond au hash en base de données
         */
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) $password_hashed = $row['Password'];

        /**
         *  Si les mots de passe ne correspondent pas on retourne false
         */
        if (!password_verify($password, $password_hashed)) {
            History::set($username, 'Authentification', 'error');
            return false;
        }

        History::set($username, 'Authentification', 'success');
        
        return true;
    }

    /**
     *  Vérification auprès du serveur LDAP que le couple username / password renseigné est valide
     */
    public function connLdap(string $username, string $password)
    {
        /**
         *  Si aucun serveur ldap n'est configuré alors on quitte
         */
        if (!defined('LDAP_SERVER')) {
            return false;
        }

        // Eléments d'authentification LDAP
        $ldaprdn  = 'uname';     // DN ou RDN LDAP
        $ldappass = 'password';  // Mot de passe associé

        // Connexion au serveur LDAP
        $ldapconn = ldap_connect("ldap://ldap.example.com")
            or die("Impossible de se connecter au serveur LDAP.");

        if ($ldapconn) {

            // Connexion au serveur LDAP
            $ldapbind = ldap_bind($ldapconn, $ldaprdn, $ldappass);

            // Vérification de l'authentification
            if ($ldapbind) {
                echo "Connexion LDAP réussie...";
            } else {
                echo "Connexion LDAP échouée...";
            }

        }

        return true;
    }

    /**
     *  Modification des informations personnelles d'un utilisateur
     */
    public function edit(string $username, string $first_name = null, string $last_name = null, string $email = null)
    {
        /**
         *  Vérification des données renseignées
         */
        $username = validateData($username);
        if (!empty($first_name)) $first_name = validateData($first_name);
        if (!empty($last_name))  $last_name = validateData($last_name);
        if (!empty($email)) {
            if (validateMail($email) === false) {
                printAlert("L'adresse email est incorrecte", 'error');
                return;
            }
        }

        /**
         *  Mise à jour en base de données
         */
        try {
            $stmt = $this->db->prepare("UPDATE users SET First_name = :first_name, Last_name = :last_name, Email = :email WHERE Username = :username AND State = 'active'");
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':first_name', $first_name);
            $stmt->bindValue(':last_name', $last_name);
            $stmt->bindValue(':email', $email);
            $stmt->execute();
        } catch(Exception $e) {
            printAlert('Erreur lors de la modification des paramètres', 'error');
            return;
        }

        /**
         *  On modifie les valeurs en session par les valeurs qui ont été renseignées
         */
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['email']      = $email;

        History::set($_SESSION['username'], "Modifications des informations personnelles", 'success');

        printAlert('Les modifications ont été appliquées', 'success');
    }

    /**
     *  Modification du mot de passe d'un utilisateur
     */
    public function changePassword(string $username, string $actual_password, string $new_password, string $new_password2)
    {
        $username        = validateData($username);
        $actual_password = validateData($actual_password);
        $new_password    = validateData($new_password);
        $new_password2   = validateData($new_password2);

        /**
         *  On vérifie que le mot de passe actuel saisi correspond au mot de passe actuel en base de données
         */
        $actual_password_hashed = $this->db_getHashedPassword($username);

        /**
         *  Si le hash récupéré est vide alors il y a une erreur, on quitte
         */
        if (empty($actual_password_hashed)) return;

        /**
         *  On vérifie que le nouveau mot de passe renseigné et sa re-saisie sont les mêmes
         */
        if ($new_password !== $new_password2) {
            printAlert('Le nouveau mot de passe et sa re-saisie sont différents', 'error');
            return;
        }
   
        /**
         *  On vérifie que le nouveau mot de passe renseigné et l'ancien (hashé en bdd) sont différents
         */
        if (password_verify($new_password, $actual_password_hashed)) {
            printAlert('Le nouveau mot de passe est identique à l\'ancien mot de passe', 'error');
            return;
        }

        /**
         *  On hash le nouveau mot de passe renseigné
         */
        $new_password_hashed = password_hash($new_password, PASSWORD_BCRYPT);

        /**
         *  On modifie le mot de passe en base de données
         */
        try {
            $stmt = $this->db->prepare("UPDATE users SET Password = :new_password WHERE username = :username AND State = 'active' AND Type = 'local'");
            $stmt->bindValue(':new_password', $new_password_hashed);
            $stmt->bindValue(':username', $username);
            $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return;
        }

        History::set($_SESSION['username'], "Modification du mot de passe", 'success');

        printAlert('Le mot de passe a bien été changé', 'success');
    }

    /**
     *  Réinitialisation du mot de passe d'un utilisateur
     */
    public function resetPassword(string $username)
    {
        $username = validateData($username);

        /**
         *  Vérification de l'existance de l'utilisateur en base de données
         */
        try {
            $stmt = $this->db->prepare("SELECT Id FROM users WHERE Username = :username AND State = 'active' AND Type = 'local'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        if ($this->db->isempty($result) === true) {
            printAlert("L'utilisateur <b>$username</b> n'existe pas", 'error');
            return false;
        }

        /**
         *  Génération d'un nouveau mot de passe
         */
        $password = $this->generateRandomPassword();

        /**
         *  Hashage du mot de passe avec un salt généré automatiquement
         */
        $password_hashed = password_hash($password, PASSWORD_BCRYPT);
        if ($password_hashed === false) {
            printAlert("Erreur lors de la création de l'utilisateur <b>$username</b>", 'error');
            return false;
        }

        /**
         *  Ajout du nouveau mot de passe hashé en base de données
         */
        try {
            $stmt = $this->db->prepare("UPDATE users SET Password = :password WHERE Username = :username AND State = 'active' AND Type = 'local'");
            $stmt->bindValue(':username', $username);
            $stmt->bindValue(':password', $password_hashed);
            $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return false;
        }

        History::set($_SESSION['username'], "Réinitialisation du mot de passe de l'utilisateur $username", 'success');

        printAlert('Le mot de passe a été regénéré', 'success');

        return $password;
    }

    /**
     *  Suppression d'un utilisateur
     */
    public function deleteUser(string $username)
    {
        $username = validateData($username);

        /**
         *  On vérifie que l'utilisateur mentionné existe en base de données
         */
        try {
            $stmt = $this->db->prepare("SELECT Id FROM users WHERE Username = :username AND State = 'active' AND Type = 'local'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return;
        }

        if ($this->db->isempty($result) === true) {
            printAlert("L'utilisateur <b>$username</b> n'existe pas", 'error');
            return;
        }

        /**
         *  Suppression de l'utilisateur en base de données
         */
        try {
            $stmt = $this->db->prepare("UPDATE users SET State = 'deleted' WHERE Username = :username AND Type = 'local'");
            $stmt->bindValue(':username', $username);
            $result = $stmt->execute();
        } catch(Exception $e) {
            printAlert('Une erreur est survenue lors de l\'exécution de la requête en base de données', 'error');
            return;
        }

        History::set($_SESSION['username'], "Suppression de l'utilisateur $username", 'success');

        printAlert("L'utilisateur <b>$username</b> a été supprimé", 'success');
    }
}
?>