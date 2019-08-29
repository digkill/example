<?php

namespace common\models;

use common\components\bl\teacher\DailyEventManager;
use common\components\bl\teacher\MonthlyEventManager;
use common\components\bl\teacher\WeeklyEventManager;
use common\exception\LogicException;
use common\components\Formatter;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "doc_event".
 *
 * Class DocEvent
 * @package common\models
 *
 * @property integer $id
 * @property int $predecessor
 * @property integer $creator_id
 * @property string $type
 * @property string $title
 * @property string $description
 * @property string $featured_image
 * @property boolean $is_required
 * @property boolean $is_parent_action_required
 * @property integer $umbrella_account
 * @property integer $ledger_account_id
 * @property string $start_date
 * @property string $end_date
 * @property string $due_date
 * @property string $due_lead_period
 * @property string $due_unit
 * @property integer $duration
 * @property integer $duration_unit
 * @property boolean $is_repeatable
 * @property int $repeat_on_week
 * @property int $repeat_on_days
 * @property integer $frequency
 * @property integer $status
 * @property boolean $remind_for_pending
 * @property boolean $remind_for_upcoming
 * @property integer $pending_reminder_interval
 * @property integer $pending_reminder_interval_unit
 * @property integer $upcoming_reminder_interval
 * @property integer $upcoming_reminder_interval_unit
 * @property integer $text_for_pending_reminder
 * @property integer $text_for_upcoming_reminder
 *
 * @property string $fundraising_message
 * @property integer $fundraising_min
 * @property integer $fundraising_max
 * @property integer $fundraising_step
 *
 * @property int $created_at
 * @property int $updated_at
 *
 * @property EventProduct[] $lnkEventProducts
 * @property Product[] $products
 * @property Form[] $forms
 * @property EventPhoto $mainPhoto
 * @property EventPhoto $parentPhoto
 */
class DocEvent extends ActiveRecord
{
    const DURATION_UNIT_MINUTE = 0x1;
    const DURATION_UNIT_HOUR = 0x2;
    const DURATION_UNIT_DAY = 0x4;
    const DURATION_UNIT_WEEK = 0x8;
    const DURATION_UNIT_MONTH = 0x10;

    const EVENT_TYPE_FIELD_TRIP = 0x01;
    const EVENT_TYPE_NUTRITION = 0x02;
    const EVENT_TYPE_STOCK_THE_CLASSROOM = 0x04;
    const EVENT_TYPE_MEETING = 0x08;
    const EVENT_TYPE_DONATIONS = 0x10;
    const EVENT_TYPE_SELL_PRODUCT = 0x20;
    const EVENT_TYPE_FILL_OUT_FORM = 0x40;
    const EVENT_TYPE_NUTRITION_ONE_TIME = 0x80;
    const EVENT_TYPE_FUNDRAISING = 0x100;
    const EVENT_TYPE_FUNDRAISING_SELL = 0x200;
    const EVENT_TYPE_TUITION_ONE_TIME = 0x400;
    const EVENT_TYPE_TUITION = 0x800;

    const STATUS_NEW = 0x1;
    const STATUS_SAVED = 0x2;
    const STATUS_PUBLISHED = 0x4;
    const STATUS_CANCELED = 0x8;

    const FREQUENCY_DAILY = 0x1;
    const FREQUENCY_WEEKLY = 0x2;
    const FREQUENCY_MONTHLY = 0x4;

    const MONDAY = 0x01;
    const TUESDAY = 0x02;
    const WEDNESDAY = 0x04;
    const THURSDAY = 0x08;
    const FRIDAY = 0x10;
    const SATURDAY = 0x20;
    const SUNDAY = 0x40;

    const WEEK_FIRST = 0x01;
    const WEEK_SECOND = 0x02;
    const WEEK_THIRD = 0x04;
    const WEEK_FOURTH = 0x08;
    const WEEK_FIFTH = 0x10;
    const WEEK_SIXTH = 0x20;
    const WEEK_LAST = 0x40;

    private static $daysOfWeek = [
        self::MONDAY => 'Mon',
        self::TUESDAY => 'Tue',
        self::WEDNESDAY => 'Wed',
        self::THURSDAY => 'Thu',
        self::FRIDAY => 'Fri',
        self::SATURDAY => 'Sat',
        self::SUNDAY => 'Sun',
    ];

