<?php

namespace Payutc\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration,
    Doctrine\DBAL\Schema\Schema;

/**
 * Ajouter des valeurs par défauts à certains champs
 */
class Version201804101400 extends AbstractMigration {
    public function up(Schema $schema) {
        $this->addSql("ALTER TABLE `t_object_obj` CHANGE `obj_alcool` `obj_alcool` INT(1) NOT NULL DEFAULT '0' COMMENT '0 = sans alcool ; 1 = avec alcool';");
    }

    public function down(Schema $schema) {
        $this->addSql("ALTER TABLE `t_object_obj` CHANGE `obj_alcool` `obj_alcool` INT(1) NOT NULL COMMENT '0 = sans alcool ; 1 = avec alcool';");
    }
}
