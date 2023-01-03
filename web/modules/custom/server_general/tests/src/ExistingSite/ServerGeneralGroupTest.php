<?php

namespace Drupal\Tests\server_general\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test Group request membership.
 */
class ServerGeneralGroupTest extends ExistingSiteBase {

  /**
   * Testing Group node and subscribe request link.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGroup() {
    // Creates a user. Will be automatically cleaned up at the end of the test.
    $author = $this->createUser();
    $guest = $this->createUser();
    $guest->label();

    // Create a "Group" node. Will be automatically cleaned up at end of
    // test.
    $group_node = $this->createNode([
      'title' => 'Group',
      'type' => 'group',
      'og_group' => true,
      'uid' => $author->id(),
    ]);
    $this->assertEquals($author->id(), $group_node->getOwnerId());

    // We can login and browse Group page.
    $this->drupalLogin($guest);
    $this->drupalGet($group_node->toUrl());
    $this->assertSession()->statusCodeEquals(200);

    // Looking for subscribe request link text.
    $this->assertSession()->pageTextContains("Hi {$guest->label()}, click here if you would like to request group membership in group {$group_node->label()}");
  }

}
