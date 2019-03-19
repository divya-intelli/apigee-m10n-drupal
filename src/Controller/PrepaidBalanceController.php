<?php

/*
 * Copyright 2018 Google Inc.
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

namespace Drupal\apigee_m10n\Controller;

use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for team balances.
 */
class PrepaidBalanceController extends PrepaidBalanceControllerBase {

  /**
   * Redirect to the user's prepaid balances page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Gets a redirect to the users's balance page.
   */
  public function myRedirect(): RedirectResponse {
    return $this->redirect(
      'apigee_monetization.billing',
      ['user' => $this->currentUser->id()],
      ['absolute' => TRUE]
    );
  }

  /**
   * View prepaid balance and account statements, add money to prepaid balance.
   *
   * @param \Drupal\user\UserInterface $user
   *   The Drupal user.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   A render array or a redirect response.
   *
   * @throws \Exception
   */
  public function prepaidBalancePage(UserInterface $user) {
    // Set the entity for this page call.
    $this->entity = $user;

    return $this->render();
  }

  /**
   * {@inheritdoc}
   */
  protected function canRefreshBalance() {
    $user = $this->entity;

    return $this->currentUser->hasPermission('refresh any prepaid balance') ||
      ($this->currentUser->hasPermission('refresh own prepaid balance') && $this->currentUser->id() === $user->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function canAccessDownloadReport() {
    return $this->currentUser->hasPermission('download prepaid balance reports');
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    return ($list = $this->monetization->getDeveloperPrepaidBalances($this->entity, new \DateTimeImmutable('now'))) ? $list : [];
  }

}