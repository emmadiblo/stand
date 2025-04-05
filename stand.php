<?php

/**
 * Bibliothèque de fonctions pour la gestion de bases de données et de sessions.
 * 
 * @author Emmadiblo
 * https://github.com/emmadiblo
 * @version 1.0.0
 */

declare(strict_types=1);

/**
 * Établit une connexion à la base de données.
 *
 * @param string $host Adresse de l'hôte de la base de données.
 * @param string $username Nom d'utilisateur de la base de données.
 * @param string $password Mot de passe de la base de données.
 * @param string $database Nom de la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param array $options Options supplémentaires pour la connexion PDO.
 *
 * @return mysqli|PDO Instance de connexion à la base de données.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur de connexion.
 */
function ConnDB(
    string $host, 
    string $username, 
    string $password, 
    string $database, 
    string $connType = 'pdo', 
    array $options = []
): mysqli|PDO {
    if ($connType === 'mysqli') {
        $conn = @new mysqli($host, $username, $password, $database);
        if ($conn->connect_error) {
            throw new Exception("Erreur de connexion MySQLi : " . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    } elseif ($connType === 'PDO') {
        try {
            // Options par défaut pour PDO
            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            // Fusionner avec les options utilisateur
            $pdoOptions = array_merge($defaultOptions, $options);
            
            $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, $pdoOptions);
            return $conn;
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion PDO : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Exécute une requête SQL personnalisée.
 *
 * @param string $sql Requête SQL à exécuter.
 * @param array $params Paramètres pour la requête préparée.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param bool $fetchAll Si true, retourne tous les résultats (pour les SELECT uniquement).
 *
 * @return mixed Résultat de la requête, dépend du type de requête.
 * @throws Exception Si une erreur se produit lors de l'exécution de la requête.
 */
function Query(
    string $sql, 
    array $params = [], 
    mysqli|PDO $conn, 
    string $connType, 
    bool $fetchAll = true
): mixed {
    if ($connType === 'mysqli') {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }

        if (!empty($params)) {
            // Déterminer les types de paramètres
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 's'; // Par défaut
                }
            }
            
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        
        // Si c'est une requête SELECT
        if (preg_match('/^\s*SELECT/i', $sql)) {
            $result = $stmt->get_result();
            if ($fetchAll) {
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $stmt->close();
                return $data;
            } else {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row;
            }
        } 
        // Pour les requêtes INSERT
        elseif (preg_match('/^\s*INSERT/i', $sql)) {
            $insertId = $conn->insert_id;
            $stmt->close();
            return $insertId;
        } 
        // Pour les autres requêtes (UPDATE, DELETE)
        else {
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            return $affectedRows;
        }

    } elseif ($connType === 'PDO') {
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            // Si c'est une requête SELECT
            if (preg_match('/^\s*SELECT/i', $sql)) {
                return $fetchAll ? $stmt->fetchAll() : $stmt->fetch();
            } 
            // Pour les requêtes INSERT
            elseif (preg_match('/^\s*INSERT/i', $sql)) {
                return $conn->lastInsertId();
            } 
            // Pour les autres requêtes (UPDATE, DELETE)
            else {
                return $stmt->rowCount();
            }
        } catch (PDOException $e) {
            throw new Exception("Erreur d'exécution de la requête : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Insère des données dans une table.
 *
 * @param string $table Nom de la table.
 * @param array $data Tableau associatif des données à insérer (clé => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 *
 * @return int|string Identifiant de la ligne insérée.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Insert(string $table, array $data, mysqli|PDO $conn, string $connType): int|string
{
    if (empty($data)) {
        throw new Exception("Aucune donnée fournie pour l'insertion");
    }
    
    if ($connType === 'mysqli') {
        $columns = implode(", ", array_keys($data));
        $placeholders = str_repeat("?, ", count($data) - 1) . "?";
        $sql = "INSERT INTO " . $conn->real_escape_string($table) . " ($columns) VALUES ($placeholders)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }

        // Déterminer les types de paramètres
        $types = '';
        $values = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
            $values[] = $value;
        }
        
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        
        $insertId = $conn->insert_id;
        $stmt->close();
        return $insertId;

    } elseif ($connType === 'PDO') {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_map(function ($key) {
            return ":$key";
        }, array_keys($data)));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            return $conn->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Erreur d'insertion : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Sélectionne des données dans une table.
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param array|null $where Tableau associatif des conditions WHERE (clé => valeur), ou null pour sélectionner toutes les lignes.
 * @param string|array|null $columns Colonnes à sélectionner (séparées par des virgules ou tableau), null pour toutes.
 * @param string|null $orderBy Clause ORDER BY (ex: "id DESC").
 * @param int|null $limit Nombre maximum de résultats à retourner.
 * @param int|null $offset Position de départ pour les résultats.
 *
 * @return array|false Tableau associatif des résultats, ou false en cas d'échec.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Select(
    string $table, 
    mysqli|PDO $conn, 
    string $connType, 
    ?array $where = null, 
    string|array|null $columns = null, 
    ?string $orderBy = null, 
    ?int $limit = null, 
    ?int $offset = null
): array|false {
    // Définition des colonnes à sélectionner
    if ($columns === null) {
        $columnsStr = "*";
    } elseif (is_string($columns)) {
        $columnsStr = $columns;
    } elseif (is_array($columns)) {
        $columnsStr = implode(", ", $columns);
    } else {
        throw new Exception("Format de colonnes invalide");
    }

    if ($connType === 'mysqli') {
        $sql = "SELECT $columnsStr FROM " . $conn->real_escape_string($table);
        $params = [];
        
        // Conditions WHERE
        if ($where && !empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = ?";
                $params[] = $where[$key];
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        // ORDER BY
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        // LIMIT et OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }

        try {
            return Query($sql, $params, $conn, $connType, true);
        } catch (Exception $e) {
            throw new Exception("Erreur de sélection : " . $e->getMessage());
        }

    } elseif ($connType === 'PDO') {
        $sql = "SELECT $columnsStr FROM $table";
        $params = [];
        
        // Conditions WHERE
        if ($where && !empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
            $params = $where;
        }
        
        // ORDER BY
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        // LIMIT et OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = $offset;
            }
        }

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur de sélection : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Récupère une seule ligne de résultat.
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param array $where Tableau associatif des conditions WHERE (clé => valeur).
 * @param string|array|null $columns Colonnes à sélectionner (séparées par des virgules ou tableau), null pour toutes.
 *
 * @return array|null Tableau associatif du résultat, ou null si aucun résultat.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function SelectOne(
    string $table, 
    mysqli|PDO $conn, 
    string $connType, 
    array $where, 
    string|array|null $columns = null
): ?array {
    // Définition des colonnes à sélectionner
    if ($columns === null) {
        $columnsStr = "*";
    } elseif (is_string($columns)) {
        $columnsStr = $columns;
    } elseif (is_array($columns)) {
        $columnsStr = implode(", ", $columns);
    } else {
        throw new Exception("Format de colonnes invalide");
    }

    if ($connType === 'mysqli') {
        $sql = "SELECT $columnsStr FROM " . $conn->real_escape_string($table);
        $params = [];
        
        // Conditions WHERE
        if (!empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = ?";
                $params[] = $where[$key];
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        $sql .= " LIMIT 1";

        try {
            $result = Query($sql, $params, $conn, $connType, false);
            return $result ?: null;
        } catch (Exception $e) {
            throw new Exception("Erreur de sélection : " . $e->getMessage());
        }

    } elseif ($connType === 'PDO') {
        $sql = "SELECT $columnsStr FROM $table";
        $params = [];
        
        // Conditions WHERE
        if (!empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
            $params = $where;
        }
        
        $sql .= " LIMIT 1";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            throw new Exception("Erreur de sélection : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Met à jour des données dans une table.
 *
 * @param string $table Nom de la table.
 * @param array $data Tableau associatif des données à mettre à jour (clé => valeur).
 * @param array $where Tableau associatif des conditions WHERE (clé => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 *
 * @return int Nombre de lignes affectées.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Update(string $table, array $data, array $where, mysqli|PDO $conn, string $connType): int
{
    if (empty($data)) {
        throw new Exception("Aucune donnée fournie pour la mise à jour");
    }
    
    if (empty($where)) {
        throw new Exception("Aucune condition WHERE fournie pour la mise à jour. Utilisez UpdateAll() pour mettre à jour toutes les lignes.");
    }
    
    if ($connType === 'mysqli') {
        $set = implode(", ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($data)));
        
        $sql = "UPDATE " . $conn->real_escape_string($table) . " SET $set WHERE ";
        
        $whereClause = [];
        foreach (array_keys($where) as $key) {
            $whereClause[] = "$key = ?";
        }
        $sql .= implode(" AND ", $whereClause);

        $params = array_merge(array_values($data), array_values($where));
        
        // Déterminer les types de paramètres
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;

    } elseif ($connType === 'PDO') {
        $set = implode(", ", array_map(function ($key) {
            return "$key = :set_$key";
        }, array_keys($data)));
        
        $whereClause = implode(" AND ", array_map(function ($key) {
            return "$key = :where_$key";
        }, array_keys($where)));
        
        $sql = "UPDATE $table SET $set WHERE $whereClause";

        // Préfixer les clés pour éviter les conflits
        $params = [];
        foreach ($data as $key => $value) {
            $params["set_$key"] = $value;
        }
        foreach ($where as $key => $value) {
            $params["where_$key"] = $value;
        }

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur de mise à jour : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Met à jour toutes les lignes d'une table (sans condition WHERE).
 *
 * @param string $table Nom de la table.
 * @param array $data Tableau associatif des données à mettre à jour (clé => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 *
 * @return int Nombre de lignes affectées.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function UpdateAll(string $table, array $data, mysqli|PDO $conn, string $connType): int
{
    if (empty($data)) {
        throw new Exception("Aucune donnée fournie pour la mise à jour");
    }
    
    if ($connType === 'mysqli') {
        $set = implode(", ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($data)));
        
        $sql = "UPDATE " . $conn->real_escape_string($table) . " SET $set";
        $params = array_values($data);
        
        // Déterminer les types de paramètres
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;

    } elseif ($connType === 'PDO') {
        $set = implode(", ", array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($data)));
        
        $sql = "UPDATE $table SET $set";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur de mise à jour : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Supprime des données d'une table.
 *
 * @param string $table Nom de la table.
 * @param array $where Tableau associatif des conditions WHERE (clé => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 *
 * @return int Nombre de lignes supprimées.
 * @throws Exception Si le type de connexion n'est pas valide, si aucune condition WHERE n'est fournie, ou en cas d'erreur.
 */
function Delete(string $table, array $where, mysqli|PDO $conn, string $connType): int
{
    if (empty($where)) {
        throw new Exception("Aucune condition WHERE fournie pour la suppression. Utilisez DeleteAll() pour supprimer toutes les lignes.");
    }
    
    if ($connType === 'mysqli') {
        $whereClause = implode(" AND ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($where)));
        
        $sql = "DELETE FROM " . $conn->real_escape_string($table) . " WHERE $whereClause";
        $params = array_values($where);
        
        // Déterminer les types de paramètres
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;

    } elseif ($connType === 'PDO') {
        $whereClause = implode(" AND ", array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($where)));
        
        $sql = "DELETE FROM $table WHERE $whereClause";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($where);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur de suppression : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Supprime toutes les lignes d'une table (sans condition WHERE).
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param bool $confirm Confirmation explicite requise pour éviter les suppressions accidentelles.
 *
 * @return int Nombre de lignes supprimées.
 * @throws Exception Si le type de connexion n'est pas valide, si la confirmation n'est pas fournie, ou en cas d'erreur.
 */
function DeleteAll(string $table, mysqli|PDO $conn, string $connType, bool $confirm = false): int
{
    if (!$confirm) {
        throw new Exception("Confirmation requise pour supprimer toutes les lignes. Définissez le paramètre \$confirm à true.");
    }
    
    if ($connType === 'mysqli') {
        $sql = "DELETE FROM " . $conn->real_escape_string($table);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->execute();
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;

    } elseif ($connType === 'PDO') {
        $sql = "DELETE FROM $table";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Erreur de suppression : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Vérifie si un enregistrement existe dans une table.
 *
 * @param string $table Nom de la table.
 * @param array $where Tableau associatif des conditions WHERE (clé => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 *
 * @return bool True si l'enregistrement existe, false sinon.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Exists(string $table, array $where, mysqli|PDO $conn, string $connType): bool
{
    if (empty($where)) {
        throw new Exception("Aucune condition WHERE fournie pour la vérification d'existence.");
    }
    
    if ($connType === 'mysqli') {
        $whereClause = implode(" AND ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($where)));
        
        $sql = "SELECT 1 FROM " . $conn->real_escape_string($table) . " WHERE $whereClause LIMIT 1";
        $params = array_values($where);
        
        // Déterminer les types de paramètres
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;

    } elseif ($connType === 'PDO') {
        $whereClause = implode(" AND ", array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($where)));
        
        $sql = "SELECT 1 FROM $table WHERE $whereClause LIMIT 1";

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($where);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la vérification d'existence : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Compte le nombre d'enregistrements dans une table.
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param array|null $where Tableau associatif des conditions WHERE (clé => valeur), ou null pour compter toutes les lignes.
 *
 * @return int Nombre d'enregistrements.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function CountRecords(string $table, mysqli|PDO $conn, string $connType, ?array $where = null): int
{
    if ($connType === 'mysqli') {
        $sql = "SELECT COUNT(*) as count FROM " . $conn->real_escape_string($table);
        $params = [];
        
        // Conditions WHERE
        if ($where && !empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = ?";
                $params[] = $where[$key];
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        if (!empty($params)) {
            // Déterminer les types de paramètres
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 's'; // Par défaut
                }
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        return (int)$row['count'];
        
    } elseif ($connType === 'PDO') {
        $sql = "SELECT COUNT(*) as count FROM $table";
        $params = [];
        
        // Conditions WHERE
        if ($where && !empty($where)) {
            $whereClause = [];
            foreach (array_keys($where) as $key) {
                $whereClause[] = "$key = :$key";
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);
            $params = $where;
        }
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors du comptage des enregistrements : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}


/**
 * Démarre une transaction.
 *
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return bool True si la transaction a été démarrée avec succès, false sinon.
 * @throws Exception Si le type de connexion n'est pas valide.
 */
function BeginTransaction(mysqli|PDO $conn, string $connType): bool
{
    if ($connType === 'mysqli') {
        return $conn->begin_transaction();
    } elseif ($connType === 'PDO') {
        return $conn->beginTransaction();
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Valide une transaction.
 *
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return bool True si la transaction a été validée avec succès, false sinon.
 * @throws Exception Si le type de connexion n'est pas valide.
 */
function CommitTransaction(mysqli|PDO $conn, string $connType): bool
{
    if ($connType === 'mysqli') {
        return $conn->commit();
    } elseif ($connType === 'PDO') {
        return $conn->commit();
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Annule une transaction.
 *
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return bool True si la transaction a été annulée avec succès, false sinon.
 * @throws Exception Si le type de connexion n'est pas valide.
 */
function RollbackTransaction(mysqli|PDO $conn, string $connType): bool
{
    if ($connType === 'mysqli') {
        return $conn->rollback();
    } elseif ($connType === 'PDO') {
        return $conn->rollBack();
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Obtient le dernier ID inséré.
 *
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param string|null $name Nom de la séquence (uniquement pour PDO).
 * 
 * @return int|string Dernier ID inséré.
 * @throws Exception Si le type de connexion n'est pas valide.
 */
function LastInsertId(mysqli|PDO $conn, string $connType, ?string $name = null): int|string
{
    if ($connType === 'mysqli') {
        return $conn->insert_id;
    } elseif ($connType === 'PDO') {
        return $conn->lastInsertId($name);
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Exécute une requête de pagination.
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param int $page Numéro de la page (commence à 1).
 * @param int $perPage Nombre d'éléments par page.
 * @param array|null $where Tableau associatif des conditions WHERE (clé => valeur), ou null pour sélectionner toutes les lignes.
 * @param string|array|null $columns Colonnes à sélectionner (séparées par des virgules ou tableau), null pour toutes.
 * @param string|null $orderBy Clause ORDER BY (ex: "id DESC").
 * 
 * @return array Tableau contenant les résultats paginés et les informations de pagination.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Paginate(
    string $table, 
    mysqli|PDO $conn, 
    string $connType, 
    int $page = 1, 
    int $perPage = 10, 
    ?array $where = null, 
    string|array|null $columns = null, 
    ?string $orderBy = null
): array {
    // Vérifie que la page est au moins 1
    $page = max(1, $page);
    
    // Calcule l'offset
    $offset = ($page - 1) * $perPage;
    
    // Récupère les données
    $data = Select($table, $conn, $connType, $where, $columns, $orderBy, $perPage, $offset);
    
    // Compte le nombre total d'enregistrements
    $total = CountRecords($table, $conn, $connType, $where);
    
    // Calcule le nombre total de pages
    $totalPages = ceil($total / $perPage);
    
    return [
        'data' => $data,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
            'has_more_pages' => $page < $totalPages,
        ]
    ];
}

/**
 * Sécurise une chaîne pour l'utilisation dans une requête SQL.
 * Note: À utiliser uniquement pour les parties non paramétrables d'une requête.
 * Pour les valeurs, utilisez toujours des requêtes préparées.
 *
 * @param string $string Chaîne à sécuriser.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return string Chaîne sécurisée.
 * @throws Exception Si le type de connexion n'est pas valide.
 */
function EscapeString(string $string, mysqli|PDO $conn, string $connType): string
{
    if ($connType === 'mysqli') {
        return $conn->real_escape_string($string);
    } elseif ($connType === 'PDO') {
        return substr($conn->quote($string), 1, -1);
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Obtient les informations sur les colonnes d'une table.
 *
 * @param string $table Nom de la table.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return array Tableau contenant les informations sur les colonnes.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function GetColumns(string $table, mysqli|PDO $conn, string $connType): array
{
    if ($connType === 'mysqli') {
        $sql = "SHOW COLUMNS FROM " . $conn->real_escape_string($table);
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        
        $stmt->close();
        return $columns;
        
    } elseif ($connType === 'PDO') {
        $sql = "SHOW COLUMNS FROM $table";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des colonnes : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Exécute une requête LIKE.
 *
 * @param string $table Nom de la table.
 * @param array $likeColumns Tableau associatif des colonnes pour la clause LIKE (colonne => valeur).
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * @param string|array|null $columns Colonnes à sélectionner (séparées par des virgules ou tableau), null pour toutes.
 * @param string $operator Opérateur de liaison entre les clauses LIKE ('AND' ou 'OR').
 * @param string|null $orderBy Clause ORDER BY (ex: "id DESC").
 * @param int|null $limit Nombre maximum de résultats à retourner.
 * @param int|null $offset Position de départ pour les résultats.
 * 
 * @return array Tableau des résultats.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function SearchLike(
    string $table, 
    array $likeColumns, 
    mysqli|PDO $conn, 
    string $connType, 
    string|array|null $columns = null, 
    string $operator = 'OR', 
    ?string $orderBy = null, 
    ?int $limit = null, 
    ?int $offset = null
): array {
    // Définition des colonnes à sélectionner
    if ($columns === null) {
        $columnsStr = "*";
    } elseif (is_string($columns)) {
        $columnsStr = $columns;
    } elseif (is_array($columns)) {
        $columnsStr = implode(", ", $columns);
    } else {
        throw new Exception("Format de colonnes invalide");
    }
    
    // Validation de l'opérateur
    $operator = strtoupper($operator);
    if ($operator !== 'AND' && $operator !== 'OR') {
        throw new Exception("Opérateur invalide. Utilisez 'AND' ou 'OR'.");
    }
    
    if ($connType === 'mysqli') {
        $sql = "SELECT $columnsStr FROM " . $conn->real_escape_string($table);
        $params = [];
        
        // Conditions LIKE
        if (!empty($likeColumns)) {
            $likeClause = [];
            foreach ($likeColumns as $column => $value) {
                $likeClause[] = "$column LIKE ?";
                $params[] = '%' . $value . '%';
            }
            $sql .= " WHERE " . implode(" $operator ", $likeClause);
        }
        
        // ORDER BY
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        // LIMIT et OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        return Query($sql, $params, $conn, $connType, true);
        
    } elseif ($connType === 'PDO') {
        $sql = "SELECT $columnsStr FROM $table";
        $params = [];
        
        // Conditions LIKE
        if (!empty($likeColumns)) {
            $likeClause = [];
            foreach ($likeColumns as $column => $value) {
                $paramName = str_replace('.', '_', $column) . '_like';
                $likeClause[] = "$column LIKE :$paramName";
                $params[$paramName] = '%' . $value . '%';
            }
            $sql .= " WHERE " . implode(" $operator ", $likeClause);
        }
        
        // ORDER BY
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        // LIMIT et OFFSET
        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET :offset";
                $params['offset'] = $offset;
            }
        }
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la recherche : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**
 * Insère ou met à jour un enregistrement (UPSERT).
 *
 * @param string $table Nom de la table.
 * @param array $data Tableau associatif des données à insérer ou mettre à jour (clé => valeur).
 * @param array $uniqueKeys Tableau des clés uniques pour identifier l'enregistrement existant.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return int|string ID de l'enregistrement inséré ou mis à jour.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function Upsert(string $table, array $data, array $uniqueKeys, mysqli|PDO $conn, string $connType): int|string
{
    // Vérifie si l'enregistrement existe
    $where = array_intersect_key($data, array_flip($uniqueKeys));
    if (empty($where)) {
        throw new Exception("Les clés uniques spécifiées ne correspondent à aucune clé dans les données.");
    }
    
    if (Exists($table, $where, $conn, $connType)) {
        // Mise à jour
        Update($table, $data, $where, $conn, $connType);
        
        // Récupère l'ID de l'enregistrement
        $result = SelectOne($table, $conn, $connType, $where, 'id');
        return $result ? $result['id'] : 0;
    } else {
        // Insertion
        return Insert($table, $data, $conn, $connType);
    }
}

/**
 * Exécute un INSERT ... ON DUPLICATE KEY UPDATE.
 * Note: Cette fonction est spécifique à MySQL/MariaDB.
 *
 * @param string $table Nom de la table.
 * @param array $data Tableau associatif des données à insérer ou mettre à jour (clé => valeur).
 * @param array|null $updateData Tableau associatif des données à mettre à jour en cas de duplication (clé => valeur), ou null pour utiliser $data.
 * @param mysqli|PDO $conn Instance de connexion à la base de données.
 * @param string $connType Type de connexion ('mysqli'ou 'PDO').
 * 
 * @return int|string ID de l'enregistrement inséré ou nombre de lignes affectées.
 * @throws Exception Si le type de connexion n'est pas valide ou en cas d'erreur.
 */
function InsertOrUpdate(
    string $table, 
    array $data, 
    ?array $updateData = null, 
    mysqli|PDO $conn, 
    string $connType
): int|string {
    if (empty($data)) {
        throw new Exception("Aucune donnée fournie pour l'insertion ou la mise à jour");
    }
    
    // Si $updateData n'est pas fourni, utiliser $data
    if ($updateData === null) {
        $updateData = $data;
    }
    
    $columns = implode(", ", array_keys($data));
    $updateClause = implode(", ", array_map(function ($key) {
        return "$key = VALUES($key)";
    }, array_keys($updateData)));
    
    if ($connType === 'mysqli') {
        $placeholders = str_repeat("?, ", count($data) - 1) . "?";
        $sql = "INSERT INTO " . $conn->real_escape_string($table) . " ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
        
        $params = array_values($data);
        
        // Déterminer les types de paramètres
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 's'; // Par défaut
            }
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête : " . $conn->error);
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        $insertId = $conn->insert_id;
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Si $affected == 1, c'est une insertion, retourne l'ID
        // Si $affected == 2, c'est une mise à jour, retourne le nombre de lignes affectées
        return $insertId > 0 ? $insertId : $affected;
        
    } elseif ($connType === 'PDO') {
        $placeholders = implode(", ", array_map(function ($key) {
            return ":$key";
        }, array_keys($data)));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
        
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($data);
            
            $insertId = $conn->lastInsertId();
            $affected = $stmt->rowCount();
            
            // Logique similaire à mysqli
            return $insertId > 0 ? $insertId : $affected;
        } catch (PDOException $e) {
            throw new Exception("Erreur d'insertion ou de mise à jour : " . $e->getMessage());
        }
    } else {
        throw new Exception("Type de connexion invalide : " . $connType);
    }
}

/**************************************
 * FONCTIONS DE GESTION DES SESSIONS
 **************************************/

/**
 * Initialise une session sécurisée.
 *
 * @param array $options Options de configuration de la session.
 * @return bool True si la session a été initialisée avec succès.
 */
function InitSession(array $options = []): bool
{
    // Options par défaut pour une session sécurisée
    $defaultOptions = [
        'cookie_httponly' => true,        // Empêche l'accès JavaScript aux cookies
        'cookie_secure' => isset($_SERVER['HTTPS']), // Cookies uniquement via HTTPS
        'use_strict_mode' => true,        // Mode strict pour prévenir la fixation de session
        'gc_maxlifetime' => 3600,         // Durée de vie de la session (1 heure)
        'sid_length' => 48,              // Longueur de l'ID de session
        'sid_bits_per_character' => 6     // Bits par caractère (pour l'entropie)
    ];
    
    // Fusionner avec les options utilisateur
    $sessionOptions = array_merge($defaultOptions, $options);
    
    // Configurer les options de session
    foreach ($sessionOptions as $key => $value) {
        ini_set("session.$key", $value);
    }
    
    // Démarrer la session
    return session_start();
}

/**
 * Régénère l'ID de session pour prévenir la fixation de session.
 *
 * @param bool $deleteOldSession Supprimer l'ancienne session après régénération.
 * @return bool True si l'ID a été régénéré avec succès.
 */
function RegenerateSessionId(bool $deleteOldSession = true): bool
{
    return session_regenerate_id($deleteOldSession);
}

/**
 * Définit une valeur dans la session.
 *
 * @param string $key Clé de la valeur.
 * @param mixed $value Valeur à stocker.
 * @return void
 */
function SetSession(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Récupère une valeur de la session.
 *
 * @param string $key Clé de la valeur.
 * @param mixed $default Valeur par défaut si la clé n'existe pas.
 * @return mixed Valeur de la session ou valeur par défaut.
 */
function GetSession(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Vérifie si une clé existe dans la session.
 *
 * @param string $key Clé à vérifier.
 * @return bool True si la clé existe, false sinon.
 */
function HasSession(string $key): bool
{
    return isset($_SESSION[$key]);
}

/**
 * Supprime une valeur de la session.
 *
 * @param string $key Clé à supprimer.
 * @return void
 */
function RemoveSession(string $key): void
{
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

/**
 * Supprime toutes les valeurs de la session.
 *
 * @return void
 */
function ClearSession(): void
{
    $_SESSION = [];
}

/**
 * Détruit complètement la session, y compris le cookie de session.
 *
 * @return bool True si la session a été détruite avec succès.
 */
function DestroySession(): bool
{
    // Vider le tableau de session
    $_SESSION = [];
    
    // Supprimer le cookie de session si nécessaire
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Détruire la session
    return session_destroy();
}

/**
 * Définit un message flash dans la session (affiché une seule fois).
 *
 * @param string $type Type de message ('success', 'error', 'info', 'warning').
 * @param string $message Contenu du message.
 * @return void
 */
function SetFlash(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Récupère et supprime tous les messages flash de la session.
 *
 * @return array Tableau des messages flash.
 */
function GetFlash(): array
{
    $flash = $_SESSION['flash_messages'] ?? [];
    $_SESSION['flash_messages'] = [];
    
    return $flash;
}

/**
 * Vérifie si des messages flash existent dans la session.
 *
 * @return bool True si des messages flash existent, false sinon.
 */
function HasFlash(): bool
{
    return !empty($_SESSION['flash_messages']);
}

/**************************************
 * FONCTIONS DE SÉCURITÉ
 **************************************/

/**
 * Sécurise les données entrantes.
 *
 * @param mixed $data Données à sécuriser.
 * @return mixed Données sécurisées.
 */
function Sanitize(mixed $data): mixed
{
    if (is_array($data)) {
        return array_map('Sanitize', $data);
    }
    
    if (is_string($data)) {
        // Supprimer les espaces en début et fin de chaîne
        $data = trim($data);
        
        // Convertir les caractères spéciaux en entités HTML
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Génère un jeton CSRF (Cross-Site Request Forgery).
 *
 * @param string $formName Nom du formulaire ou de l'action (pour avoir plusieurs jetons).
 * @return string Jeton CSRF.
 */
function GenerateCsrfToken(string $formName = 'default'): string
{
    if (!isset($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$formName] = [
        'token' => $token,
        'timestamp' => time()
    ];
    
    return $token;
}

/**
 * Vérifie la validité d'un jeton CSRF.
 *
 * @param string $token Jeton CSRF à vérifier.
 * @param string $formName Nom du formulaire ou de l'action.
 * @param int $expiration Durée de validité du jeton en secondes (3600 par défaut).
 * @return bool True si le jeton est valide, false sinon.
 */
function ValidateCsrfToken(string $token, string $formName = 'default', int $expiration = 3600): bool
{
    if (!isset($_SESSION['csrf_tokens'][$formName])) {
        return false;
    }
    
    $storedToken = $_SESSION['csrf_tokens'][$formName];
    
    // Vérifier si le jeton a expiré
    if (time() - $storedToken['timestamp'] > $expiration) {
        unset($_SESSION['csrf_tokens'][$formName]);
        return false;
    }
    
    // Vérifier si le jeton correspond
    return hash_equals($storedToken['token'], $token);
}

/**
 * Nettoie les jetons CSRF expirés.
 *
 * @param int $expiration Durée de validité en secondes (3600 par défaut).
 * @return void
 */
function CleanExpiredCsrfTokens(int $expiration = 3600): void
{
    if (!isset($_SESSION['csrf_tokens'])) {
        return;
    }
    
    $currentTime = time();
    foreach ($_SESSION['csrf_tokens'] as $formName => $tokenData) {
        if ($currentTime - $tokenData['timestamp'] > $expiration) {
            unset($_SESSION['csrf_tokens'][$formName]);
        }
    }
}

/**
 * Hache un mot de passe de manière sécurisée.
 *
 * @param string $password Mot de passe à hacher.
 * @param array $options Options pour password_hash.
 * @return string|false Mot de passe haché ou false en cas d'erreur.
 */
function HashPassword(string $password, array $options = []): string|false
{
    $defaultOptions = [
        'cost' => 12 // Coût de l'algorithme (plus c'est élevé, plus c'est sécurisé mais lent)
    ];
    
    $hashOptions = array_merge($defaultOptions, $options);
    
    return password_hash($password, PASSWORD_DEFAULT, $hashOptions);
}

/**
 * Vérifie si un mot de passe correspond à son hash.
 *
 * @param string $password Mot de passe à vérifier.
 * @param string $hash Hash à comparer.
 * @return bool True si le mot de passe correspond, false sinon.
 */
function VerifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Vérifie si un hash de mot de passe doit être recalculé.
 *
 * @param string $hash Hash à vérifier.
 * @param array $options Options pour password_needs_rehash.
 * @return bool True si le hash doit être recalculé, false sinon.
 */
function PasswordNeedsRehash(string $hash, array $options = []): bool
{
    $defaultOptions = [
        'cost' => 12
    ];
    
    $hashOptions = array_merge($defaultOptions, $options);
    
    return password_needs_rehash($hash, PASSWORD_DEFAULT, $hashOptions);
}

/**
 * Génère un UUID (Universally Unique Identifier) v4.
 *
 * @return string UUID v4.
 */
function GenerateUUID(): string
{
    $data = random_bytes(16);
    
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC4122
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Génère un jeton d'accès sécurisé.
 *
 * @param int $length Longueur du jeton (32 par défaut).
 * @return string Jeton d'accès.
 */
function GenerateAccessToken(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}
