# Tests Fonctionnels Symfony — Vape Store

## Vue d'ensemble

Ce projet contient une suite de tests fonctionnels complète pour le backend Symfony. Les tests couvrent les principaux contrôleurs et les entités métier du projet Vape Store.

## Installation des dépendances de test

```bash
cd vape-store-backend

# Installer les dépendances de test
composer install --dev
```

Les dépendances ajoutées incluent :
- `symfony/test-pack` - Suite de test Symfony
- `phpunit/phpunit` - Framework de test
- `symfony/browser-kit` - Client HTTP pour les tests
- `symfony/dom-crawler` - Parseur DOM
- `doctrine/doctrine-fixtures-bundle` - Fixtures pour les données de test

## Configuration de l'environnement de test

Un fichier `.env.test` a été créé pour configurer l'environnement de test :

```env
DATABASE_URL="mysql://app:app@127.0.0.1:3307/vape_test?serverVersion=10.11&charset=utf8mb4"
APP_ENV=test
APP_SECRET=test_secret_key
```

**Note:** Assurez-vous que la base de données de test `vape_test` est créée ou que les migrations peuvent s'exécuter automatiquement.

## Lancer les tests

### Exécuter tous les tests

```bash
./bin/phpunit
```

### Exécuter les tests d'une classe spécifique

```bash
./bin/phpunit tests/Functional/Controller/ProductControllerTest.php
```

### Exécuter les tests avec verbosité

```bash
./bin/phpunit --verbose
```

### Exécuter les tests avec couverture de code

```bash
./bin/phpunit --coverage-html var/coverage
```

## Structure des tests

Les tests sont organisés dans le dossier `tests/` :

```
tests/
├── Functional/
│   ├── WebTestCase.php                    # Classe de base pour les tests
│   └── Controller/
│       ├── ProductControllerTest.php      # Tests du ProductController
│       ├── AuthControllerTest.php         # Tests de l'authentification
│       ├── BrandControllerTest.php        # Tests du BrandController
│       └── OrderControllerTest.php        # Tests des commandes
└── phpunit.xml.dist                       # Configuration PHPUnit
```

## Tests inclus

### ProductControllerTest

- `testListProducts()` - Lister tous les produits
- `testSearchProducts()` - Rechercher des produits par mot-clé
- `testShowProduct()` - Afficher un produit spécifique
- `testCreateProductUnauthorized()` - Vérifier la protection des endpoints
- `testShowProductNotFound()` - Tester les réponses 404
- `testFilterProductsByType()` - Filtrer les produits par type
- `testInvalidBrandIdFilter()` - Tester la validation des paramètres

### AuthControllerTest

- `testSignupSuccess()` - Inscription réussie
- `testSignupMissingFields()` - Vérifier les champs obligatoires
- `testSignupInvalidEmail()` - Valider le format email
- `testSignupPasswordTooShort()` - Valider la longueur du mot de passe
- `testSignupDuplicateEmail()` - Empêcher les emails en doublon
- `testLoginSuccess()` - Connexion réussie
- `testLoginWrongPassword()` - Rejeter les mauvais mots de passe
- `testLoginUserNotFound()` - Tester les utilisateurs inexistants
- `testLoginMissingFields()` - Vérifier les champs obligatoires

### BrandControllerTest

- `testListBrands()` - Lister toutes les marques
- `testShowBrand()` - Afficher une marque spécifique
- `testShowBrandNotFound()` - Tester les réponses 404

### OrderControllerTest

- `testListOrdersRequiresAuth()` - Vérifier l'authentification requise
- `testCreateOrder()` - Créer une commande avec des lignes
- `testGetOrderDetails()` - Récupérer les détails d'une commande

## Classe de base WebTestCase

La classe `tests/Functional/WebTestCase.php` fournit des méthodes utilitaires pour tous les tests :

### Méthodes HTTP

```php
// GET request
$this->get('/api/products');

// POST request with JSON payload
$this->post('/api/products', ['name' => 'Test Product']);

// PUT request
$this->put('/api/products/1', ['name' => 'Updated']);

// PATCH request
$this->patch('/api/products/1', ['quantity' => 50]);

// DELETE request
$this->delete('/api/products/1');
```

### Assertions

```php
// Vérifier les codes de réponse
$this->assertResponseOk();           // 200
$this->assertResponseCreated();      // 201
$this->assertResponseBadRequest();   // 400
$this->assertResponseUnauthorized(); // 401
$this->assertResponseForbidden();    // 403
$this->assertResponseNotFound();     // 404
$this->assertResponseConflict();     // 409

// Vérifier les réponses JSON
$this->assertJsonHasKey('name');
$this->assertJsonHasKeys(['name', 'email', 'price']);

// Récupérer la réponse JSON
$response = $this->getJsonResponse();
```

### Gestion de la base de données

```php
// Accéder à l'EntityManager
$entityManager = $this->getEntityManager();

// Persister une entité
$entityManager->persist($product);

// Flusher les changements
$this->flushDatabase();
```

## Bonnes pratiques

1. **Transactions de test** : Chaque test est enrobé dans une transaction qui est annulée après le test pour garantir l'isolation.

2. **Données de test** : Créez les données nécessaires dans `setUp()` ou au début du test pour éviter les dépendances entre tests.

3. **Assertions claires** : Utilisez des assertions explicites pour rendre les tests faciles à comprendre.

4. **Noms de test significatifs** : Les noms de test doivent décrire ce qu'ils testent.

5. **Tests de cas d'erreur** : N'oubliez pas de tester les cas d'erreur et les réponses négatives.

## Exemple de test complet

```php
public function testProductWorkflow(): void
{
    // Setup
    $brand = new Brand();
    $brand->setName('Test Brand');
    $this->entityManager->persist($brand);
    $this->entityManager->flush();

    // Create product
    $product = new Product();
    $product->setName('Test Product');
    $product->setPrice(1999);
    $product->setBrand($brand);
    $this->entityManager->persist($product);
    $this->entityManager->flush();

    // Test - List products
    $this->get('/api/products');
    $this->assertResponseOk();
    $response = $this->getJsonResponse();
    $this->assertGreaterThanOrEqual(1, count($response));

    // Test - Get product details
    $this->get('/api/products/' . $product->getId());
    $this->assertResponseOk();
    $details = $this->getJsonResponse();
    $this->assertEquals('Test Product', $details['name']);
}
```

## Intégration continue

Pour intégrer les tests à votre CI/CD (GitHub Actions, GitLab CI, etc.), ajoutez cette commande à votre pipeline :

```bash
./bin/phpunit --testdox
```

L'option `--testdox` génère un rapport lisible des tests exécutés.

## Dépannage

### "Database does not exist"

Assurez-vous que la base de données `vape_test` existe ou créez-la :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
```

### "SQLSTATE[HY000]: General error: 1030 Got error..."

Cela peut indiquer que vous n'avez pas assez d'espace disque. Assurez-vous d'avoir assez d'espace ou utilisez une base de données en mémoire (SQLite) pour les tests.

### Tests échouant de manière aléatoire

Cela peut indiquer une dépendance entre les tests. Assurez-vous que chaque test crée ses propres données de test.

## Ressources

- [Symfony Testing Documentation](https://symfony.com/doc/current/testing.html)
- [PHPUnit Documentation](https://phpunit.de/)
