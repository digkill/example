<?php

namespace backend\controllers\teacher;

use backend\controllers\BaseController;
use common\helpers\BackendUrl;
use common\models\Form;
use common\models\FormSearch;
use Yii;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;

class FormController extends BaseController
{
    public function actionIndex()
    {
        $filterModel = new FormSearch();
        $dataProvider = $filterModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
            'filterModel' => $filterModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        $returnUrl = Yii::$app->request->post('returnUrl', Url::to(['index']));

        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Form successfully updated');
                return $this->redirect($returnUrl);
            } else {
                Yii::$app->session->setFlash('error', 'Form not saved');
            }
        }

        return $this->render('update', [
            'model' => $model,
            'returnUrl' => $returnUrl,
        ]);
    }

    public function actionCreate()
    {
        $model = new Form();

        $returnUrl = Yii::$app->request->post('returnUrl', Url::to(['index']));

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Form successfully created.');
            Yii::$app->session->set('newlyCreatedFormId', $model->id);
            return $this->redirect($returnUrl);
        }

        return $this->render('create', [
            'model' => $model,
            'returnUrl' => $returnUrl,
        ]);
    }

    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->delete() !== false) {
            Yii::$app->session->setFlash('success', 'Form successfully deleted');
            return $this->redirect(['index']);
        }
        Yii::$app->session->setFlash('error', 'Form is not deleted');
    }

    /**
     * @param $id
     * @return array|null|\yii\db\ActiveRecord
     * @throws NotFoundHttpException
     */
    protected function findModel($id)
    {
        $model = Form::find()->where(['id' => $id])->one();
        if ($model === null) {
            throw new NotFoundHttpException();
        };
        return $model;
    }
}