<?php

namespace backend\controllers\teacher;

use common\components\AjaxResponse;
use common\helpers\ArrayHelper;
use common\models\DocEventForm;
use DateTime;
use Yii;
use yii\db\Query;
use common\components\bl\enum\PredefinedPeriods;
use backend\controllers\BaseController;
use common\models\DocEvent;
use common\models\EventProduct;
use common\models\Grade;
use common\models\Guardian;
use common\models\LnkGuardianStudent;
use common\models\LnkTeacherGrade;
use common\models\Product;
use common\models\ProductVariation;
use common\models\Student;
use yii\web\HttpException;

class ReportController extends BaseController
{
    public function actionSales()
    {
        $format = 'Y-m-d H:i:s';

        $sales = (new Query())
            ->select([
                'lp.order_id',
                'lp.price',
                'lp.quantity',
                'lp.total',
                'e.title as event_title',
                'lp.created_at as transaction_date',
                "concat(st.first_name, ' ', st.last_name) as student_name",
                "concat(gu.first_name, ' ', gu.last_name) as guardian_name",
                'pr.title as product_title',
                'pv.name as product_variation_title'
            ])
            ->from('ldg_event_activity_purchase lp')
            ->leftJoin(DocEvent::tableName() . ' e', 'e.id = lp.doc_event_id')
            ->leftJoin(Product::tableName() . ' pr', 'pr.id = lp.product_id')
            ->leftJoin(ProductVariation::tableName() . ' pv', 'pv.id = lp.product_variation_id')
            ->leftJoin(Student::tableName() . ' st', 'st.id = lp.student_id')
            ->leftJoin(Guardian::tableName() . ' gu', 'gu.id = lp.guardian_id')
            ->where('lp.action_type <> :eventTypeDonation', [
                ':eventTypeDonation' => DocEvent::EVENT_TYPE_STOCK_THE_CLASSROOM
            ]);
        $userId = $this->getUserId();
        if (!empty($userId)) {
            $sales->andWhere('coalesce(creator_id, :creator_id) = :creator_id', [
                ':creator_id' => $userId
            ]);
        }

        $startDate = new DateTime('first day of this month');
        $endDate = new DateTime();

        if (Yii::$app->request->isAjax) {
            $post = Yii::$app->request->post();
            $endDate = $post['endDate'] ? new DateTime($post['endDate']) : $endDate;
            $startDate = $post['startDate'] ? new DateTime($post['startDate']) : $startDate;
        }

        $sales->andWhere([
            'and',
            'lp.created_at::date >= :startDate::date',
            'lp.created_at::date <= :endDate::date'
        ], [
            ':startDate' => $startDate->format($format),
            ':endDate' => $endDate->format($format)
        ]);

        if (Yii::$app->request->isAjax) {
            return $this->asJson(AjaxResponse::success([
                'sales' => $sales->all()
            ]));
        }

        $predefinedPeriods = PredefinedPeriods::asArray();
        $predefinedPeriods = ArrayHelper::map($predefinedPeriods, null, function ($period) {
            return [
                'id' => $period,
                'text' => ucfirst($period)
            ];
        });

        return $this->render('sales', [
            'sales' => $sales->all(),
            'predefinedPeriods' => $predefinedPeriods,
            'predefinedPeriod' => PredefinedPeriods::THIS_MONTH,
            'startDate' => $startDate->format($format),
            'endDate' => $endDate->format($format),
        ]);
    }

