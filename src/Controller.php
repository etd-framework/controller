<?php
/**
 * Part of the ETD Framework Controller Package
 *
 * @copyright   Copyright (C) 2015 ETD Solutions, SARL Etudoo. Tous droits réservés.
 * @license     Apache License 2.0; see LICENSE
 * @author      ETD Solutions http://etd-solutions.com
 */

namespace EtdSolutions\Controller;

use EtdSolutions\Language\LanguageFactory;

use EtdSolutions\View\HtmlView;
use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Input\Input;
use Joomla\Application\AbstractApplication;
use Joomla\Controller\AbstractController;
use Joomla\Model\ModelInterface;

/**
 * Controller de base
 */
class Controller extends AbstractController implements ContainerAwareInterface {

    use ContainerAwareTrait;

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
     * @var \Joomla\Registry\Registry Objet d'état à injecter dans le modèle.
     */
    protected $modelState = null;

    /**
     * Instancie le controller.
     *
     * @param   Input               $input The input object.
     * @param   AbstractApplication $app   The application object.
     */
    public function __construct(Input $input = null, AbstractApplication $app = null) {

        parent::__construct($input, $app);

        // On charge le fichier de langue pour le controller.
        $factory = new LanguageFactory();
        $factory->getLanguage()->load(strtolower($this->getName()));

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
     * On redirige le navigateur.
     *
     * @param $url
     * @param null $msg
     * @param null $type
     * @return bool
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
     * Méthode pour gérer la tâche "display".
     *
     * @param string $view Le nom de la vue à afficher.
     * @return string Le rendu de la vue.
     */
    public function display($view = null) {

        $view = $this->initializeView($view);

        $result = $view->render();

        return $result;

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
     * Méthode pour initialiser l'objet View.
     *
     * @param string $view Un nom facultatif de la vue à initialiser.
     *
     * @return \Joomla\View\ViewInterface L'objet View
     *
     * @throws \RuntimeException
     */
    protected function initializeView($view = null) {

        if (!isset($view)) {
            $view = $this->defaultView;
        }

        // On initialise le modèle.
        $this->initializeModel();

        $class = APP_NAMESPACE . "\\View\\" . ucfirst($view) . "View";

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf("Unable to find %s view", $view), 500);
        }

        // On initialise le renderer.
        $this->initializeRenderer();

        // On instancie la vue.
        $object = $this->getContainer()->buildObject($class);

        // On définit le container.
        if ($object instanceof HtmlView) {
            $object->setContainer($this->getContainer());
        }

        // On définit le layout.
        $object->setLayout($this->getLayout());

        // On ajoute la vue aux recherches du chargeur du renderer.
        $object->getRenderer()->getRenderer()->getLoader()->addPath(JPATH_TEMPLATES . '/views/' . strtolower($view));

        return $object;

    }

    protected function initializeModel($model = null, $ignore_request = false) {

        if (!isset($name)) {
            $name = $this->defaultModel;
        }

        $class = APP_NAMESPACE . "\\Model\\" . ucfirst($name) . "Model";

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf("Unable to find %s model (class: %s)", $name, $class), 500);
        }

        $object = new $class($this->getApplication(), $this->getContainer()->get('db'), $this->modelState, $ignore_request);
        $object->setContainer($this->getContainer());

        $this->getContainer()->set($class, $object)->alias('Joomla\\Model\\ModelInterface', $class);

    }

    /**
     * Méthode pour initialiser le renderer.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function initializeRenderer() {

        $container = $this->getContainer();

        // On ajoute le renderer au container s'il n'existe pas.
        if (!$container->exists('renderer')) {

            $type = $container->get('config')->get('template.renderer');

            // On définit le nom de la classe du fournisseur du service Renderer.
            $class = 'EtdSolutions\\Service\\' . ucfirst($type) . 'RendererProvider';

            // Sanity check
            if (!class_exists($class)) {
                throw new \RuntimeException(sprintf('Renderer provider for renderer type %s not found. (class: %s)', $type, $class));
            }

            // On enregistre notre fournisseur de service.
            $container->registerServiceProvider(new $class($this->getApplication()));

        }
    }

    /**
     * Méthode pour instancier un modèle s'il existe.
     *
     * @param  string $name           Le nom du modèle.
     * @param  bool   $ignore_request True pour ignore la requête dans l'état du modèle.
     * @return ModelInterface
     */
    protected function getModel($name = null, $ignore_request = false) {

        if (!isset($name)) {
            $name = $this->defaultModel;
        }

        $class = APP_NAMESPACE . "\\Model\\" . ucfirst($name) . "Model";

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf("Unable to find %s model (class: %s)", $name, $class), 500);
        }

        return new $class($this->getApplication(), $this->getContainer()->get('db'), $this->modelState, $ignore_request);

    }

}
