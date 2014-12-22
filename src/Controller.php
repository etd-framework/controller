<?php
/**
 * Part of the ETD Framework Controller Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Controller;

use EtdSolutions\Application\Web;
use EtdSolutions\Model\Model;
use EtdSolutions\User\User;
use Joomla\Input\Input;
use Joomla\Application\AbstractApplication;
use Joomla\Controller\AbstractController;
use Joomla\Language\Text;

defined('_JEXEC') or die;

/**
 * Controller de base
 */
abstract class Controller extends AbstractController {

    /**
     * @var string Contexte
     */
    protected $context = '';

    /**
     * @var string Tâche par défaut à exécuter.
     */
    protected $defaultTask = 'display';

    /**
     * @var string Layout par défaut pour la vue.
     */
    protected $defaultLayout = 'default';

    /**
     * @var string Vue par défaut à charger.
     */
    protected $defaultView;

    /**
     * @var $defaultModel string Le nom du model par défaut.
     */
    protected $defaultModel;

    /**
     * @var string Nom du controller.
     */
    protected $name;

    /**
     * @var string Tâche à exécuter par le controller.
     */
    protected $task;

    /**
     * @var string Tâche en cours d'exécution.
     */
    protected $doTask;

    /**
     * @var array Tableau contenant un mapping entre les tâches et les fonctions.
     */
    protected $tasks;

    /**
     * @var string  Nom du layout utilisé pour la vue.
     */
    protected $layout;

    /**
     * @var bool Si True, le controller supporte les requêtes SSL.
     */
    protected $ssl_enabled = true;

    /**
     * Instancie le controller.
     *
     * @param   Input               $input The input object.
     * @param   AbstractApplication $app   The application object.
     */
    public function __construct(Input $input = null, AbstractApplication $app = null) {

        if (!isset($app)) {
            $app = Web::getInstance();
        }

        if (!isset($input)) {
            $input = $app->input;
        }

        parent::__construct($input, $app);

        // On charge le fichier de langue pour le controller.
        $lang = $this->getApplication()
                     ->getLanguage();
        $lang->load(strtolower($this->getName()));

        // Le nom de la vue par défaut est pris sur celui du controller.
        $this->defaultView = $this->getName();

        // Tâches.
        $this->task  = '';
        $this->tasks = array();

        // Determine the methods to exclude from the base class.
        $xMethods = get_class_methods('EtdSolutions\\Controller\\Controller');

        // Get the public methods in this class using reflection.
        $r        = new \ReflectionClass($this);
        $rMethods = $r->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($rMethods as $rMethod) {
            $mName = $rMethod->getName();

            // Add default display method if not explicitly declared.
            if (!in_array($mName, $xMethods) || $mName == 'display') {
                $this->methods[] = strtolower($mName);

                // Auto register the methods as tasks.
                $this->taskMap[strtolower($mName)] = $mName;
            }
        }

        $this->registerDefaultTask($this->defaultTask);

        // On détermine le modèle par défaut grâce au nom du controller.
        $this->defaultModel = $this->getName();

        // Contexte
        $this->context = strtolower($this->getName());

    }

    /**
     * Method to execute the controller.
     *
     * @return  string
     *
     * @since   1.0
     * @throws  \LogicException
     * @throws  \RuntimeException
     */
    public function execute() {

        $this->task = $this->getInput()
                           ->get('task', $this->defaultTask);

        $task = strtolower($this->task);
        if (isset($this->taskMap[$task])) {
            $doTask = $this->taskMap[$task];
        } elseif (isset($this->taskMap['__default'])) {
            $doTask = $this->taskMap['__default'];
        } else {
            throw new \RuntimeException("Task not found !");
        }

        // Record the actual task being fired
        $this->doTask = $doTask;

        return $this->$doTask();

    }

    public function display($view = null) {

        $app = $this->getApplication();

        // Si configuré, les vues ne sont disponibles qu'aux utilisateurs authentifiés.
        if ($app->get('lock_display', true)) {
            $user = User::getInstance();
            if ($user->isGuest()) {
                $this->redirect('/login', Text::_('APP_ERROR_MUST_BE_LOGGED'), 'warning');
            }
        }

        return $this->renderView($view);

    }