    private static $weeks = [
        self::WEEK_FIRST => '1',
        self::WEEK_SECOND => '2',
        self::WEEK_THIRD => '3',
        self::WEEK_FOURTH => '4',
        self::WEEK_FIFTH => '5',
        self::WEEK_SIXTH => '6',
        self::WEEK_LAST => 'Last week',
    ];

    private static $frequencies = [
        self::FREQUENCY_DAILY => 'Daily',
        self::FREQUENCY_WEEKLY => 'Weekly',
        self::FREQUENCY_MONTHLY => 'Monthly',
    ];

    private static $eventTypes = [
        self::EVENT_TYPE_FIELD_TRIP => 'Field trip',
        self::EVENT_TYPE_NUTRITION => 'Nutrition (recurring)',
        self::EVENT_TYPE_NUTRITION_ONE_TIME => 'Nutrition (one time)',
        self::EVENT_TYPE_STOCK_THE_CLASSROOM => 'Stock the classroom',
//            self::EVENT_TYPE_MEETING => 'Meeting',
//            self::EVENT_TYPE_DONATIONS => 'Donations',
        self::EVENT_TYPE_SELL_PRODUCT => 'Sell product',
        self::EVENT_TYPE_FILL_OUT_FORM => 'Fill out form',
        self::EVENT_TYPE_FUNDRAISING => 'Donate Fundraising',
        self::EVENT_TYPE_FUNDRAISING_SELL => 'Sell fundraising',
    //    self::EVENT_TYPE_TUITION_ONE_TIME => 'Tuition (one time)',
    //    self::EVENT_TYPE_TUITION => 'Tuition (recurring)',
    ];

    private static $oneFullDayEventTypes = [
        self::EVENT_TYPE_STOCK_THE_CLASSROOM,
        self::EVENT_TYPE_SELL_PRODUCT,
        self::EVENT_TYPE_FILL_OUT_FORM,
    ];

    private static $recurringEventTypes = [
        self::EVENT_TYPE_NUTRITION,
        self::EVENT_TYPE_TUITION,
    ];

    private static $umbrellaAccounts = [
        ['id' => '1', 'name' => 'Admin/Other'],
        ['id' => '2', 'name' => 'Athletics'],
//        ['id' => '3', 'name' => 'Board Funds'],
        ['id' => '4', 'name' => 'Charities External'],
        ['id' => '5', 'name' => 'Clubs'],
        ['id' => '6', 'name' => 'Departments'],
        ['id' => '7', 'name' => 'Fund Raising'],
//        ['id' => '8', 'name' => 'Investments'],
        ['id' => '9', 'name' => 'Student Activities'],
//        ['id' => '10', 'name' => 'YrEnd Transaction'],
        ['id' => '11', 'name' => 'Nutrition']
    ];

