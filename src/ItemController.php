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
use EtdSolutions\Model\ItemModel;
use EtdSolutions\Model\Model;
use EtdSolutions\Table\Table;
use EtdSolutions\User\User;
use Joomla\Application\AbstractApplication;
use Joomla\Input\Input;
use Joomla\Language\Text;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die;

/**
 * Controller pour un élément.
 */
class ItemController extends Controller {

    /**
     * @var string La route pour la vue de listing des éléments.
     */
    protected $listRoute = null;

    /**
     * @var string La route pour la vue de visualisation et de modification d'un élément.
     */
    protected $itemRoute = null;

    /**
     * Instancie le controller.
     *
     * @param   Input               $input The input object.
     * @param   AbstractApplication $app   The application object.
     */
    public function __construct(Input $input = null, AbstractApplication $app = null) {

        // On devine la route de l'élément suivant le nom du controller.
        if (empty($this->itemRoute)) {
            $this->itemRoute = strtolower($this->getName());
        }

        // On devine la route de listing comme le pluriel de la route pour un élément.
        if (empty($this->listRoute)) {

            // Pluralisation simple basée sur un snippet de Paul Osman.
            // http://kuwamoto.org/2007/12/17/improved-pluralizing-in-php-actionscript-and-ror/
            //
            // Pour des types plus complexes, il suffit de définir manuellement la variable dans la classe.
            $plural = array(
                '/(x|ch|ss|sh)$/i'      => "$1es",
                '/([^aeiouy]|qu)y$/i'   => "$1ies",
                '/([^aeiouy]|qu)ies$/i' => "$1y",
                '/(bu)s$/i'             => "$1ses",
                '/s$/i'                 => "s",
                '/$/'                   => "s"
            );

            // On trouve le bon match en utlisant les expressions régulières.
            foreach ($plural as $k => $v) {
                if (preg_match($k, $this->itemRoute)) {
                    $this->listRoute = preg_replace($k, $v, $this->itemRoute);
                    break;
                }
            }
        }

        parent::__construct($input, $app);

        // On enregistre les tâches standards.

        // Valeur = 0
        $this->registerTask('unpublish', 'publish');

        // Valeur = 2
        $this->registerTask('archive', 'publish');

        // Valeur = -2
        $this->registerTask('trash', 'publish');

        // Valeur = -3
        $this->registerTask('report', 'publish');

        // Ordre
        $this->registerTask('orderup', 'reorder');
        $this->registerTask('orderdown', 'reorder');
    }

    /**
     * Ajoute un élément.
     *
     * @return mixed
     */
    public function add() {

        // On contrôle les droits.
        if (!$this->allowAdd()) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');
        }

        // On passe en layout de création (form).
        $this->setLayout('form');

