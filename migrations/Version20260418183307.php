<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418183307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_badge (user_id INT NOT NULL, badge_id INT NOT NULL, INDEX IDX_1C32B345A76ED395 (user_id), INDEX IDX_1C32B345F7A2C2FC (badge_id), PRIMARY KEY (user_id, badge_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_badge ADD CONSTRAINT FK_1C32B345F7A2C2FC FOREIGN KEY (badge_id) REFERENCES badge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE badge CHANGE rarete rarete ENUM(\'COMMUN\',\'RARE\',\'EPIQUE\',\'LEGENDAIRE\') DEFAULT \'COMMUN\'');
        $this->addSql('ALTER TABLE cours CHANGE niveau_difficulte niveau_difficulte ENUM(\'DEBUTANT\',\'INTERMEDIAIRE\',\'AVANCE\',\'EXPERT\') NOT NULL DEFAULT \'DEBUTANT\', CHANGE image_url image_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_participants DROP FOREIGN KEY `fk_ep_user`');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY `fk_event_creator`');
        $this->addSql('ALTER TABLE group_members DROP FOREIGN KEY `fk_gm_user`');
        $this->addSql('ALTER TABLE groups DROP FOREIGN KEY `fk_groups_admin`');
        $this->addSql('ALTER TABLE investment_offer DROP FOREIGN KEY `fk_offer_investor`');
        $this->addSql('ALTER TABLE investment_offer DROP FOREIGN KEY `fk_offer_opportunity`');
        $this->addSql('ALTER TABLE investment_offer CHANGE status status ENUM(\'PENDING\',\'ACCEPTED\',\'REJECTED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE investment_opportunity DROP FOREIGN KEY `fk_opportunity_project`');
        $this->addSql('ALTER TABLE investment_opportunity CHANGE status status ENUM(\'OPEN\',\'CLOSED\',\'FUNDED\') DEFAULT \'OPEN\'');
        $this->addSql('ALTER TABLE post_reactions CHANGE reaction_type reaction_type ENUM(\'LIKE\',\'LOVE\',\'HAHA\',\'WOW\',\'SAD\',\'ANGRY\') NOT NULL DEFAULT \'LIKE\'');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY `fk_post_user`');
        $this->addSql('ALTER TABLE progression CHANGE etat etat ENUM(\'NON_COMMENCE\',\'EN_COURS\',\'COMPLETE\',\'CERTIFIE\') DEFAULT \'NON_COMMENCE\'');
        $this->addSql('ALTER TABLE threads DROP FOREIGN KEY `fk_thread_group`');
        $this->addSql('ALTER TABLE threads DROP FOREIGN KEY `fk_thread_user`');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ADMIN\',\'ENTREPRENEUR\',\'MENTOR\',\'INVESTISSEUR\') NOT NULL DEFAULT \'ENTREPRENEUR\'');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345A76ED395');
        $this->addSql('ALTER TABLE user_badge DROP FOREIGN KEY FK_1C32B345F7A2C2FC');
        $this->addSql('DROP TABLE user_badge');
        $this->addSql('ALTER TABLE badge CHANGE rarete rarete ENUM(\'COMMUN\', \'RARE\', \'EPIQUE\', \'LEGENDAIRE\') DEFAULT \'COMMUN\'');
        $this->addSql('ALTER TABLE cours CHANGE niveau_difficulte niveau_difficulte ENUM(\'DEBUTANT\', \'INTERMEDIAIRE\', \'AVANCE\', \'EXPERT\') DEFAULT \'DEBUTANT\' NOT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT `fk_event_creator` FOREIGN KEY (created_by) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_participants ADD CONSTRAINT `fk_ep_user` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT `fk_groups_admin` FOREIGN KEY (group_admin_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_members ADD CONSTRAINT `fk_gm_user` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_offer CHANGE status status ENUM(\'PENDING\', \'ACCEPTED\', \'REJECTED\') DEFAULT \'PENDING\'');
        $this->addSql('ALTER TABLE investment_offer ADD CONSTRAINT `fk_offer_investor` FOREIGN KEY (investor_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_offer ADD CONSTRAINT `fk_offer_opportunity` FOREIGN KEY (opportunity_id) REFERENCES investment_opportunity (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE investment_opportunity CHANGE status status ENUM(\'OPEN\', \'CLOSED\', \'FUNDED\') DEFAULT \'OPEN\'');
        $this->addSql('ALTER TABLE investment_opportunity ADD CONSTRAINT `fk_opportunity_project` FOREIGN KEY (project_id) REFERENCES projet (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('ALTER TABLE posts ADD CONSTRAINT `fk_post_user` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reactions CHANGE reaction_type reaction_type ENUM(\'LIKE\', \'LOVE\', \'HAHA\', \'WOW\', \'SAD\', \'ANGRY\') DEFAULT \'LIKE\' NOT NULL');
        $this->addSql('ALTER TABLE progression CHANGE etat etat ENUM(\'NON_COMMENCE\', \'EN_COURS\', \'COMPLETE\', \'CERTIFIE\') DEFAULT \'NON_COMMENCE\'');
        $this->addSql('ALTER TABLE threads ADD CONSTRAINT `fk_thread_group` FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE threads ADD CONSTRAINT `fk_thread_user` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user CHANGE role role ENUM(\'ADMIN\', \'ENTREPRENEUR\', \'MENTOR\', \'INVESTISSEUR\') DEFAULT \'ENTREPRENEUR\' NOT NULL');
    }
}