    protected static $accounts = [
        ['id' => '1', 'name' => 'Anti-Tobacco', 'parent' => '3'],
        ['id' => '2', 'name' => 'Article 8.02', 'parent' => '1'],
        ['id' => '3', 'name' => 'Athletics - Intermediate', 'parent' => '2'],
        ['id' => '4', 'name' => 'Athletics - Junior', 'parent' => '2'],
        ['id' => '5', 'name' => 'Athletics - Primary', 'parent' => '2'],
        ['id' => '6', 'name' => 'Bank Charges', 'parent' => '1'],
        ['id' => '7', 'name' => 'Building Improvement', 'parent' => '1'],
        ['id' => '8', 'name' => 'Cash Float', 'parent' => '1'],
        ['id' => '9', 'name' => 'Charitable Donations', 'parent' => '4'],
        ['id' => '10', 'name' => 'Chocolate', 'parent' => '7'],
        ['id' => '11', 'name' => 'Co-Instructional', 'parent' => '5'],
        ['id' => '12', 'name' => 'Computers & Technology', 'parent' => '1'],
        ['id' => '13', 'name' => 'Cross Country', 'parent' => '2'],
        ['id' => '14', 'name' => 'Curriculum Support', 'parent' => '9'],
        ['id' => '15', 'name' => 'CYO Sports', 'parent' => '2'],
        ['id' => '16', 'name' => 'Donations', 'parent' => '7'],
        ['id' => '17', 'name' => 'ECO', 'parent' => '9'],
        ['id' => '18', 'name' => 'Equal Opportunities', 'parent' => '3'],
        ['id' => '19', 'name' => 'Interest', 'parent' => '1'],
        ['id' => '20', 'name' => 'Investment', 'parent' => '8'],
        ['id' => '21', 'name' => 'Learning Material', 'parent' => '9'],
        ['id' => '22', 'name' => 'Library', 'parent' => '6'],
        ['id' => '23', 'name' => 'Little Angels', 'parent' => '4'],
        ['id' => '23', 'name' => 'Lunchroom Supervision', 'parent' => '3'],
        ['id' => '25', 'name' => 'Major Fundraiser - CSC', 'parent' => '7'],
        ['id' => '26', 'name' => 'Major Fundraiser - School', 'parent' => '7'],
        ['id' => '27', 'name' => 'Milk', 'parent' => '7'],
        ['id' => '28', 'name' => 'Minor Fundraiser - CSC', 'parent' => '7'],
        ['id' => '29', 'name' => 'Minor Fundraiser - School', 'parent' => '7'],
        ['id' => '30', 'name' => 'Nevada', 'parent' => '3'],
        ['id' => '31', 'name' => 'NSF Fees', 'parent' => '1'],
        ['id' => '32', 'name' => 'Nutrition Program', 'parent' => '9'],
        ['id' => '33', 'name' => 'Office Supplies', 'parent' => '1'],
        ['id' => '34', 'name' => 'Other Athletics', 'parent' => '2'],
        ['id' => '35', 'name' => 'Other Food', 'parent' => '7'],
        ['id' => '36', 'name' => 'Over/Under', 'parent' => '1'],
        ['id' => '37', 'name' => 'Parent Involvement Grant', 'parent' => '3'],
        ['id' => '38', 'name' => 'YrEnd Outstanding', 'parent' => '10'],
        //nutrition umbrella account
        ['id' => '38', 'name' => 'Pizza', 'parent' => '11'],
        ['id' => '38', 'name' => 'Sub/Pita', 'parent' => '11'],
        ['id' => '38', 'name' => 'Hamburger/Hotdog', 'parent' => '11'],
        ['id' => '38', 'name' => 'Milk', 'parent' => '11'],
        ['id' => '38', 'name' => 'Baked good', 'parent' => '11'],
        ['id' => '38', 'name' => 'Other treat', 'parent' => '11'],
    ];


