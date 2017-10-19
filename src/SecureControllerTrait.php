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
		$task         = $app->input->get('task');

        // Si la session a expirée.
        if ($session->getState() == 'expired' && !$session->get('from_cookie', false)) {
        	if (strpos($task, "ajax") === 0) {
        	    $this->raiseAjaxError($text->sprintf('APP_ERROR_EXPIRED_SESSION', $app->get('session_expire')));
	        }
	        $redirect_url .= "&expired=1";
	        return $this->redirect($redirect_url, $text->sprintf('APP_ERROR_EXPIRED_SESSION', $app->get('session_expire')), 'error');
        }

        // Si l'utilisateur n'est pas connecté.
        if ($user->isGuest()) {
	        if (strpos($task, "ajax") === 0) {
		        $this->raiseAjaxError($text->translate('APP_ERROR_MUST_BE_LOGGED'));
	        }
            $redirect_url .= "&isGuest=1";
            return $this->redirect($redirect_url, $text->translate('APP_ERROR_MUST_BE_LOGGED'), 'error');
        }

        // Si l'utilisateur n'a les droits d'accès.
        if (!$this->allowExecute()) {
	        if (strpos($task, "ajax") === 0) {
		        $this->raiseAjaxError($text->translate('APP_ERROR_UNAUTHORIZED_ACTION'));
	        }
            return $this->redirect("/", $text->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');
        }

        return parent::execute();
    }

    protected function raiseAjaxError($msg) {
	    echo json_encode([
		    'error'   => true,
		    'message' => $msg
	    ]);
	    die;
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
