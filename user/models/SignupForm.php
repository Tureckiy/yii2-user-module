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
class SignupForm extends Model
{
    /**
     * @var
     */
    public $username;
    /**
     * @var
     */
    public $email;
    
    
    public $group;
    /**
     * @var
     */
    public $password;

    /**
     * @inheritdoc
     */
    public function rules()
    {
//        return [
//            ['username', 'filter', 'filter' => 'trim'],
//            ['username', 'required'],
//            ['username', 'unique',
//                'targetClass'=>'\common\models\User',
//                'message' => Yii::t('frontend', 'This username has already been taken.')
//            ],
//            ['username', 'string', 'min' => 2, 'max' => 255],
//
//            ['email', 'filter', 'filter' => 'trim'],
//            ['email', 'required'],
//            ['email', 'email'],
//            ['email', 'unique',
//                'targetClass'=> '\common\models\User',
//                'message' => Yii::t('frontend', 'This email address has already been taken.')
//            ],
//
//            ['password', 'required'],
//            ['password', 'string', 'min' => 6],
//        ];

        return [
            ['username', 'filter', 'filter' => 'trim'],
            ['group', 'required'],
//            ['username', 'unique',
//                'targetClass'=>'\common\models\User',
//                'message' => Yii::t('frontend', 'This username has already been taken.')
//            ],
            ['group', 'string', 'min' => 2, 'max' => 255],

            ['email', 'filter', 'filter' => 'trim'],
            ['email', 'required'],
            ['email', 'email'],
            ['email', 'unique',
                'targetClass'=> '\common\models\User',
                'message' => Yii::t('frontend', 'This email address has already been taken.')
            ],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels()
    {
        return [
//            'username'=>Yii::t('frontend', 'Username'),
//            'email'=>Yii::t('frontend', 'E-mail'),
//            'password'=>Yii::t('frontend', 'Password'),

            'username'=>Yii::t('frontend', 'Username'),
            'email'=>Yii::t('frontend', 'E-mail'),
            'password'=>Yii::t('frontend', 'Password'),
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
            $shouldBeActivated = $this->shouldBeActivated();
            $user = new User();

            //$user->username = $this->username;
            $user->username = $this->email;

            $user->email = $this->email;
            $user->status = $shouldBeActivated ? User::STATUS_NOT_ACTIVE : User::STATUS_ACTIVE;

            //$user->setPassword($this->password);
            $user->setPassword($this->email);

            if(!$user->save()) {
                throw new Exception("User couldn't be  saved");
            };
            $user->afterSignup();
            if ($shouldBeActivated) {
                $token = UserToken::create(
                    $user->id,
                    UserToken::TYPE_ACTIVATION,
                    Time::SECONDS_IN_A_DAY
                );
                Yii::$app->commandBus->handle(new SendEmailCommand([
                    'subject' => Yii::t('frontend', 'Activation email'),
                    'view' => 'activation',
                    'to' => $this->email,
                    'params' => [
                        'url' => Url::to(['/user/sign-in/activation', 'token' => $token->token], true)
                    ]
                ]));
            }
            return $user;
        } else {
            p($this->getErrors());
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