    public function actionDailySales()
    {
        $format = 'Y-m-d H:i:s';

        $startDate = new DateTime('Monday this week');
        $endDate = new DateTime();
        $selectedUmbrellaAccount = null;
        $selectedLedgerAccount = null;

        if (Yii::$app->request->isAjax) {
            $post = Yii::$app->request->post();
            $endDate = isset($post['endDate']) ? new DateTime($post['endDate']) : $endDate;
            $startDate = isset($post['startDate']) ? new DateTime($post['startDate']) : $startDate;
            $selectedUmbrellaAccount = isset($post['umbrellaAccount']) ? $post['umbrellaAccount'] : null;
            $selectedLedgerAccount = isset($post['ledgerAccount']) ? $post['ledgerAccount'] : null;
        }

        $salesSubquery = (new Query())
            ->select([
                'lp.total',
                'transaction_date' => new \yii\db\Expression('"lp"."created_at"::date'),
                'e.umbrella_account',
                'e.ledger_account_id as ledger_account'
            ])
            ->from('ldg_event_activity_purchase lp')
            ->leftJoin(DocEvent::tableName() . ' e', 'e.id = lp.doc_event_id')
            ->where('lp.action_type <> :eventTypeDonation', [
                ':eventTypeDonation' => DocEvent::EVENT_TYPE_STOCK_THE_CLASSROOM
            ]);

        $userId = $this->getUserId();
        if (!empty($userId)) {
            $salesSubquery->andWhere('coalesce(creator_id, :creator_id) = :creator_id', [
                ':creator_id' => $userId
            ]);
        }

        $salesSubquery = $salesSubquery->andWhere([
            'and',
            'lp.created_at::date >= :startDate::date',
            'lp.created_at::date <= :endDate::date'
        ], [
            ':startDate' => $startDate->format($format),
            ':endDate' => $endDate->format($format)
        ])
            ->andFilterWhere(['e.umbrella_account' => $selectedUmbrellaAccount])
            ->andFilterWhere(['e.ledger_account_id' => $selectedLedgerAccount])
            ->createCommand()
            ->getRawSql();

        $sales = (new Query())
            ->select([
                'ss.transaction_date',
                'ss.umbrella_account',
                'ss.ledger_account',
                'sum(ss.total) as total'
            ])
            ->from("($salesSubquery) ss")
            ->groupBy([
                'ss.transaction_date',
                'ss.umbrella_account',
                'ss.ledger_account'
            ]);

        $allSales = $sales->all();
        $sales = ArrayHelper::map($allSales, 'id', function ($sale) {
            $ledgerAccount = DocEvent::getLedgerAccount($sale['ledger_account']);
            $ledgerAccount = array_shift($ledgerAccount);
            $umbrellaAccount = DocEvent::getUmbrellaAccount($ledgerAccount['parent']);
            $umbrellaAccount = array_shift($umbrellaAccount);
            $sale['umbrella_account'] = $umbrellaAccount['name'] ?? null;
            $sale['ledger_account'] = $ledgerAccount['name'] ?? null;
            return $sale;
        });
        $umbrellaAccounts = ArrayHelper::map($allSales, null, function ($sale) {
            $umbrellaAccount = DocEvent::getUmbrellaAccount($sale['umbrella_account']);
            $umbrellaAccount = array_shift($umbrellaAccount);
            return [
                'id' => $umbrellaAccount['id'],
                'text' => $umbrellaAccount['name'],
            ];
        });


        if (Yii::$app->request->isAjax) {
            return $this->asJson(AjaxResponse::success([
                'sales' => $sales
            ]));
        }

        $predefinedPeriods = PredefinedPeriods::asArray();
        $predefinedPeriods = ArrayHelper::map($predefinedPeriods, null, function ($period) {
            return [
                'id' => $period,
                'text' => ucfirst($period)
            ];
        });

        return $this->render('daily-sales', [
            'sales' => $sales,
            'predefinedPeriods' => $predefinedPeriods,
            'predefinedPeriod' => PredefinedPeriods::THIS_WEEK,
            'startDate' => $startDate->format($format),
            'endDate' => $endDate->format($format),
            'umbrellaAccounts' => $umbrellaAccounts,
        ]);
    }

    public function actionGetLedgerAccounts()
    {
        $parents = Yii::$app->request->post('umbrellaAccount', null);
        if (empty($parents)) {
            return $this->asJson([]);
        }
        $umbrellaAccountId = $parents[0];
        $accounts = DocEventForm::getLedgerAccounts($umbrellaAccountId);
        $out = ArrayHelper::map($accounts, null, function ($account) {
            return [
                'id' => $account['id'],
                'text' => $account['name'],
            ];
        });
        return $this->asJson($out);
    }

    protected function getUserId()
    {
        return $this->actor->user_id;
    }

}