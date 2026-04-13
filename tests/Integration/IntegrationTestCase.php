<?php

declare(strict_types=1);

namespace Tests\Integration;

use Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base class pour tous les tests d'intégration.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static Database $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        self::$db->beginTransaction();

        $_ENV['CONTACT_OWNER_EMAIL'] = $_ENV['CONTACT_OWNER_EMAIL'] ?? 'contact@test.local';
        $_ENV['DPO_EMAIL']           = $_ENV['DPO_EMAIL']           ?? 'dpo@test.local';
        $_ENV['SECURITY_EMAIL']      = $_ENV['SECURITY_EMAIL']      ?? 'security@test.local';
        $_ENV['MAINTAINER_MAIL']     = $_ENV['MAINTAINER_MAIL']     ?? 'maintainer@test.local';
    }

    protected function tearDown(): void
    {
        self::$db->rollback();
    }

    /**
     * Réinitialise le singleton Database entre les classes de test.
     */
    public static function tearDownAfterClass(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $prop = $reflection->getProperty('instance');
        $prop->setValue(null, null);
    }

    /**
     * Insère un vin minimal avec toutes les colonnes bilingues (_fr/_en) et retourne son ID.
     *
     * Helper partagé entre les tests d'intégration nécessitant un vin en base.
     * Chaque test étant rollbacké, le slug est inséré tel quel — pas de collision possible.
     *
     * @param string $slug      Slug exact du vin (utilisé tel quel en BDD)
     * @param string $color     Couleur : 'red', 'white', 'rosé', 'sweet'
     * @param string $labelName Nom du vin
     * @return int              ID inséré
     */
    protected static function insertTestWine(
        string $slug = 'test-wine',
        string $color = 'red',
        string $labelName = 'Vin Test'
    ): int {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              image_path, slug)
             VALUES (?,?,'bottle',2020,15.00,100,1,
                     5.00,'Test','Merlot 100%',15,
                     'Test FR','Test EN','Test FR','Test EN','Test FR','Test EN',
                     'Test FR','Test EN','Test FR','Test EN','Test FR','Test EN',
                     '','','','',
                     'test.png',?)",
            [$labelName, $color, $slug]
        );
    }
}
