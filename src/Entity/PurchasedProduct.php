<?php

/*
 * Copyright 2021 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_m10n\Entity;

use Apigee\Edge\Api\ApigeeX\Entity\AcceptedRatePlan;
use Apigee\Edge\Api\ApigeeX\Entity\DeveloperAcceptedRatePlan;
use Apigee\Edge\Api\Monetization\Entity\DeveloperInterface;
use Apigee\Edge\Entity\EntityInterface as EdgeEntityInterface;
use Drupal\apigee_edge\Entity\FieldableEdgeEntityBase;
use Drupal\apigee_m10n\Entity\Property\EndTimePropertyAwareDecoratorTrait;
use Drupal\apigee_m10n\Entity\Property\StartTimePropertyAwareDecoratorTrait;
use Drupal\apigee_m10n\Entity\Property\NamePropertyAwareDecoratorTrait;
use Drupal\apigee_m10n\Entity\Property\ApiProductPropertyAwareDecoratorTrait;
use Apigee\Edge\Entity\Property\NamePropertyAwareTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Defines the `purchased_product` entity class.
 *
 * @\Drupal\apigee_edge\Annotation\EdgeEntityType(
 *   id             = "purchased_product",
 *   label          = @Translation("Purchased Product"),
 *   label_singular = @Translation("Purchased Product"),
 *   label_plural   = @Translation("Purchased Products"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Purchased Product",
 *     plural   = "@count Purchased Products",
 *   ),
 *   handlers = {
 *     "storage"             = "Drupal\apigee_m10n\Entity\Storage\PurchasedProductStorage",
 *     "access"              = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "Drupal\apigee_m10n\Entity\Permissions\PurchasedProductPermissionProvider",
 *     "list_builder"        = "Drupal\apigee_m10n\Entity\ListBuilder\PurchasedProductListBuilder",
 *     "form" = {
 *       "default" = "Drupal\apigee_m10n\Entity\Form\PurchasedPlanForm",
 *       "cancel"  = "Drupal\apigee_m10n\Entity\Form\CancelPurchasedProductConfirmForm",
 *     },
 *   },
 *   links = {
 *     "developer_product_collection" = "/user/{user}/monetization/purchased-product",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   admin_permission       = "administer apigee monetization",
 *   field_ui_base_route    = "apigee_m10n.settings.purchased_product",
 * )
 */
class PurchasedProduct extends FieldableEdgeEntityBase implements PurchasedProductInterface, EntityOwnerInterface {

  use EndTimePropertyAwareDecoratorTrait;
  use StartTimePropertyAwareDecoratorTrait;
  use ApiProductPropertyAwareDecoratorTrait;
  use NamePropertyAwareDecoratorTrait;

  public const ENTITY_TYPE_ID = 'purchased_product';

  /**
   * Suppress errors if rate plan that overlaps another accepted rate plan.
   *
   * @var bool
   */
  protected $suppressWarning;

