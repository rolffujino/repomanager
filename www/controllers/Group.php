<?php

namespace Controllers;

use Exception;

class Group
{
    private $id;
    private $name;
    private $type;
    private $model;

    public function __construct(string $type)
    {
        /**
         *  Cette class permet de manipuler des groupes de repos ou d'hôtes.
         *  Selon ce qu'on souhaite traiter, la base de données n'est pas la même.
         *  Si on a renseigné une base de données au moment de l'instanciation d'un objet Group alors on utilise cette base
         *  Sinon par défaut on utilise la base principale de repomanager
         */

        if ($type != 'repo' and $type != 'host') {
            throw new Exception("Group type is invalid");
        }

        $this->type = $type;

        $this->model = new \Models\Group($type);
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     *  Retourne l'Id du groupe à partir de son nom
     */
    public function getIdByName(string $name)
    {
        /**
         *  On vérifie que le groupe existe
         */
        if ($this->exists($name) === false) {
            throw new Exception("Group <b>$name</b> does not exist");
        }

        return $this->model->getIdByName($name);
    }

    /**
     *  Retourne le nom du groupe à partir de son Id
     */
    public function getNameById(string $id)
    {
        /**
         *  On vérifie que le groupe existe
         */
        if ($this->existsId($id) === false) {
            throw new Exception("Group Id <b>$id</b> does not exist");
        }

        return $this->model->getNameById($id);
    }

    /**
     *  Retourne true si l'Id du groupe existe en base de données
     */
    public function existsId(string $groupId)
    {
        return $this->model->existsId($groupId);
    }

    /**
     *  Vérifie si le groupe existe en base de données, à partir de son nom
     */
    public function exists(string $name = '')
    {
        return $this->model->exists($name);
    }

    /**
     *  Créer un nouveau groupe
     *  @param name
     */
    public function new(string $name)
    {
        $name = \Controllers\Common::validateData($name);

        /**
         *  1. On vérifie que le nom du groupe ne contient pas de caractères interdits
         */
        if (\Controllers\Common::isAlphanumDash($name) === false) {
            throw new Exception("Group <b>$name</b> contains invalid characters");
        }

        /**
         *  2. On vérifie que le groupe n'existe pas déjà
         */
        if ($this->exists($name) === true) {
            throw new Exception("Group name <b>$name</b> already exists");
        }

        /**
         *  3. Insertion du nouveau groupe
         */
        $this->model->add($name);

        $myhistory = new \Controllers\History();
        $myhistory->set($_SESSION['username'], 'Create a new group <span class="label-white">' . $name . '</span> (type: ' . $this->type . ')', 'success');

        \Controllers\App\Cache::clear();
    }

    /**
     *  Renommer un groupe
     *  @param actualName
     *  @param newName
     */
    public function rename(string $actualName, string $newName)
    {
        /**
         *  1. On vérifie que le nom du groupe ne contient pas des caractères interdits
         */
        if (\Controllers\Common::isAlphanumDash($actualName) === false) {
            throw new Exception("Actual group name <b>$actualName</b> contains invalid characters");
        }
        if (\Controllers\Common::isAlphanumDash($newName) === false) {
            throw new Exception("New group name <b>$newName</b> contains invalid characters");
        }

        /**
         *  2. On vérifie que le nouveau nom de groupe n'existe pas déjà
         */
        if ($this->model->exists($newName) === true) {
            throw new Exception("Group name <b>$newName</b> already exists");
        }

        /**
         *  3. Renommage du groupe
         */
        $this->model->rename($actualName, $newName);

        $myhistory = new \Controllers\History();
        $myhistory->set($_SESSION['username'], 'Rename group: <span class="label-white">' . $actualName . '</span> to <span class="label-white">' . $newName . '</span> (type: ' . $this->type . ')', 'success');

        \Controllers\App\Cache::clear();
    }

    /**
     *  Supprimer un groupe
     *  @param name
     */
    public function delete(string $name)
    {
        /**
         *  1. On vérifie que le groupe existe
         */
        if ($this->model->exists($name) === false) {
            throw new Exception("Group <b>$name</b> does not exist");
        }

        /**
         *  2. Suppression du groupe en base de données
         */
        $this->model->delete($name);

        $myhistory = new \Controllers\History();
        $myhistory->set($_SESSION['username'], 'Delete group <span class="label-white">' . $name . '</span> (type: '. $this->type . ')', 'success');

        \Controllers\App\Cache::clear();
    }

    /**
     *  Retourne les informations de tous les groupes en base de données
     *  Sauf le groupe par défaut
     */
    public function listAll()
    {
        return $this->model->listAll();
    }

    /**
     *  Retourne tous les noms de groupes en bases de données
     *  Sauf le groupe par défaut
     */
    public function listAllName()
    {
        return $this->model->listAllName();
    }

    /**
     *  Returns the names of groups in database
     *  With the default group name
     */
    public function listAllWithDefault()
    {
        $groups = $this->model->listAllName();

        /**
         *  Sort by name
         */
        asort($groups);

        /**
         *  Then add default group 'Default' to the end of the list
         */
        $groups[] = 'Default';

        return $groups;
    }

    /**
     *  Supprime des groupes les repos qui n'existent plus
     */
    public function cleanRepos()
    {
        $this->model->cleanRepos();
    }
}
