<?php

namespace frontend\modules\user\models;

use cheatsheet\Time;
use common\commands\SendEmailCommand;
use common\models\User;
use common\models\UserToken;
use frontend\modules\user\Module;
use yii\base\Exception;
use yii\base\Model;
use Yii;
use yii\helpers\Url;

/**
 * Signup form
 */
class RegisterForm extends Model
{
    /**
     * @var
     */
    public $phone;

    /**
     * @var
     */
    public $name;

    /**
     * @var
     */
    public $email;

    /**
     * @var
     */
    public $password = '';

    const SCENARIO_DEFAULT = 'default';
    const SCENARIO_FIRST_TEST = 'get_test';


    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['phone', 'filter', 'filter' => function ($value) {
                return preg_replace("/[^0-9,.]/", "", $value);
            }],
            ['phone', 'required'],

            ['phone', 'phoneValidationFormat'],
            ['phone', 'phoneValidation'],

            ['phone', 'unique',
                'targetClass' => '\common\models\User',
                'targetAttribute' => 'username',
                'message' => Yii::t('frontend', 'This phone has already been taken.')
            ],

            ['name', 'required', 'on' => self::SCENARIO_FIRST_TEST],

            ['email', 'email'],
            ['email', 'required', 'on' => self::SCENARIO_FIRST_TEST],
            ['email', 'unique',
                'targetClass' => '\common\models\User',
                'targetAttribute' => 'email',
                'message' => Yii::t('frontend', 'This email has already been taken.')
            ],

            ['password', 'string'],
        ];
    }

    public function phoneValidation($attribute_name, $params)    {

        if (12 != strlen($this->phone)) {
            $this->addError($attribute_name, Yii::t('user', 'Not valid Phone format'));
            return false;
        }
        return true;

    }

    public function phoneValidationFormat($attribute_name, $params) {
        $firstCharacter = substr($this->phone, 0, 3);

        if ($firstCharacter != '380'  ) {
            $this->addError($attribute_name, Yii::t('user', 'Please start 380'));

            return false;
        }

        return true;
    }


    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
            'phone' => Yii::t('frontend', 'Phone'),
            'password' => Yii::t('frontend', 'Password'),
            'name' => Yii::t('frontend', 'Name'),
            'email' => Yii::t('frontend', 'Email'),
        ];
    }

    /**
     * Signs user up.
     *
     * @return User|null the saved model or null if saving fails
     */
    public function signup()
    {
        if ($this->validate()) {
            $shouldBeActivated = false; //$this->shouldBeActivated();
            $user = new User();

            $user->username = $this->phone;

            if (!empty($this->email)) {
                $user->email = $this->email;
            } else {
                $user->email = $this->phone . '@present-simple.com.ua';
            }

            $user->status = $shouldBeActivated ? User::STATUS_NOT_ACTIVE : User::STATUS_ACTIVE;

            // set password
            if(!empty($password)) {
                $user->setPassword($password);
            } else {
                $user->setPassword($this->phone);
            }

            $user->pwd_updated = time();

            if ($user->validate() && $user->save(false)) {


                // send email
                Yii::$app->commandBus->handle(new SendEmailCommand([
                    'emailProvider' => env('EMAIL_PROVIDER'),
          //..
                    'view' => 'custom_email',
                    'params' => [
                        'content' => var_export($user->attributes, true)
                    ]
                ]));
            } else {
                Yii::error("Error user registration :" . var_export($user->attributes, true));
                p($user->errors);
                throw new Exception("User couldn't be  saved");
            }

            $profileData = [];
            if (!empty($this->name)) {
                $profileData['firstname'] = trim($this->name);
            }

            $user->afterSignup($profileData);

            // assign role LANG_TESTING
            if ($this->getScenario() === self::SCENARIO_FIRST_TEST) {
                try {
                    $user->assignRole(User::ROLE_LANG_TESTING);
                } catch (\Exception $e) {
                    Yii::error('Role assignment is failed', 'register');
                }
            }

            if ($shouldBeActivated) {
                $token = UserToken::create(
                    $user->id,
                    UserToken::TYPE_ACTIVATION,
                    Time::SECONDS_IN_A_DAY
                );

            }
            return $user;
        } else {
//
        }

        return null;
    }

    /**
     * @return bool
     */
    public function shouldBeActivated()
    {
        /** @var Module $userModule */
        $userModule = Yii::$app->getModule('user');
        if (!$userModule) {
            return false;
        } elseif ($userModule->shouldBeActivated) {
            return true;
        } else {
            return false;
        }
    }
}