  /**
   * The owner's user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $owner;

  /**
   * Constructs a `purchased_product` entity.
   *
   * @param array $values
   *   An array of values to set, keyed by property name.
   * @param null|string $entity_type
   *   Type of the entity. It is optional because constructor sets its default
   *   value.
   * @param \Apigee\Edge\Entity\EntityInterface|null $decorated
   *   The SDK entity that this Drupal entity decorates.
   *
   * @throws \ReflectionException
   */
  public function __construct(array $values, ?string $entity_type = NULL, ?EdgeEntityInterface $decorated = NULL) {
    /** @var \Apigee\Edge\Api\Management\Entity\DeveloperAppInterface $decorated */
    $entity_type = $entity_type ?? static::ENTITY_TYPE_ID;
    parent::__construct($values, $entity_type, $decorated);

    // Do not suppress warnings by default.
    $this->suppressWarning = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected static function decoratedClass(): string {
    return DeveloperAcceptedRatePlan::class;
  }

  /**
   * {@inheritdoc}
   */
  public static function idProperty(): string {
    return AcceptedRatePlan::idProperty();
  }

  /**
   * {@inheritdoc}
   */
  protected static function propertyToBaseFieldTypeMap(): array {
    return [
      'startTime'            => 'apigee_datestamp',
      'endTime'              => 'apigee_datestamp',
      'createdAt'            => 'apigee_datestamp',
      'lastModifiedAt'       => 'apigee_datestamp',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $definitions */
    $definitions = parent::baseFieldDefinitions($entity_type);

    // The following fields should not be configurable through the UI.
    $read_only_fields = [
      'id',
      'developer',
      'name',
      'apiProduct',
    ];
    // Disable the form display entry for all read only fields.
    foreach ($read_only_fields as $field_name) {
      $definitions[$field_name]->setDisplayConfigurable('form', FALSE);
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function loadByDeveloperId(string $developer_id): array {
    return \Drupal::entityTypeManager()
      ->getStorage(static::ENTITY_TYPE_ID)
      ->loadByDeveloperId($developer_id);
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    // The string formatter assumes entities are revisionable.
    $rel = ($rel === 'revision') ? 'canonical' : $rel;
    // Use the developer collection as the default collection.
    $rel = ($rel === 'collection') ? 'developer_product_collection' : $rel;
    // Build the URL.
    $url = parent::toUrl($rel, $options);
    // Add the user parameter to any routes that require it.
    if ($rel == 'developer_product_collection') {
      // Strip the `purchased_product` parameter from the collection.
      $url->setRouteParameters(array_diff_key($url->getRouteParameters(), ['purchased_product' => NULL]));
      // Set the developer's user ID.
      $url->setRouteParameter('user', $this->getOwnerId());
    }

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalEntityId(): ?string {
    return $this->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): ?string {
    return $this->decorated->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function isActive(): bool {
    return ($this->getStatus() === PurchasedProductInterface::STATUS_ACTIVE
      || $this->getStatus() === PurchasedProductInterface::STATUS_FUTURE);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): string {
    $today = new \DateTime('today', new \DateTimeZone('UTC'));

    // If subscription ended before today, the status is ended.
    $subscription_end_time = $this->getEndTime();
    if (!empty($subscription_end_time) && $subscription_end_time < $today) {
      return PurchasedProductInterface::STATUS_ENDED;
    }

    return PurchasedProductInterface::STATUS_ACTIVE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastModifiedAt(): ?\DateTimeImmutable {
    return $this->decorated->getLastModifiedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedAt(): ?\DateTimeImmutable {
    return $this->decorated->getCreatedAt();
  }

  /**
   * {@inheritdoc}
   */
  public function getDeveloper(): ?DeveloperInterface {
    // TODO: Return the `apigee_edge` developer entity reference.
    return $this->decorated->getDeveloper();
  }

  /**
   * {@inheritdoc}
   */
  public function setSuppressWarning(bool $value): PurchasedProductInterface {
    $this->suppressWarning = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuppressWarning(): bool {
    return $this->suppressWarning;
  }

  /**
   * Gets the monetization service.
   *
   * This gets the monetization service as needed avoiding loading the SDK
   * connector during bootstrap.
   *
   * @return \Drupal\apigee_m10n\MonetizationInterface
   *   The monetization service.
   */
  protected function monetization() {
    return \Drupal::service('apigee_m10n.monetization');
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    if (!isset($this->owner)) {
      $owner = $this->entityTypeManager()->getStorage('user')->loadByProperties([
        'mail' => $this->getDeveloper()->getEmail(),
      ]);
      $this->owner = !empty($owner) ? reset($owner) : NULL;
    }
    return $this->owner;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return ($owner = $this->getOwner()) ? $owner->id() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    // The owner is not settable after instantiation.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    // The owner is not settable after instantiation.
    return $this;
  }

}
