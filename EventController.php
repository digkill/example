<?php

namespace backend\controllers\teacher;

use common\components\bl\teacher\PublishErrorException;
use common\models\DocEvent;
use common\models\EventVisibilitySection;
use common\models\teacher\event\ReminderForm;
use Yii;
use common\helpers\ArrayHelper;
use yii\base\ErrorException;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use common\components\AjaxResponse;
use common\components\ModelHelper;
use common\components\Model;
use common\components\bl\teacher\EventManager;
use common\models\EventProduct;
use common\models\LnkEventForm;
use common\models\DocEventForm;
use common\models\DocEventSearch;
use common\models\EventPhoto;
use common\models\teacher\event\StudentSearch;
use common\models\teacher\event\GradeSearch;
use common\models\teacher\event\VisibilityGroupSearch;
use common\models\teacher\event\ProductSearch;
use common\models\teacher\event\FormSearch;
use backend\controllers\BaseController;

class EventController extends BaseController
{
    /**
     * @var EventManager
     */
    public $manager;

    /**
     * EventController constructor.
     * @param string $id
     * @param \yii\base\Module $module
     * @param EventManager $manager
     * @param array $config
     */
    public function __construct($id, $module, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->manager = new EventManager();
    }

    /**
     * Event list page
     * @return string
     * @throws \yii\base\InvalidArgumentException
     */
    public function actionIndex()
    {
        $filterModel = new DocEventSearch();
        $dataProvider = $filterModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'filterModel' => $filterModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionPublish($id)
    {
        $docEvent = $this->find($id);
        $manager = $this->manager;
        $manager->setTarget($docEvent);

        try {
            $manager->validatePublish();
        } catch (PublishErrorException $exception) {
            return $this->asJson(AjaxResponse::danger($exception->getMessage(), [
                'switchToTab' => $exception->tab
            ]));
        }

        try {
            $publish = $this->manager->publish($id);
            if ($publish) {
                Yii::$app->session->setFlash('success', 'Event successfully published.');
            } else {
                Yii::$app->session->setFlash('error', 'Unable to publish event. Please try again later.');
            }
            return $this->redirect(['index']);
        } catch (\Exception $e) {
            return $this->asJson(AjaxResponse::error($e->getMessage()));
        }
    }

    /**
     * Create event page
     * @return string|\yii\web\Response
     * @throws \yii\base\InvalidArgumentException
     */
    public function actionCreate()
    {
        $event = new DocEventForm();
        $photo = new EventPhoto();

        $visibilitySection = new EventVisibilitySection();

        $post = Yii::$app->request->post();

        if ($event->load($post)) {


            if (!$event->validate()) {
                $errors = ModelHelper::errorsForForm($event);
                return $this->asJson(AjaxResponse::validationError($errors));
            }

            try {
                $this->manager->create($event, $photo, $this->getCreatorId());
            } catch (\Exception $e) {
                return $this->asJson(AjaxResponse::error('Unable to create event. Please try again later.'));
            }
            Yii::$app->session->setFlash('success', 'Event successfully created.');
            return $this->redirect(['update', 'id' => $event->id, 'active' => 'participants']);
        }

        return $this->render('event-form', [
            'model' => $event,
            'visibilitySection' => $visibilitySection,
            'previousUrl' => Url::previous(),
        ]);
    }

    /**
     * @param $id
     * @return string|\yii\web\Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\base\ErrorException
     * @throws \yii\base\InvalidArgumentException
     * @throws NotFoundHttpException
     * @throws \yii\base\ErrorException
     */
    public function actionUpdate($id, $active = 'info')
    {
        $manager = $this->manager;

        $event = $manager->find($id);
        $event->setScenario(DocEventForm::SCENARIO_UPDATE);
        $photo = new EventPhoto();

        /* @var $visibilitySection EventVisibilitySection */
        $visibilitySection = $manager->findVisibilitySection($event->id);

        $post = Yii::$app->request->post();

        $checkedProducts = $post['Product']['checked'] ?? null;
        $checkedForms = $post['Form']['checked'] ?? null;
        $limitedUpdate = $event->status === DocEvent::STATUS_PUBLISHED ? true : false;

        if ($event->load($post)) {

            $visibilitySection->load($post);
            $visibilitySection->students = $post['Student']['checked'] ?? [];
            $visibilitySection->grades = $post['Grade']['checked'] ?? [];
            $visibilitySection->visibility_groups = $post['VisibilityGroup']['checked'] ?? [];

            $eventForms = Model::loadMultiple(LnkEventForm::class, $post, 'Form');
            $mandatoryForms = $post['Form']['is_mandatory'] ?? [];
            $eventForms = ArrayHelper::map($eventForms, null, function ($eventForm) use ($mandatoryForms) {
                $eventForm->is_mandatory = ArrayHelper::isIn($eventForm->form_id, $mandatoryForms);
                return $eventForm;
            });
            $eventForms = array_filter($eventForms, function ($eventForm) use ($checkedForms) {
                return ArrayHelper::isIn($eventForm->form_id, $checkedForms ?? []);
            });

            $eventProducts = Model::loadMultiple(EventProduct::class, $post, 'Product');
            $eventProducts = array_filter($eventProducts, function ($eventProduct) use ($checkedProducts) {
                return ArrayHelper::isIn($eventProduct->product_id, $checkedProducts ?? []);
            });

            $eventReminder = new ReminderForm();
            $eventReminder->load($post);

            $models = [$event, $visibilitySection, $eventForms, $eventProducts, $eventReminder];


            if (!Model::validateMultiple($models)) {
                $errors = ModelHelper::errorsForMultipleForms([
                    [$event],
                    [$visibilitySection, $visibilitySection->getFormName()],
                    [$eventForms, 'Form', 'form_id'],
                    [$eventProducts, 'Product', 'product_id'],
                    [$eventReminder]
                ]);
                return $this->asJson(AjaxResponse::tabularFormValidation($errors));
            }

            try {
                $manager->update($event, $photo, $visibilitySection, $eventForms, $eventProducts, $eventReminder, $limitedUpdate);
            } catch (\Exception $e) {
                return $this->asJson(AjaxResponse::error('Unable to update event. Please try again later.'));
            }

            Yii::$app->session->setFlash('success', 'Event successfully updated.');
            return $this->asJson(AjaxResponse::success(['message' => 'Event successfully updated.']));
        }

        $previousUrl = Yii::$app->request->referrer;

        if (false !== strpos($previousUrl, 'visibility-group/create')) {
            $visibilitySection->newlyCreatedVisibilityGroup = Yii::$app->session->get('newlyCreatedVisibilityGroupId', null);
        }

        if (false !== strpos($previousUrl, 'form/create')) {
            $newlyCreatedForm = Yii::$app->session->get('newlyCreatedFormId', null);
        }

        if (false !== strpos($previousUrl, 'product/create')) {
            $newlyCreatedProduct = Yii::$app->session->get('newlyCreatedProductId', null);
        }

        $queryParams = Yii::$app->request->get();

        $searchModels = $this->getSearchModels(
            $visibilitySection->students,
            $visibilitySection->grades,
            $event->getLnkEventForms()->indexBy('form_id')->asArray()->all(),
            $event->getLnkEventProducts()->indexBy('product_id')->asArray()->all(),
            $checkedForms,
            $checkedProducts
        );
        $remainderForm = new ReminderForm(['eventId' => $event->id]);

        return $this->render('event-form', array_merge([
            'model' => $event,
            'visibilitySection' => $visibilitySection,
            'previousUrl' => $previousUrl,
            'remainderForm' => $remainderForm,
            'newlyCreatedProduct' => $newlyCreatedProduct ?? null,
            'newlyCreatedForm' => $newlyCreatedForm ?? null,
            'active' => $active
        ], $searchModels,
            $this->getVisibilityGroupDataProvider($queryParams, $visibilitySection->visibility_groups,
                $visibilitySection->newlyCreatedVisibilityGroup ?? null)));
    }

    public function actionSaveReminder()
    {
        $model = new ReminderForm();

        if (!$model->load(Yii::$app->request->post()) && !$model->validate()) {
            $errors = ModelHelper::errorsForForm($model);
            return $this->asJson(AjaxResponse::validationError($errors));
        }
        if ($model->save()) {
            return $this->asJson(AjaxResponse::success(AjaxResponse::successMessage('Reminder successfully updated')));
        }


        return $this->asJson(AjaxResponse::error('Unable to update reminder. Please try again later.'));

    }

    /**
     * @param $id
     * @return \yii\web\Response
     * @throws ServerErrorHttpException
     */
    public function actionDelete($id)
    {
        if (!$this->manager->delete($id)) {
            throw new ServerErrorHttpException('Unable to delete event. Please, try again later.');
        }

        return $this->redirect(['index']);
    }


    /**
     * @param $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionCancel($id)
    {
        $docEvent = $this->find($id);
        $manager = $this->manager;
        $manager->setTarget($docEvent);

        try {
            $cancel = $this->manager->cancel($id);

            if (!$cancel) {
                throw new ErrorException("Not cancelled");
            }

            Yii::$app->session->setFlash('success', 'Event successfully canceled.');
            return $this->redirect(['index']);
            //return $this->asJson(AjaxResponse::success(AjaxResponse::successMessage('Event successfully canceled')));
        } catch (\Exception $e) {
            return $this->asJson(AjaxResponse::error('Unable to publish event. Please try again later.'));
        }
    }


    /**
     * @param $id
     * @return DocEventForm
     * @throws NotFoundHttpException
     */
    public function find($id)
    {
        try {
            $event = $this->manager->find($id);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        return $event;
    }

    public function actionUploadPhoto()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $photoSaver = Yii::$container->get('PhotoSaver');
        if ($photoSaver->save()) {
            return $photoSaver->fileSaver->getFileInfo();
        }

        return $photoSaver->getErrors();
    }

    protected function getSearchModels(
        $visibilitySectionStudents, $visibilitySectionGrades,
        $forms, $products, $checkedForms, $checkedProducts
    )
    {
        $queryParams = Yii::$app->request->queryParams;

        $studentsFilterModel = new StudentSearch(['teacherId' => $this->actor->id]);

        $studentsDataProvider = $studentsFilterModel->search($queryParams, $visibilitySectionStudents);

        $gradesFilterModel = new GradeSearch(['teacherId' => $this->actor->id, 'onlyThisYear' => !Yii::$app->request->isAjax]);
        $gradesDataProvider = $gradesFilterModel->search($queryParams, $visibilitySectionGrades);

        $formsFilterModel = new FormSearch(['actor' => $this->actor]);
        $formsDataProvider = $formsFilterModel->search($queryParams, $forms, $checkedForms);

        $productsFilterModel = new ProductSearch(['actor' => $this->actor]);
        $productsDataProvider = $productsFilterModel->search($queryParams, $products, $checkedProducts);

        return [
            'studentsFilterModel' => $studentsFilterModel,
            'studentsDataProvider' => $studentsDataProvider,

            'gradesFilterModel' => $gradesFilterModel,
            'gradesDataProvider' => $gradesDataProvider,

            'formsFilterModel' => $formsFilterModel,
            'formsDataProvider' => $formsDataProvider,

            'productsFilterModel' => $productsFilterModel,
            'productsDataProvider' => $productsDataProvider,
        ];
    }

    protected function getVisibilityGroupDataProvider($queryParams, $visibilitySectionGroups = [], $newlyCreatedVisibilityGroup = null)
    {
        $visibilityGroupsFilterModel = new VisibilityGroupSearch(['teacherId' => $this->actor->id]);
        if ($newlyCreatedVisibilityGroup) {
            $visibilitySectionGroups[] = $newlyCreatedVisibilityGroup;
        }
        $visibilityGroupsDataProvider = $visibilityGroupsFilterModel->search($queryParams, $visibilitySectionGroups);
        return [
            'visibilityGroupsFilterModel' => $visibilityGroupsFilterModel,
            'visibilityGroupsDataProvider' => $visibilityGroupsDataProvider,
        ];
    }

    public function actionGetAccounts()
    {
        $parents = Yii::$app->request->post('depdrop_parents', null);
        if (empty($parents)) {
            return $this->asJson(['output' => '', 'selected' => '']);
        }
        $umbrellaAccountId = $parents[0];
        $out = DocEventForm::getLedgerAccounts($umbrellaAccountId);
        return $this->asJson(['output' => $out, 'selected' => '']);
    }

    public function actionGroupList()
    {
        return $this->render('visibilitySection/_visibilityGroups',
            $this->getVisibilityGroupDataProvider(Yii::$app->request->get())
        );
    }

    public function getCreatorId()
    {
        return $this->actor->user_id;
    }

}