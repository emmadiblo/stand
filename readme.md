# Explication sur l'utilisation de `stand.php`

Le fichier `stand.php` est une bibliothèque PHP complète pour faciliter la gestion des bases de données et des sessions. Voici une documentation détaillée des fonctionnalités disponibles dans ce fichier.

## Table des matières

1. [Gestion de bases de données](#gestion-de-bases-de-données)
   - [Connexion à la base de données](#connexion-à-la-base-de-données)
   - [Requêtes SQL](#requêtes-sql)
   - [Opérations CRUD](#opérations-crud)
   - [Transactions](#transactions)
   - [Pagination](#pagination)
   - [Fonctions avancées](#fonctions-avancées)

2. [Gestion de sessions](#gestion-de-sessions)
   - [Initialisation et destruction](#initialisation-et-destruction)
   - [Manipulation des données de session](#manipulation-des-données-de-session)
   - [Messages flash](#messages-flash)

3. [Fonctions de sécurité](#fonctions-de-sécurité)
   - [Sanitization](#sanitization)
   - [Protection CSRF](#protection-csrf)
   - [Gestion des mots de passe](#gestion-des-mots-de-passe)
   - [Génération de jetons](#génération-de-jetons)

## Gestion de bases de données

### Connexion à la base de données avec  `ConnDB()`

La fonction `ConnDB()` permet d'établir une connexion à la base de données en supportant à la fois `MySQLi` et `PDO`.

#### syntaxe
```php
$variable = ConnDB('host', 'username', 'password', 'database', 'connType');
```
#### Options
- `host`: Adresse de l'hôte de la base de données
- `username`: Nom d'utilisateur
- `password`: Mot de passe
- `database`: Nom de la base de données
- `connType`: Type de connexion ('mysqli' ou 'PDO', par défaut 'PDO')


#### exemple
```php
$conn = ConnDB('localhost', 'root', '', 'contacts', 'mysqli');
```






### Executer les requetes SQL avec la fonction `Query()` 

La fonction `Query()` exécute une requête SQL personnalisée avec des paramètres.

`syntaxe`
```php
$variable = Query("requete", [parametres], $conn, 'connType');
```

#### Options
- `requete`: Requete SQL
- `parametres`: Parametres d'une requete
- `$conn`: Variable de connexion à la base de données
- `connType`: Type de connexion ('mysqli' ou 'PDO', par défaut 'PDO')

`exemple`
```php
$results = Query("SELECT * FROM users WHERE age > ?", [18], $conn, 'PDO');
```



### Opérations CRUD

#### Insertion

`exemple`
```php
$userId = Insert('users', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => HashPassword('secure_password')
], $conn, 'PDO');
```

#### Sélection

`exemple`
```php
// Sélectionner plusieurs lignes
$users = Select('users', $conn, 'PDO', ['status' => 'active'], 'id, username, email', 'username ASC', 10, 0);

// Sélectionner une seule ligne
$user = SelectOne('users', $conn, 'PDO', ['id' => 123]);
```

#### Mise à jour

`exemple`
```php
// Mettre à jour avec condition
$affected = Update('users', 
    ['status' => 'inactive'], 
    ['id' => 123], 
    $conn, 
    'PDO'
);

// Mettre à jour toutes les lignes
$affected = UpdateAll('users', ['status' => 'pending'], $conn, 'PDO');
```

#### Suppression

`exemple`
```php
// Supprimer avec condition
$affected = Delete('users', ['id' => 123], $conn, 'PDO');

// Supprimer toutes les lignes (avec confirmation obligatoire)
$affected = DeleteAll('users', $conn, 'PDO', true);
```

### Transactions

`exemple`
```php
BeginTransaction($conn, 'PDO');

try {
    // Opérations sur la base de données...
    
    CommitTransaction($conn, 'PDO');
} catch (Exception $e) {
    RollbackTransaction($conn, 'PDO');
    // Gestion de l'erreur...
}
```

### Pagination

`exemple`
```php
$result = Paginate('articles', $conn, 'PDO', 2, 10, ['status' => 'published']);

// $result contient 'data' (les articles) et 'pagination' (informations sur la pagination)
$articles = $result['data'];
$pagination = $result['pagination'];
```

### Fonctions avancées

#### Vérifier l'existence d'un enregistrement

`exemple`
```php
if (Exists('users', ['email' => 'john@example.com'], $conn, 'PDO')) {
    // L'utilisateur existe déjà
}
```

#### Comptage

`exemple`
```php
$count = Count('users', $conn, 'PDO', ['status' => 'active']);
```

#### Recherche avec LIKE

`exemple`
```php
$results = SearchLike('users', 
    ['username' => 'john', 'email' => 'example'], 
    $conn, 
    'PDO', 
    'id, username, email', 
    'OR'
);
```

#### Upsert (Insert ou Update)

`exemple`
```php
$id = Upsert('users', 
    ['email' => 'john@example.com', 'username' => 'john_doe', 'last_login' => date('Y-m-d H:i:s')], 
    ['email'], 
    $conn, 
    'PDO'
);
```

#### Insert ou Update (MySQL)

`exemple`
```php
$result = InsertOrUpdate('users', 
    ['email' => 'john@example.com', 'username' => 'john_doe', 'status' => 'active'], 
    null, 
    $conn, 
    'PDO'
);
```

## Gestion de sessions

### Initialisation et destruction

`exemple`
```php
// Initialiser une session sécurisée
InitSession([
    'cookie_lifetime' => 86400, // 24 heures
    'cookie_secure' => true
]);

// Régénérer l'ID de session
RegenerateSessionId();

// Détruire la session
DestroySession();
```

### Manipulation des données de session

`exemple`
```php
// Définir une valeur
SetSession('user_id', 123);

// Récupérer une valeur
$userId = GetSession('user_id');

// Vérifier si une clé existe
if (HasSession('user_id')) {
    // La clé existe
}

// Supprimer une valeur
RemoveSession('user_id');

// Vider la session
ClearSession();
```

### Messages flash

`exemple`
```php
// Définir un message flash
SetFlash('success', 'Votre profil a été mis à jour avec succès.');

// Vérifier s'il y a des messages flash
if (HasFlash()) {
    // Il y a des messages flash
}

// Récupérer et supprimer les messages flash
$flashMessages = GetFlash();
foreach ($flashMessages as $flash) {
    echo '<div class="alert alert-' . $flash['type'] . '">' . $flash['message'] . '</div>';
}
```

## Fonctions de sécurité

### Sanitization

`exemple`
```php
$cleanData = Sanitize($_POST);
```

### Protection CSRF

`exemple`
```php
// Dans le formulaire
$token = GenerateCsrfToken('user_form');
echo '<input type="hidden" name="csrf_token" value="' . $token . '">';

// Lors de la soumission du formulaire
if (ValidateCsrfToken($_POST['csrf_token'], 'user_form')) {
    // Token valide, traiter le formulaire
} else {
    // Token invalide
}

// Nettoyer les jetons expirés
CleanExpiredCsrfTokens();
```

### Gestion des mots de passe

`exemple`
```php
// Hacher un mot de passe
$hashedPassword = HashPassword('secure_password');

// Vérifier un mot de passe
if (VerifyPassword('password_to_check', $hashedPassword)) {
    // Mot de passe correct
}

// Vérifier si un rehachage est nécessaire
if (PasswordNeedsRehash($hashedPassword)) {
    $newHash = HashPassword('secure_password');
    // Mettre à jour le hash dans la base de données
}
```

### Génération de jetons

`exemple`
```php
// Générer un UUID
$uuid = GenerateUUID();

// Générer un jeton d'accès
$accessToken = GenerateAccessToken(64);
```

Cette bibliothèque offre un ensemble complet de fonctions pour simplifier le développement d'applications PHP en fournissant des abstractions pour les opérations courantes de base de données et de gestion des sessions, tout en intégrant les bonnes pratiques de sécurité.
