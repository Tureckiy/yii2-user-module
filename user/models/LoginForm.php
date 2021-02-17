<?php

namespace frontend\modules\user\models;

use cheatsheet\Time;
use common\models\User;
use Yii;
use yii\base\Model;

/**
 * Login form
 */
class LoginForm extends Model
{
    public $identity;
    public $password;
    public $rememberMe = true;

    private $user = false;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['identity', 'password'], 'required'],
            // rememberMe must be a boolean value
            ['rememberMe', 'boolean'],
            // password is validated by validatePassword()
            ['password', 'validatePassword'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'identity' => Yii::t('frontend', 'Username or email'),
            'password' => Yii::t('frontend', 'Password'),
            'rememberMe' => Yii::t('frontend', 'Remember Me'),
        ];
    }


    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     */
    public function validatePassword()
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();

            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError('password', Yii::t('frontend', 'Incorrect username or password.'));
                return;
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return boolean whether the user is logged in successfully
     */
    public function login()
    {
        $user = $this->getUser();
        if ($user) {
            $roles = \Yii::$app->authManager->getRolesByUser($user->id);

            if (isset($roles['member'])) {
                $this->addError('password', Yii::t('frontend', 'Not allowed access to this section'));
                return false;
            }
        }

        if ($user && $this->validate()) {
            if (Yii::$app->user->login($this->getUser(), $this->rememberMe ? Time::SECONDS_IN_A_MONTH : 0)) {
                return true;
            }
        }
        return false;
    }


    private function loginAuto($userModel)
    {
        if (Yii::$app->user->login($userModel, $this->rememberMe ? Time::SECONDS_IN_A_MONTH : 0)) {
            return true;
        }

        return false;
    }

    private function checkFrom($from)
    {
        $from = (int)$from;
        $user = User::find()
            ->active()
            ->andWhere(['id' => $from])
            ->one();

        if ($user) {
            return true;
        } else {
            return false;
        }
    }

    public function autologin()
    {

        $request = Yii::$app->request;
        if (!$this->checkFrom($request->get('from'))) {
            return false;
        }

        $timeRequest = $request->get('time', 0);
        $time = date('h-m');

        $timeRequest = base64_decode($timeRequest);
        if ($time != $timeRequest) {
            return false;
        }

        $upd = $request->get('upd', 0);
        $token = $request->get('token', 0);

        $user = User::find()
            ->active()
            ->andWhere(['updated_at' => $upd, 'access_token' => $token])
            ->one();


        if (!$user) {
            return false;
        } else {
            return $this->loginAuto($user);
        }
    }


    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->user === false) {
            $this->user = User::find()
                ->active()
                ->andWhere(['or', ['username' => $this->identity], ['email' => $this->identity]])
                ->one();
        }


        return $this->user;
    }


}
