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
        if ($session->getState() == 'expired' && !$session->get('from_cookie', false)) {
            $redirect_url .= "&expired=1";
            return $this->redirect($redirect_url, $text->sprintf('APP_ERROR_EXPIRED_SESSION', $app->get('session_expire')), 'error');
        }

        // Si l'utilisateur n'est pas connecté.
        if ($user->isGuest()) {
            return $this->redirect($redirect_url, $text->translate('APP_ERROR_MUST_BE_LOGGED'), 'error');
        }

        // Si l'utilisateur n'a les droits d'accès.
        if (!$this->allowExecute()) {
            return $this->redirect("/", $text->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');
        }

        return parent::execute();
    }

    /**
     * Méthode pour contrôler que l'utilisateur a le droit d'exécuter les tâches du controlleur.
     *
     * @return bool
     */
    protected function allowExecute() {

        return true;

    }

}