    protected function renderView($view = null) {

        // On récupère l'appli.
        $app = $this->getApplication();

        // On définit la liste des espaces de noms dans laquelle la vue peut se trouver.
        $namespaces = array(
            $app->get('app_namespace'),
            '\\EtdSolutions'
        );

        $className = "";

        // On cherche la vue dans ces espaces de nom.
        foreach ($namespaces as $namespace) {

            // On crée le nom de la classe.
            if (isset($view)) {
                $className = $namespace . '\\View\\' . ucfirst($view) . 'View';
            } elseif (isset($this->defaultView)) {
                $className = $namespace . '\\View\\' . ucfirst($this->defaultView) . 'View';
            } else {
                throw new \RuntimeException("Unable to find a view", 500);
            }

            // Si on a trouvé la classe, on arrête.
            if (class_exists($className)) {
                break;
            }

        }

        // On vérifie que l'on a bien une classe valide.
        if (!class_exists($className)) {
            throw new \RuntimeException("Unable to find a view", 500);
        }

        // On instancie la vue.
        $view = new $className();

        // On affecte le layout à la vue.
        $view->setLayout($this->getLayout());

        try {
            $result = $view->render();
        } catch (\Exception $e) {
            $this->getApplication()
                 ->raiseError($e->getMessage(), 404, $e);
        }

        // On retourne le rendu.
        return $result;

    }

    /**
     * Register the default task to perform if a mapping is not found.
     *
     * @param   string $method The name of the method in the derived class to perform if a named task is not found.
     *
     * @return  Controller  A JControllerLegacy object to support chaining.
     */
    public function registerDefaultTask($method) {

        $this->registerTask('__default', $method);

        return $this;
    }

    /**
     * Register (map) a task to a method in the class.
     *
     * @param   string $task   The task.
     * @param   string $method The name of the method in the derived class to perform for this task.
     *
     * @return  Controller  A JControllerLegacy object to support chaining.
     */
    public function registerTask($task, $method) {

        if (in_array(strtolower($method), $this->methods)) {
            $this->taskMap[strtolower($task)] = $method;
        }

        return $this;
    }

    /**
     * Unregister (unmap) a task in the class.
     *
     * @param   string $task The task.
     *
     * @return  Controller  This object to support chaining.
     */
    public function unregisterTask($task) {

        unset($this->taskMap[strtolower($task)]);

        return $this;
    }

    /**
     * Redirects the browser or returns false if no redirect is set.
     */
    public function redirect($url, $msg = null, $type = null) {

        $this->getApplication()
             ->redirect($url, $msg, $type);

        return true;
    }

    /**
     * Méthode pour récupérer le nom du controller.
     *
     * @return  string  Le nom du controller.
     *
     * @throws  \RuntimeException
     */
    public function getName() {

        if (empty($this->name)) {
            $r         = null;
            $classname = join('', array_slice(explode('\\', get_class($this)), -1));
            if (!preg_match('/(.*)Controller/i', $classname, $r)) {
                throw new \RuntimeException('Unable to detect controller name', 500);
            }
            $this->name = $r[1];
        }

        return $this->name;
    }

    /**
     * Méthode pour définir le layout.
     *
     * @param string $layout Le layout.
     */
    public function setLayout($layout) {
        $this->layout = $layout;
    }

    /**
     * Méthode qui retourne le layout.
     *
     * @return string Le layout.
     */
    public function getLayout() {

        if (empty($this->layout)) {

            // On définit la valeur par défaut pour le layout si aucun n'est spécifié.
            $this->layout = $this->defaultLayout;

        }

        return $this->layout;
    }

    /**
     * Méthode pour charger un model.
     *
     * @param string $model Le nom du modèle. Facultatif.
     * @param bool   $ignore_request
     *
     * @return Model Le modèle.
     *
     * @throws \RuntimeException
     */
    protected function getModel($model = null, $ignore_request = false) {

        if (!isset($model) && isset($this->defaultModel)) {
            $model = ucfirst($this->defaultModel);
        } elseif (!isset($model)) {
            throw new \RuntimeException("Unable to find a model", 500);
        }

        return Model::getInstance($model, $ignore_request);

    }

    public function isSSLEnabled() {
        return $this->ssl_enabled;
    }

}
