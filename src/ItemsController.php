<?php
/**
 * Part of the ETD Framework Controller Package
 *
 * @copyright   Copyright (C) 2015 - 2016 ETD Solutions. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */


namespace EtdSolutions\Controller;

class ItemsController extends Controller {

    /**
     * @var bool True pour charger la pagination, false sinon.
     */
    protected $pagination = true;

	/**
	 * Méthode pour charger un enregistrement en AJAX.
	 */
	public function ajaxLoad() {

		// On initialise les variables
		$app    = $this->getApplication();
		$text   = $this->getContainer()->get('language')->getText();
		$result = new \stdClass();

		// Bad request par défaut.
		$result->status = 400;
		$result->error  = true;

		// On contrôle le jeton de la requête.
		if (!$app->checkToken()) {
			$result->status  = 403;
			$result->message = $text->translate('APP_ERROR_INVALID_TOKEN');

			return $result;
		}

		$model = $this->getModel();

		// Si on est ici c'est OK.
		$result->status = 200;
		$result->error  = false;
		$result->items  = $model->getItems();
        if ($this->pagination) {
            $result->pagination = $model->getPagination();
        }

        /**
         * @event afterLoad
         */
        $this->afterLoad($result, $model);

		return $result;

	}

	public function ajaxOrdering() {

        // On initialise les variables
        $app    = $this->getApplication();
        $text   = $this->getContainer()->get('language')->getText();
        $result = new \stdClass();

        // Bad request par défaut.
        $result->status = 400;
        $result->error  = true;

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $result->status  = 403;
            $result->message = $text->translate('APP_ERROR_INVALID_TOKEN');

            return $result;
        }

        $model = $this->getModel();
        $input = $this->getInput();

        $pks      = $input->get('pks', [], 'array');
        $ordering = $input->get('ordering', [], 'array');

        if (empty($pks) || empty($ordering) || count($pks) != count($ordering)) {
            $result->message = $text->translate('APP_ERROR_BAD_REQUEST');
            return $result;
        }

        if (!$model->setOrdering($pks, $ordering)) {
            $result->status = 500;
            $error          = $model->getError();
            if ($error instanceof \Exception) {
                $error = $error->getMessage();
            }
            $result->message = $error;

            return $result;
        }

        // Si on est ici c'est OK.
        $result->status = 200;
        $result->error  = false;

        return $result;

    }

    /**
     * @event afterLoad
     *
     * @param $result
     * @param $model
     */
	protected function afterLoad(&$result, &$model) {

    }

}