        // On affiche la vue.
        return $this->display();

    }

    /**
     * Modifie un élément.
     *
     * @return mixed
     */
    public function edit() {

        // On récupère l'identifiant.
        $id = $this->getInput()
                   ->get('id', null, 'array');

        // Si on a aucun élément, on redirige vers la liste avec une erreur.
        if (!is_array($id) || count($id) < 1) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_NO_ITEM_SELECTED'), 'warning');

            return false;
        }

        // On ne prend que le premier des ids.
        $id = (int)$id[0];

        // On modifie l'input pour mettre l'id.
        $this->getInput()
             ->set('id', $id);

        // On contrôle les droits.
        if (!$this->allowEdit($id)) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');
        }

        // On passe en layout de création (form).
        $this->setLayout('form');

        // On affiche la vue.
        return $this->display();

    }

    /**
     * Supprime un élément.
     *
     * @return bool
     */
    public function delete() {

        // App
        $app = Web::getInstance();

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $app->raiseError($this->getApplication()
                                  ->translate('APP_ERROR_INVALID_TOKEN', 403));
        }

        // On récupère les identifiants
        $id = $this->getInput()
                   ->get('id', null, 'array');

        // Si on a aucun élément, on redirige vers la liste avec une erreur.
        if (!is_array($id) || count($id) < 1) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_NO_ITEM_SELECTED'), 'warning');

            return false;
        }

        // On récupềre le model
        $model = $this->getModel();

        // On s'assure que ce sont bien des integers.
        $id = ArrayHelper::toInteger($id);

        // On effectue la suppression.
        if ($model->delete($id)) {

            // La suppresion s'est faite avec succès.
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->plural('CTRL_' . strtoupper($this->getName()) . '_N_ITEMS_DELETED', count($id)), 'success');

        } else {

            // Une erreur s'est produite.
            $this->redirect("/" . $this->listRoute, $model->getError(), 'error');
        }

        return true;

    }

    /**
     * Méthode pour sauver un enregistrement.
     */
    public function save() {

        // App
        $app = Web::getInstance();

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $app->raiseError($this->getApplication()
                                  ->translate('APP_ERROR_INVALID_TOKEN', 403));
        }

        /**
         * @var ItemModel $model
         */
        $model    = $this->getModel();
        $input    = $this->getInput();
        $data     = $input->get('etdform', array(), 'array');
        $recordId = (int)$data['id'];

        // Contrôle d'accès.
        if (!$this->allowSave($recordId)) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');

            return false;
        }

        $this->beforeSave($model, $data);

        // On filtre les données
        $data = $model->filter($data);

        // On valide les données.
        $valid = $model->validate($data);

        if ($valid === false) {

            // On récupère les messages de validation.
            $errors = $model->getErrors();

            // On affiche jusqu'à 3 messages de validation à l'utilisateur.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }

            // On sauvegarde les données dans la session.
            $app->setUserState($this->context . '.edit.data', $data);

            // on renvoie vers le formulaire.
            $this->redirect("/" . $this->itemRoute . $this->getRedirectToItemAppend($recordId));

            return false;
        }

        // On enregistre.
        if (!$model->save($data)) {

            // On sauvegarde les données VALIDÉES dans la session.
            $app->setUserState($this->context . '.edit.data', $data);

            // on renvoie vers le formulaire.
            $this->redirect("/" . $this->itemRoute . $this->getRedirectToItemAppend($recordId), $this->getApplication()
                                                                                                     ->sprintf('APP_ERROR_CTRL_SAVE_FAILED', $model->getError()), 'error');

            return false;

        }

        // On invoque la méthode afterSave pour permettre aux contrôleurs enfants d'accéder au modèle.
        $this->afterSave($model, $data);

        // On nettoie les informations d'édition de l'enregistrement dans la session.
        $app->setUserState($this->context . '.edit.data', null);

        // On redirige vers la page de listing.
        $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                     ->getText()
                                                     ->translate('CTRL_' . strtoupper($this->getName()) . '_SAVE_SUCCESS'), 'success');

        return true;

    }

    /**
     * Méthode pour annuler une édition.
     */
    public function cancel() {

        // On nettoie les informations d'édition de l'enregistrement dans la session.
        Web::getInstance()
           ->setUserState($this->context . '.edit.data', null);

        // On redirige vers la liste.
        $this->redirect("/" . $this->listRoute);

    }

    /**
     * Méthode pour afficher la page d'information sur un élément.
     *
     * @return string|object
     */
    public function view() {

        // On contrôle les droits.
        if (!$this->allowView()) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('APP_ERROR_UNAUTHORIZED_ACTION'), 'error');
        }

        // On nettoie les informations d'édition de l'enregistrement dans la session.
        Web::getInstance()
           ->setUserState($this->context . '.edit.data', null);

        //On affiche la vue
        return $this->display();

    }

    /**
     * Méthode pour dupliquer un enregistrement.
     *
     * @return bool
     */
    public function duplicate() {

        // App
        $app = $this->getApplication();

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $app->raiseError($this->getApplication()
                                  ->translate('APP_ERROR_INVALID_TOKEN', 403));
        }

        $model = $this->getModel();
        $id    = $this->getInput()
                      ->get('id', array(), 'array');

        // Si on a aucun élément, on redirige vers la liste avec une erreur.
        if (!is_array($id) || count($id) < 1) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_NO_ITEM_SELECTED'), 'warning');

            return false;
        }

        // On s'assure que ce sont bien des integers.
        $id = ArrayHelper::toInteger($id);

        // On duplique.
        if ($model->duplicate($id)) {

            // La suppresion s'est faite avec succès.
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->plural('CTRL_' . strtoupper($this->getName()) . '_N_ITEMS_DUPLICATED', count($id)), 'success');

        } else {

            // Une erreur s'est produite.
            $this->redirect("/" . $this->listRoute, $model->getError(), 'error');
        }

        return true;

    }

    /**
     * Méthode pour changer l'état d'un élément.
     *
     * @return bool
     */
    public function publish() {

        // App
        $app = $this->getApplication();

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $app->raiseError($this->getApplication()
                                  ->translate('APP_ERROR_INVALID_TOKEN', 403));
        }

        $model = $this->getModel();
        $id    = $this->getInput()
                      ->get('id', array(), 'array');

        // Si on a aucun élément, on redirige vers la liste avec une erreur.
        if (!is_array($id) || count($id) < 1) {
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_NO_ITEM_SELECTED'), 'warning');

            return false;
        }

        // On s'assure que ce sont bien des integers.
        $id = ArrayHelper::toInteger($id);

        // On prend la bonne valeur en fonction de la tâche.
        $data  = array(
            'publish'   => 1,
            'unpublish' => 0,
            'archive'   => 2,
            'trash'     => -2,
            'report'    => -3
        );
        $value = ArrayHelper::getValue($data, $this->task, 0, 'int');

        // On effectue l'action.
        if ($model->publish($id, $value)) {

            // La tâche s'est faite avec succès.

            $ntext = 'CTRL_' . strtoupper($this->getName());
            if ($value == 1) {
                $ntext .= '_N_ITEMS_PUBLISHED';
            } elseif ($value == 0) {
                $ntext .= '_N_ITEMS_UNPUBLISHED';
            } elseif ($value == 2) {
                $ntext .= '_N_ITEMS_ARCHIVED';
            } else {
                $ntext .= '_N_ITEMS_TRASHED';
            }

            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->plural($ntext, count($id)), 'success');

        } else {

            // Une erreur s'est produite.
            $this->redirect("/" . $this->listRoute, $model->getError(), 'error');

            return false;
        }

        return true;

    }

    /**
     * Change l'ordre d'un ou plusieurs enregistrements.
     *
     * @return  boolean  True en cas de succès.
     */
    public function reorder() {

        // App
        $app = $this->getApplication();

        // On contrôle le jeton de la requête.
        if (!$app->checkToken()) {
            $app->raiseError($this->getApplication()
                                  ->translate('APP_ERROR_INVALID_TOKEN', 403));
        }

        $ids = $this->getInput()
                    ->get('cid', array(), 'array');
        $inc = ($this->task == 'orderup') ? -1 : 1;

        $model  = $this->getModel();
        $return = $model->reorder($ids, $inc);

        if ($return === false) {
            // Reorder failed.
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->getText()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_REORDER_FAILED'), 'error');

            return false;
        } else {
            // Reorder succeeded.
            $this->redirect("/" . $this->listRoute, $this->getApplication()
                                                         ->translate('CTRL_' . strtoupper($this->getName()) . '_ITEM_REORDERED'), 'success');

            return true;
        }
    }

    /**
     * Méthode pour contrôler si l'utilisateur peut créer un nouvel enregistrement.
     *
     * @return  boolean
     */
    protected function allowAdd() {

        $user = User::getInstance();

        return ($user->authorise('add', $this->context) || $user->authorise("admin", "app"));
    }

    /**
     * Méthode pour contrôler si l'utilisateur peut modifier un enregistrement.
     *
     * @param   array|int $id L'identifiant de l'enregistrement.
     *
     * @return  boolean
     */
    protected function allowEdit($id = null) {

        $user = User::getInstance();

        return ($user->authorise('edit', $this->context) || $user->authorise("admin", "app"));
    }

    /**
     * Méthode pour contrôler si l'utilisateur peut supprimer un enregistrement.
     *
     * @param   array|int $id L'identifiant de l'enregistrement.
     *
     * @return  boolean
     */
    protected function allowDelete($id = null) {

        $user = User::getInstance();

        return ($user->authorise('delete', $this->context) || $user->authorise("admin", "app"));
    }

    /**
     * Méthode pour contrôler si l'utilisateur peut afficher un enregistrement.
     *
     * @param   array|int $id L'identifiant de l'enregistrement.
     *
     * @return  boolean
     */
    protected function allowView($id = null) {

        $user = User::getInstance();

        return ($user->authorise('view', $this->context) || $user->authorise("admin", "app"));
    }

    /**
     * Méthode pour contrôler si l'utilisateur peut enregistrer un enregistrement.
     *
     * @param   array|int $id L'identifiant de ou des enregistrements.
     *
     * @return  boolean
     */
    protected function allowSave($id = null) {

        if ($id) {
            return $this->allowEdit($id);
        } else {
            return $this->allowAdd();
        }
    }

    /**
     * Donne les segments à ajouter à la route pour la redirection vers la vue de l'élément.
     *
     * @param   integer $recordId La clé primaire de l'élément.
     *
     * @return  string  Les segments à ajouter à l'URL.
     */
    protected function getRedirectToItemAppend($recordId = null) {

        $append = "";

        if (is_int($recordId)) {
            if ($recordId == 0) {
                $append = "/add";
            } else if ($recordId > 0) {
                $append = "/edit/" . $recordId;
            }
        }

        return $append;
    }

    /**
     * Méthode qui permet aux controllers enfants d'accéder
     * aux données et au modèle avant la sauvegarde.
     *
     * @param   Model $model Le modèle.
     * @param   array $data  Les données.
     *
     * @return  void
     */
    protected function beforeSave(Model &$model, &$data) {

    }

    /**
     * Méthode qui permet aux controllers enfants d'accéder
     * aux données et au modèle après la sauvegarde.
     *
     * @param   Model $model Le modèle.
     * @param   array $data  Les données.
     *
     * @return  void
     */
    protected function afterSave(Model &$model, $data = array()) {

    }

    /**
     * Méthode pour récupérer le table associé au controller.
     *
     * @param string $name Le nom du table.
     *
     * @return Table
     */
    protected function getTable($name = null) {

        if (!isset($name)) {
            $name = $this->getName();
        }

        return Table::getInstance($name);
    }

}