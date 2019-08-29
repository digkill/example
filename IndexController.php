<?php

namespace backend\controllers\teacher;

use backend\controllers\BaseController;
use common\models\Product;
use common\models\teacher\DocEvent;
use yii\data\ArrayDataProvider;

class IndexController extends BaseController
{
    public function actionIndex($productId = null)
    {
        return $this->render('index', [
            'upcomingDataProvider' => $this->getUpcomingDataProvider($productId),
            'productModel' => empty($productId) ? null : Product::findOne($productId),
        ]);
    }

    /**
     * @return ArrayDataProvider
     */
    private function getUpcomingDataProvider($productId): ArrayDataProvider
    {
        $events = DocEvent::find()
            ->forThisUser()
            ->upcoming()
            ->filterByProductId($productId)
            ->all();
        return new ArrayDataProvider([
            'allModels' => $events,
            'pagination' => false,
            'sort' => [
                'route' => '/teacher/index/upcoming',
                'attributes' => [
                    'start_date',
                    'title',
                    'ordersRatio',
                    'formsRatio'
                ]
            ]
        ]);
    }

    public function actionUpcoming($productId = null)
    {
        return $this->renderPartial('_upcoming-grid', [
            'upcomingDataProvider' => $this->getUpcomingDataProvider($productId),
        ]);
    }
}