<?php

namespace Models;

use Exception;

class Stat extends Model
{
    public function __construct()
    {
        /**
         *  Ouverture d'une connexion à la base de données
         */
        $this->getConnection('stats');
    }

    /**
     *  Ajoute de nouvelles statistiques à la table stats
     */
    public function add(string $date, string $time, string $repoSize, string $packagesCount, string $envId)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO stats (Date, Time, Size, Packages_count, Id_env) VALUES (:date, :time, :size, :packages_count, :envId)");
            $stmt->bindValue(':date', $date);
            $stmt->bindValue(':time', $time);
            $stmt->bindValue(':size', $repoSize);
            $stmt->bindValue(':packages_count', $packagesCount);
            $stmt->bindValue(':envId', $envId);
            $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }
    }

    /**
     *  Add new repo access log to database
     */
    public function addAccess(string $date, string $time, string $sourceHost, string $sourceIp, string $request, string $result)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO access (Date, Time, Source, IP, Request, Request_result) VALUES (:date, :time, :sourceHost, :sourceIp, :request, :result)");
            $stmt->bindValue(':date', $date);
            $stmt->bindValue(':time', $time);
            $stmt->bindValue(':sourceHost', $sourceHost);
            $stmt->bindValue(':sourceIp', $sourceIp);
            $stmt->bindValue(':request', $request);
            $stmt->bindValue(':result', $result);
            $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }
    }

    /**
     *  Retourne tout le contenu de la table stats
     */
    public function getAll(string $envId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM stats WHERE Id_env = :envId");
            $stmt->bindValue('envId', $envId);
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        $datas = array();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $datas[] = $row;
        }

        return $datas;
    }

    /**
     *  Return repo snapshot size (by its env Id) for the last specified days
     */
    public function getEnvSize(string $envId, int $days)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM (SELECT Date, Size FROM stats WHERE Id_env = :envId ORDER BY Date DESC LIMIT :days) ORDER BY Date ASC");
            $stmt->bindValue(':envId', $envId);
            $stmt->bindValue(':days', $days);
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        $datas = array();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $datas[] = $row;
        }

        return $datas;
    }

    /**
     *  Return repo snapshot packages count (by its env Id) for the last specified days
     */
    public function getPkgCount(string $envId, int $days)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM (SELECT Date, Packages_count FROM stats WHERE Id_env = :envId ORDER BY Date DESC LIMIT :days) ORDER BY Date ASC");
            $stmt->bindValue(':envId', $envId);
            $stmt->bindValue(':days', $days);
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        $datas = array();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $datas[] = $row;
        }

        return $datas;
    }

    /**
     *  Retourne le détails des 50 dernières requêtes du repo/section spécifié
     */
    public function getLastAccess(string $name, string $dist = null, string $section = null, string $env)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM access WHERE Request LIKE :request ORDER BY Date DESC, Time DESC LIMIT 50");

            if (!empty($dist) and !empty($section)) {
                $stmt->bindValue(':request', "%/${name}/${dist}/${section}_${env}/%");
            } else {
                $stmt->bindValue(':request', "%/${name}_${env}/%");
            }
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        $datas = array();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $datas[] = $row;
        }

        return $datas;
    }

    /**
     *  Retourne le détail des requêtes sur le repo/section spécifié, des 5 dernières minutes
     */
    public function getLastMinutesAccess(string $name, string $dist = null, string $section = null, string $env)
    {
        $timeEnd   = date("H:i:s");
        $timeStart = date('H:i:s', strtotime('-5 minutes', strtotime($timeEnd)));

        try {
            $stmt = $this->db->prepare("SELECT * FROM access WHERE Date = '" . DATE_YMD . "' AND Time BETWEEN '$timeStart' AND '$timeEnd' AND Request LIKE :request ORDER BY Date DESC LIMIT 30");
            if (!empty($dist) and !empty($section)) {
                $stmt->bindValue(':request', "%/${name}/${dist}/${section}_${env}/%");
            } else {
                $stmt->bindValue(':request', "%/${name}_${env}/%");
            }
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        $datas = array();

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $datas[] = $row;
        }

        return $datas;
    }

    /**
     *  Compte le nombre de requêtes d'accès au repo/section spécifié, sur une date donnée
     */
    public function getDailyAccessCount(string $name, string $dist = null, string $section = null, string $env, string $date)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM access WHERE Date = :date AND Request LIKE :request");
            if (!empty($dist) and !empty($section)) {
                $stmt->bindValue(':request', "%/${name}/${dist}/${section}_${env}/%");
            } else {
                $stmt->bindValue(':request', "%/${name}_${env}/%");
            }
            $stmt->bindValue(':date', $date);
            $result = $stmt->execute();
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }

        /**
         *  Compte le nombre de lignes retournées par la requête
         */
        $count = $this->db->count($result);

        /**
         *  Retourne le nombre de lignes
         */
        return $count;
    }

    /**
     *  Clean oldest repos statistics by deleting rows in database between specified dates
     */
    public function clean(string $dateStart, string $dateEnd)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM stats WHERE Date >= :dateStart and Date <= :dateEnd");
            $stmt->bindValue(':dateStart', $dateStart);
            $stmt->bindValue(':dateEnd', $dateEnd);
            $stmt->execute();

            $stmt = $this->db->prepare("DELETE FROM access WHERE Date >= :dateStart and Date <= :dateEnd");
            $stmt->bindValue(':dateStart', $dateStart);
            $stmt->bindValue(':dateEnd', $dateEnd);
            $stmt->execute();

            /**
             *  Clean empty space
             */
            $this->db->exec("VACUUM");
        } catch (\Exception $e) {
            \Controllers\Common::dbError($e);
        }
    }

    /**
     *  Fermeture de la connexion à la base de données
     */
    public function closeConnection()
    {
        $this->db->close();
    }
}
