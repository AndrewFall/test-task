<?php

declare(strict_types = 1);

namespace Drupal\server_general\Plugin\Field\FieldFormatter;

use Drupal\og\Plugin\Field\FieldFormatter\GroupSubscribeFormatter;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\og\OgMembershipInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Plugin implementation for the OG subscribe formatter.
 *
 * @FieldFormatter(
 *   id = "custom_og_group_subscribe",
 *   label = @Translation("Custom OG Group subscribe"),
 *   description = @Translation("Display Custom OG Group subscribe and un-subscribe links."),
 *   field_types = {
 *     "og_group"
 *   }
 * )
 */
class CustomGroupSubscribeFormatter extends GroupSubscribeFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Cache by the OG membership state. Anonymous users are handled below.
    $elements['#cache']['contexts'] = [
      'og_membership_state',
      'user.roles:authenticated',
    ];
    $cache_meta = CacheableMetadata::createFromRenderArray($elements);

    $group = $items->getEntity();
    $entity_type_id = $group->getEntityTypeId();
    $cache_meta->merge(CacheableMetadata::createFromObject($group));
    $cache_meta->applyTo($elements);

    $user = $this->entityTypeManager->getStorage('user')->load(($this->currentUser->id()));
    if (($group instanceof EntityOwnerInterface) && ($group->getOwnerId() == $user->id())) {
      // User is the group manager.
      $elements[0] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'title' => $this->t('You are the group manager'),
          'class' => ['group', 'manager'],
        ],
        '#value' => $this->t('You are the group manager'),
      ];

      return $elements;
    }

    $storage = $this->entityTypeManager->getStorage('og_membership');
    $props = [
      'uid' => $user ? $user->id() : 0,
      'entity_type' => $group->getEntityTypeId(),
      'entity_bundle' => $group->bundle(),
      'entity_id' => $group->id(),
    ];
    $memberships = $storage->loadByProperties($props);
    /** @var \Drupal\og\OgMembershipInterface $membership */
    $membership = reset($memberships);

    if ($membership) {
      $cache_meta->merge(CacheableMetadata::createFromObject($membership));
      $cache_meta->applyTo($elements);
      if ($membership->isBlocked()) {
        // If user is blocked, they should not be able to apply for
        // membership.
        return $elements;
      }
      // Member is pending or active.
      $link['title'] = $this->t('Unsubscribe from group');
      $link['url'] = Url::fromRoute('og.unsubscribe', [
        'entity_type_id' => $entity_type_id,
        'group' => $group->id(),
      ]);
      $link['class'] = ['unsubscribe'];
    }
    else {
      // If the user is authenticated, set up the subscribe link.
      if ($user->isAuthenticated()) {
        $parameters = [
          'entity_type_id' => $group->getEntityTypeId(),
          'group' => $group->id(),
          'og_membership_type' => OgMembershipInterface::TYPE_DEFAULT,
        ];

        $url = Url::fromRoute('og.subscribe', $parameters);
      }
      else {
        $cache_meta->setCacheContexts(['user.roles:anonymous']);
        // User is anonymous, link to user login and redirect back to here.
        $url = Url::fromRoute('user.login', [], ['query' => $this->getDestinationArray()]);
      }
      $cache_meta->applyTo($elements);

      /** @var \Drupal\Core\Access\AccessResult $access */
      if (($access = $this->ogAccess->userAccess($group, 'subscribe without approval', $user)) && $access->isAllowed()) {
        $link['title'] = $this->t('Hi @user_name, click here if you would like to subscribe to this group, called @group_title', [
          '@user_name' => $user->label(),
          '@group_title' => $group->label(),
        ]);
        $link['class'] = ['subscribe'];
        $link['url'] = $url;
      }
      elseif (($access = $this->ogAccess->userAccess($group, 'subscribe', $user)) && $access->isAllowed()) {
        $link['title'] = $this->t('Hi @user_name, click here if you would like to request group membership in group @group_title', [
          '@user_name' => $user->label(),
          '@group_title' => $group->label(),
        ]);
        $link['class'] = ['subscribe', 'request'];
        $link['url'] = $url;
      }
      else {
        $elements[0] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'title' => $this->t('This is a closed group. Only a group administrator can add you.'),
            'class' => ['group', 'closed'],
          ],
          '#value' => $this->t('This is a closed group. Only a group administrator can add you.'),
        ];

        return $elements;
      }
    }

    if (!empty($link['title'])) {
      $link += [
        'options' => [
          'attributes' => [
            'title' => $link['title'],
            'class' => ['group'] + $link['class'],
          ],
        ],
      ];

      $elements[0] = [
        '#type' => 'link',
        '#title' => $link['title'],
        '#url' => $link['url'],
      ];
    }

    return $elements;
  }

}
