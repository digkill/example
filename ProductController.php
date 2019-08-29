<?php

namespace backend\controllers\teacher;

use common\helpers\BackendUrl;
use common\models\ProductVariation;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use backend\controllers\BaseController;
use common\components\AjaxResponse;
use common\models\ProductPhoto;
use common\components\bl\ProductManager;
use common\models\Product;
use common\models\SearchProduct;
use yii\web\ServerErrorHttpException;

class ProductController extends BaseController
{
    public function actionIndex()
    {
        $searchModel = new SearchProduct();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate()
    {
        $productManager = new ProductManager();
        $product = new Product();
        $photo = new ProductPhoto();

        $returnUrl = Yii::$app->request->post('returnUrl', Url::to(['index']));

        $variations = [];
        if ($product->load(Yii::$app->request->post())) {
            $productManager->loadVariations(
                $variations,
                Yii::$app->request->post('ProductVariation', []),
                $product->id,
                $product->is_free,
                $product->tax
            );
            if ($productManager->validate($product, $variations) && $productManager->save($product, $photo, $variations)) {
                Yii::$app->session->setFlash('success', 'Product successfully created.');
                Yii::$app->session->set('newlyCreatedProductId', $product->id);
                return $this->redirect($returnUrl);
            }
        }

        $ids = array_keys($variations);
        $ids[] = 0;
        $variationIndex = min($ids) - 1;

        return $this->render('create', [
            'product' => $product,
            'returnUrl' => $returnUrl,
            'suppliers' => $productManager->getSuppliers(),
            'variations' => $variations,
            'variationIndex' => $variationIndex,
            'uploadPhotoUrl' => $this->getUploadPhotoUrl()
        ]);
    }

    public function actionUpdate($id)
    {
        $productManager = new ProductManager();
        $product = $this->find($id);
        $product->setScenario(Product::SCENARIO_UPDATE);
        $photo = new ProductPhoto();

        $returnUrl = Yii::$app->request->post('returnUrl', Url::to(['index']));

        $variations = $product->variations;
        $variations = ArrayHelper::map($variations, 'id', function($model) use($product) {
            /* @var $model ProductVariation */
            if ($product->is_free) {
                $model->setScenario(ProductVariation::SCENARIO_PRODUCT_IS_FREE);
            }
            return $model;
        });

        if ($product->load(Yii::$app->request->post())) {
            $productManager->loadVariations(
                $variations,
                Yii::$app->request->post('ProductVariation', []),
                $product->id,
                $product->is_free,
                $product->tax
            );
            if ($productManager->validate($product, $variations) && $productManager->save($product, $photo, $variations)) {
                return $this->redirect($returnUrl);
            }
        }

        $ids = array_keys($variations);
        $ids[] = 0;
        $variationIndex = min($ids) - 1;

        return $this->render('update', [
            'product' => $product,
            'returnUrl' => $returnUrl,
            'suppliers' => $productManager->getSuppliers(),
            'variations' => $variations,
            'variationIndex' => $variationIndex,
            'uploadPhotoUrl' => $this->getUploadPhotoUrl()
        ]);
    }

    public function actionAddVariation()
    {
        $newVariation = new ProductVariation();
        $newVariation->load(Yii::$app->request->post(), '');
        return $this->renderPartial('new-variation', [
            'newVariation' => $newVariation,
            'isProductFree' => Yii::$app->request->post('isProductFree', false) === 'true',
        ]);
    }

    public function actionDelete($id)
    {
        $productManager = new ProductManager();
        try {
            if (!$productManager->delete($id)) {
                throw new ServerErrorHttpException('Unable to delete product. Please, try again later.');
            }
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('warning', 'Unable to delete product. Please, try again later.');
        }

        return $this->redirect(['index']);
    }

    /**
     * @param $id
     * @return Product
     * @throws NotFoundHttpException
     */
    public function find($id)
    {
        $productManager = new ProductManager();
        try {
            $product = $productManager->find($id);
        } catch (\Exception $e) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        return $product;
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

    protected function getUploadPhotoUrl()
    {
        return BackendUrl::to(["{$this->actor->role}/product/upload-photo"]);
    }
}