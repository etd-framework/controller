<?php
/**
 * Part of the ETD Framework Controller Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Controller;

defined('_JEXEC') or die;

class ErrorController extends Controller {

    /**
     * @var bool Si True, le controller supporte les requêtes SSL.
     */
    protected $ssl_enabled = false;

    public function display($view = null) {

        return $this->renderView($view);

    }

}