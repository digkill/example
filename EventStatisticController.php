<?php


namespace backend\controllers\teacher;


use backend\controllers\BaseController;

use common\models\DocEvent;
use common\models\teacher\statistics\FieldTripEventStatistic;
use common\models\teacher\statistics\FundraisingEventStatistic;
use common\models\teacher\statistics\NutritionChildEventStatistic;
use common\models\teacher\statistics\NutritionParentEventStatistic;
use common\models\teacher\statistics\StockTheClassroomEventStatistic;
use yii\data\ArrayDataProvider;
use yii\web\NotFoundHttpException;

class EventStatisticController extends BaseController
{

    private $view;
    public $role = 'teacher';

    /**
     * @param $id
     * @param string $view
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView($id, $view = 'view')
    {
        $this->view = $view;


        $event = $this->findEvent($id);
        switch ($event->type) {
            case DocEvent::EVENT_TYPE_SELL_PRODUCT :
            case DocEvent::EVENT_TYPE_FILL_OUT_FORM :
            case DocEvent::EVENT_TYPE_FIELD_TRIP :
            case DocEvent::EVENT_TYPE_NUTRITION_ONE_TIME:
            case DocEvent::EVENT_TYPE_FUNDRAISING_SELL:
                return $this->renderFieldTripStatistic($event);
            case DocEvent::EVENT_TYPE_STOCK_THE_CLASSROOM :
                return $this->renderStockTheClassroomEvent($event);
            case DocEvent::EVENT_TYPE_NUTRITION :
                return $this->renderNutritionEvent($event);
            case DocEvent::EVENT_TYPE_FUNDRAISING:
                return $this->renderFundraisingStatistic($event);
        }
        throw new \Exception('Not assigned view for this event type.');
    }

    protected function renderNutritionEvent(DocEvent $event)
    {
        $isChildEvent = $event->predecessor !== null;
        return $isChildEvent ? $this->renderNutritionChildEvent($event) : $this->renderNutritionParentEvent($event);
    }

    /**
     * @param DocEvent $event
     * @return string
     */
    protected function renderNutritionParentEvent(DocEvent $event)
    {
        $statistics = NutritionParentEventStatistic::findByEvent($event->id);

        return $this->render('nutrition-parent-' . $this->view, [
            'model' => $event,
            'statisticDataProvider' => $this->getEventStudentStatusDataProvider($statistics),
        ]);
    }

    /**
     * @param DocEvent $event
     * @return string
     * @throws NotFoundHttpException
     */
    protected function renderNutritionChildEvent(DocEvent $event)
    {
        $statistics = NutritionChildEventStatistic::findByEvent($event->id);
        return $this->render('nutrition-child-' . $this->view, [
            'model' => $event,
            'statisticDataProvider' => $this->getEventStudentStatusDataProvider($statistics),
            'photoUrl' => $event->parentPhoto->url ?? null,
        ]);
    }

    /**
     * @param DocEvent $event
     * @return string
     */
    protected function renderStockTheClassroomEvent(DocEvent $event)
    {
        $studentStatusStatistics = StockTheClassroomEventStatistic::findByEvent($event->id);
        $donateProgress = StockTheClassroomEventStatistic::getDonateProgress($event->id);
        return $this->render('stock-the-classroom-'.$this->view, [
            'model' => $event,
            'studentStatusDataProvider' => $this->getEventStudentStatusDataProvider($studentStatusStatistics),
            'donateProgress' => $donateProgress,
        ]);
    }

    /**
     * @param DocEvent $event
     * @return string
     */
    protected function renderFieldTripStatistic(DocEvent $event)
    {
        $studentStatusStatistics = FieldTripEventStatistic::findByEvent($event->id);
        return $this->render('field-trip-'.$this->view, [
            'model' => $event,
            'studentStatusDataProvider' => $this->getEventStudentStatusDataProvider($studentStatusStatistics),
        ]);
    }

    /**
     * @param DocEvent $event
     * @return string
     */
    protected function renderFundraisingStatistic(DocEvent $event)
    {
        $studentStatusStatistics = FundraisingEventStatistic::findByEvent($event->id);
        return $this->render('fundraising-'.$this->view, [
            'model' => $event,
            'studentStatusDataProvider' => $this->getEventStudentStatusDataProvider($studentStatusStatistics),
        ]);
    }

    /**
     * @param $studentStatusStatistic
     * @return ArrayDataProvider
     */
    protected function getEventStudentStatusDataProvider($studentStatusStatistic)
    {
        return new ArrayDataProvider([
            'allModels' => $studentStatusStatistic,
        ]);
    }


    /**
     * @param $id
     * @return array|DocEvent|null
     * @throws NotFoundHttpException
     */
    protected function findEvent($id)
    {
        $model = DocEvent::find()->where(['id' => $id])->one();
        if ($model === null) {
            throw new NotFoundHttpException();
        }
        return $model;
    }
}