    protected $statusNames = [
        self::STATUS_NEW => 'New',
        self::STATUS_SAVED => 'Saved',
        self::STATUS_PUBLISHED => 'Published',
        self::STATUS_CANCELED => 'Cancelled',
    ];

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => function () {
                    return date('Y-m-d H:i:s');
                },
            ],
        ];
    }

    /**
     * @return string
     */
    public static function tableName()
    {
        return 'doc_event';
    }

    public function beforeSave($insert)
    {
        if ($this->isNewRecord) {
            $this->status = empty($this->predecessor) ? self::STATUS_SAVED : self::STATUS_PUBLISHED;
        }

        return parent::beforeSave($insert);
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [[
                'creator_id', 'ledger_account_id', 'duration',
                'duration_unit', 'frequency', 'repeat_on_days',
                'pending_reminder_interval', 'pending_reminder_interval_unit',
                'upcoming_reminder_interval', 'upcoming_reminder_interval_unit'
            ], 'integer'],
            [[
                'title', 'description', 'featured_image',
                'start_date', 'end_date', 'due_date',
                'text_for_pending_reminder', 'text_for_upcoming_reminder'], 'string'],
            ['type', 'in', 'range' => array_keys(self::getEventTypes())],
            [['is_required', 'is_repeatable', 'is_parent_action_required', 'remind_for_pending', 'remind_for_upcoming'], 'boolean']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'due_lead_period' => 'Due Date Lead Time',
            'due_unit' => 'Due Date Time Unit',
        ];
    }

    public function getLabelStatus($status)
    {
        if (isset($this->statusNames[$status])) {
            return $this->statusNames[$status];
        }
        return null;
    }

    public static function getFrequencies()
    {
        return self::$frequencies;
    }

    /**
     * Return list of duration unit names
     *
     * @return array
     */
    public static function getDurationUnits()
    {
        return [
            self::DURATION_UNIT_MINUTE => 'Minutes',
            self::DURATION_UNIT_HOUR => 'Hours',
            self::DURATION_UNIT_DAY => 'Days',
        ];
    }

    public static function getDueUnits()
    {
        return [
            self::DURATION_UNIT_MINUTE => 'Minutes',
            self::DURATION_UNIT_HOUR => 'Hours',
            self::DURATION_UNIT_DAY => 'Days',
            self::DURATION_UNIT_WEEK => 'Week',
            self::DURATION_UNIT_MONTH => 'Month',
        ];
    }

    public static function getRecurringEventTypes()
    {
        return self::$recurringEventTypes;
    }

    public function isRecurring()
    {
        return in_array($this->type, self::$recurringEventTypes);
    }

    /**
     * @return array
     */
    public static function getEventTypes()
    {
        return self::$eventTypes;
    }

    public function getEventTypeLabel()
    {
        return self::$eventTypes[$this->type];
    }

    public static function getUmbrellaAccounts()
    {
        return ArrayHelper::map(self::$umbrellaAccounts, 'id', 'name');
    }

    public static function getUmbrellaAccount($umbrellaAccountId)
    {
        return array_filter(self::$umbrellaAccounts, function ($a) use ($umbrellaAccountId) {
            return $a['id'] == $umbrellaAccountId;
        });
    }

    /**
     * @param $umbrellaId
     * @return array
     */
    public static function getLedgerAccounts($umbrellaId)
    {
        return array_filter(self::$accounts, function ($a) use ($umbrellaId) {
            return $a['parent'] == $umbrellaId;
        });
    }

    public static function getLedgerAccount($ledgerAccountId)
    {
        return array_filter(self::$accounts, function ($a) use ($ledgerAccountId) {
            return $a['id'] == $ledgerAccountId;
        });
    }

    public static function getMappedLedgerAccounts($umbrellaId)
    {
        return ArrayHelper::map(self::getLedgerAccounts($umbrellaId), 'id', 'name');
    }

    public static function getWeeks()
    {
        return self::$weeks;
    }

    public static function getDaysOfWeek()
    {
        return self::$daysOfWeek;
    }

    public static function daysGenerator($days)
    {
        foreach (self::$daysOfWeek as $dayOfWeek => $value) {
            if (($days & $dayOfWeek) > 0) {
                yield $dayOfWeek;
            }
        }
    }

    public function getRepeatOnDays()
    {
        return iterator_to_array(self::daysGenerator($this->repeat_on_days));
    }

    public function getRepeatOnDaysNames()
    {
        return array_map(function ($index) {
            return self::$daysOfWeek[$index];
        }, $this->getRepeatOnDays());
    }

    /**
     *
     * @param $value
     */
    public function setRepeatOnDays($value)
    {
        if (!is_array($value)) {
            $value = [];
        }
        $result = 0;
        foreach ($value as $dayOfWeek) {
            $result |= $dayOfWeek;
        }

        $this->repeat_on_days = $result;
    }

    public static function getOneFullDayEventTypes()
    {
        return self::$oneFullDayEventTypes;
    }

    /**
     * @param $checkedFormIdList
     */
    public function linkForms($checkedFormIdList, $checkedMandatoryIdList = [], $editedFormIds = [])
    {
        $linkedFormIdList = ArrayHelper::map($this->getForms()->where(['form.id' => $editedFormIds])->asArray()->all(), 'id', 'id');
        $unlinkList = array_diff($linkedFormIdList, $checkedFormIdList);
        $linkList = array_diff($checkedFormIdList, $linkedFormIdList);

        // unlink old relation
        foreach ($unlinkList as $formId) {
            $formModel = Form::find()->where(['id' => $formId])->one();
            if ($formModel !== null) {
                $this->unlink('forms', $formModel, true);
            }
        }

        // link new relation
        foreach ($linkList as $formId) {
            $formModel = Form::find()->where(['id' => $formId])->one();
            if ($formModel !== null) {
                $this->link('forms', $formModel, [
                    'is_mandatory' => in_array($formId, $checkedMandatoryIdList)
                ]);
            }
        }

        //update mandatory
        $lnkEventForms = $this->getLnkEventForms()->where(['form_id' => $checkedFormIdList])->all();
        /* @var $lnkEventForm LnkEventForm */
        foreach ($lnkEventForms as $lnkEventForm) {
            $lnkEventForm->is_mandatory = in_array($lnkEventForm->form_id, $checkedMandatoryIdList);
            $lnkEventForm->save();
        }
    }

    /**
     * @param $checkedProductIds
     * @param $setProductData
     * @throws \yii\base\InvalidConfigException
     */
    public function linkProducts($checkedProductIds, $setProductData, $editedProductIds)
    {
        $linkedProductIds = ArrayHelper::map($this->getProducts()->where(['product.id' => $editedProductIds])->asArray()->all(), 'id', 'id');
        $unlinkList = array_diff($linkedProductIds, $checkedProductIds);
        $linkList = array_diff($checkedProductIds, $linkedProductIds);

        // unlink old relations
        foreach ($unlinkList as $productId) {
            $productModel = Product::find()->where(['id' => $productId])->one();
            if ($productModel !== null) {
                $this->unlink('products', $productModel, true);
            }
        }

        // link new relations
        foreach ($linkList as $productId) {
            $productModel = Product::find()->where(['id' => $productId])->one();
            if ($productModel !== null) {
                $this->link('products', $productModel, $setProductData[$productId]);
            }
        }

        // update lnkEventProducts data
        foreach ($checkedProductIds as $productId) {
            $lnkEventProductModel = EventProduct::find()->where(['product_id' => $productId])->one();
            $lnkEventProductModel->loadFromArray($setProductData[$productId]);
            $lnkEventProductModel->save();
        }
    }

    public function linkProductById($productId)
    {
        $lnkEventProduct = EventProduct::find()->where(['doc_event_id' => $this->id, 'product_id' => $productId])->one();
        if ($lnkEventProduct !== null) {
            return false;
        }
        $productModel = Product::find()->where(['id' => $productId])->one();
        if ($productModel === null) {
            return false;
        }
        $this->link('products', $productModel, [
            'cost' => $productModel->cost,
            'price' => $productModel->price,
            'min_quantity' => 1,
            'max_quantity' => 1,
        ]);
        return true;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLnkEventProducts()
    {
        return $this->hasMany(EventProduct::class, ['doc_event_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProducts()
    {
        return $this->hasMany(Product::class, ['id' => 'product_id'])->via('lnkEventProducts');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getLnkEventForms()
    {
        return $this->hasMany(LnkEventForm::class, ['doc_event_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getForms()
    {
        return $this->hasMany(Form::class, ['id' => 'form_id'])
            ->via('lnkEventForms');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getVisibilitySection()
    {
        return $this->hasOne(EventVisibilitySection::class, ['id' => 'doc_event_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getMainPhoto()
    {
        return $this->hasOne(EventPhoto::class, ['event_id' => 'id'])
            ->andOnCondition(['is_main' => true]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParentPhoto()
    {
        return $this->hasOne(EventPhoto::class, ['event_id' => 'predecessor'])
            ->andOnCondition(['is_main' => true]);
    }

    public function getChildrenEvents()
    {
        return $this->hasMany(self::class, ['predecessor' => 'id']);
    }

    /**
     * @return DailyEventManager|MonthlyEventManager|WeeklyEventManager
     * @throws LogicException
     */
    public function getPeriodicEventManager()
    {
        switch ($this->frequency) {
            case self::FREQUENCY_DAILY:
                return new DailyEventManager($this);
            case self::FREQUENCY_WEEKLY:
                return new WeeklyEventManager($this);
            case self::FREQUENCY_MONTHLY:
                return new MonthlyEventManager($this);
            default:
                throw new LogicException('Wrong frequency value.');
        }
    }

    public function isPublished()
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * День недели
     * @param $number
     * @return bool|mixed
     */
    public static function getDayWeek($number)
    {
        if (isset(self::$daysOfWeek[$number])) {
            return self::$daysOfWeek[$number];
        }
        return false;
    }

    public function isOnlyView()
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function wasPublished()
    {
        $ldgEventStatus = LdgEventStatus::find()->where(['doc_event_id' => $this->id])->one();
        return !empty($ldgEventStatus);
    }

}