<?php

namespace frontend\modules\user\models;

use cheatsheet\Time;
use yii\base\Exception;
use common\models\User;
use common\models\UserProfile;
use Yii;
use yii\base\Model;

/**
 * Login form
 */
class ProfileForm extends Model
{
    public $firstname;
    public $lastname;
    public $address;
    public $email;
    public $phone;
    public $user_job;
    public $locale;
    public $gender;
    public $passwordField;
    public $passwordFieldConfirm;


    private $model;

    const SCENARIO_UPDATE_PROFILE = 'update_profile';
    const SCENARIO_UPDATE_PASSWORD = 'update_pass';

    //private $psDomains = ['@presentsimple.com.ua', '@present-simple.com.ua'];
    private $psDomains = '@presentsimple.com.ua';

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['firstname', 'lastname', 'address', 'phone', 'user_job', 'email'], 'filter', 'filter' => 'trim'],
            [['firstname', 'lastname', 'address', 'phone', 'user_job', 'email'], 'filter', 'filter' => 'strip_tags'],
            [['firstname', 'lastname', 'address', 'phone', 'user_job', 'email'], 'string', 'min' => 2, 'max' => 255],
            ['phone', 'string', 'min' => 10, 'max' => 10],
            ['locale', 'string'],
            [['phone', 'gender'], 'integer'],

            [['firstname', 'lastname', 'phone', 'user_job'], 'required', 'on' => self::SCENARIO_UPDATE_PROFILE],

            ['email', 'email'],

            ['phone', 'checkUniqPhone'],
            ['email', 'checkUniqEmail'],

            [['passwordField', 'passwordFieldConfirm'], 'string', 'min' => 6],
            [['passwordField', 'passwordFieldConfirm'], 'required', 'on' => self::SCENARIO_UPDATE_PASSWORD],
            ['passwordField', 'compare', 'compareAttribute' => 'passwordFieldConfirm'],

//            [
//                'phone',
//                'unique',
//                'targetClass' => UserProfile::className(),
//                'filter' => function ($query) {
//                    if (!$this->getModel()->isNewRecord) {
//                        $query->andWhere(['not', ['phone' => $this->getModel()->user_id]]);
//                    }
//                },
//            ],
        ];
    }


    public function attributeLabels()
    {
        return [
            'phone' => Yii::t('frontend', 'Phone'),
            'user_job' => Yii::t('frontend', 'Job & Position'),
            'address' => Yii::t('frontend', 'Address'),
            'firstname' => Yii::t('frontend', 'First name'),
            'lastname' => Yii::t('frontend', 'Last name'),
            'locale' => Yii::t('frontend', 'Language'),
            'gender' => Yii::t('common', 'Gender'),
            'passwordField' => Yii::t('frontend', 'New password'),
            'passwordFieldConfirm' => Yii::t('frontend', 'Confirm Password'),
        ];
    }

    public function checkUniqPhone($attribute_name, $params)
    {
        $phone = $this->$attribute_name;

        $sql = '';
        $sql .= ' SELECT t.user_id  ';
        $sql .= ' FROM user_profile as t ';
        $sql .= ' WHERE t.user_id <> :id  AND t.phone = :phone';


        $params = [
            ':id' => (int)$this->getModel()->user_id,
            ':phone' => $phone
        ];
        $row = Yii::$app->db->createCommand($sql)->bindValues($params)
            ->queryOne();

        if ($row) {
            $this->addError($attribute_name, Yii::t('backend', 'Phone already used'));

            return false;
        }

        return true;
    }

    public function checkUniqEmail($attribute_name, $params)
    {
        $email = $this->$attribute_name;

        $sql = '';
        $sql .= ' SELECT t.id  ';
        $sql .= ' FROM user as t ';
        $sql .= ' WHERE t.id <> :id  AND t.email = :email';


        $params = [
            ':id' => (int)$this->getModel()->user_id,
            ':email' => $email
        ];
        $row = Yii::$app->db->createCommand($sql)->bindValues($params)
            ->queryOne();

        if ($row) {
            $this->addError($attribute_name, Yii::t('backend', 'Email already used'));

            return false;
        }

        return true;
    }


    private function checkSystemEmail($email)
    {
        $pos = strpos($email, $this->psDomains);

        if (false === $pos) {
            return false;
        }

        return true;
    }

    /**
     * @return User
     */
    public function getModel()
    {
        if (!$this->model) {
            $this->model = new UserProfile();
        }

        return $this->model;
    }

    /**
     * @param UserProfile $model
     *
     * @return mixed
     */
    public function setModel($model)
    {

        $this->model = $model;

        $this->firstname = $model->firstname;
        $this->lastname = $model->lastname;


        $this->address = $model->address;
        $this->phone = $model->phone;
        $this->user_job = $model->user_job;

        $this->locale = $model->locale;
        $this->gender = $model->gender;

        if (!$this->checkSystemEmail($model->user->email)) {
            $this->email = $model->user->email;
        }

        return $this->model;
    }


    public function save()
    {
        if ($this->validate()) {

            $model = $this->getModel();

            $model->firstname = $this->firstname;
            $model->lastname = $this->lastname;
            $model->address = $this->address;

            $model->phone = $this->phone;
            $model->user_job = $this->user_job;

            $model->locale = $this->locale;
            $model->gender = $this->gender;

            // set selected locale
            Yii::$app->language = $model->locale;
            Yii::$app->response->cookies->add(new \yii\web\Cookie([
                'name' => '_lang',
                'value' => $model->locale,
            ]));

            if (!$model->save()) {
//                echo "<pre>";
//                print_r($model->getErrors());
//                echo "</pre>";
//                exit;
                throw new Exception('Profile not saved');
            } else {

                $model->user->updateAttributes(
                    [
                        'email' => $this->email,

                    ]
                );
            }

            return !$model->hasErrors();
        }

        return null;
    }
}
