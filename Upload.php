<?php
namespace common\models;

use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "upload".
 *
 * @property int $id
 * @property string $path
 * @property string $filename
 * @property string $created_at
 * @property int $updated_at
 */
class Upload extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'upload';
    }

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
                'value' => function() { return date('Y-m-d H:i:s'); },
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['path'], 'string', 'max' => 255],
            [['filename'], 'string', 'max' => 50],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'path' => 'Path',
            'filename' => 'Filename',
            'created_at' => 'Created At',
        ];
    }
}
