<?php
/**
 * Part of the Joomla Framework DI Package
 *
 * @copyright  Copyright (C) 2013 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace EtdSolutions\Controller;

/**
 * Définit un trait pour les controllers devant gérer les applications fermées.
 */
trait SecureControllerTrait {

    public function execute() {

        $app          = $this->getApplication();
        $container    = $this->getContainer();
        $user         = $container->get('user')->load();
        $session      = $app->getSession();
        $text         = $container->get('language')->getText();
        $current_uri  = $app->get('uri.current');
        $redirect_url = "/login?redirect_url=" . base64_encode($current_uri);

        // Si la session a expirée.
        if ($session->getState() == 'expired') {
            $redirect_url .= "&expired=1";
            $this->redirect($redirect_url, $text->sprintf('APP_ERROR_EXPIRED_SESSION', $app->get('session_expire')), 'error');
            return true;
        }

        // Si l'utilisateur n'est pas connecté.
        if ($user->isGuest()) {
            $this->redirect($redirect_url, $text->translate('APP_ERROR_MUST_BE_LOGGED'), 'error');
            return true;
        }

        return parent::execute();
    }

}
